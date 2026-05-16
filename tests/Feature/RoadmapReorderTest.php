<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Roadmap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoadmapReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_persists_pivot_order_with_sqlite_string_or_int_ids(): void
    {
        $user = User::factory()->create();

        $roadmap = Roadmap::query()->create([
            'user_id' => $user->id,
            'title' => 'Test path',
            'description' => null,
        ]);

        $a = Course::query()->create(['user_id' => $user->id, 'title' => 'A']);
        $b = Course::query()->create(['user_id' => $user->id, 'title' => 'B']);
        $c = Course::query()->create(['user_id' => $user->id, 'title' => 'C']);

        $roadmap->courses()->attach($a->id, ['sort_order' => 1]);
        $roadmap->courses()->attach($b->id, ['sort_order' => 2]);
        $roadmap->courses()->attach($c->id, ['sort_order' => 3]);

        $response = $this->actingAs($user)->postJson(route('roadmaps.courses.reorder', $roadmap), [
            'course_ids' => [(int) $c->id, (int) $a->id, (int) $b->id],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $ordered = $roadmap->fresh()->courses()->orderByPivot('sort_order')->pluck('courses.id')->values()->all();
        $this->assertSame([(int) $c->id, (int) $a->id, (int) $b->id], array_map('intval', $ordered));
    }
}
