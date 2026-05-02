<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Roadmap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoadmapReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_persists_pivot_order_with_sqlite_string_or_int_ids(): void
    {
        $roadmap = Roadmap::query()->create([
            'title' => 'Test path',
            'description' => null,
        ]);

        $a = Course::query()->create(['title' => 'A']);
        $b = Course::query()->create(['title' => 'B']);
        $c = Course::query()->create(['title' => 'C']);

        $roadmap->courses()->attach($a->id, ['sort_order' => 1]);
        $roadmap->courses()->attach($b->id, ['sort_order' => 2]);
        $roadmap->courses()->attach($c->id, ['sort_order' => 3]);

        $response = $this->postJson(route('roadmaps.courses.reorder', $roadmap), [
            'course_ids' => [(int) $c->id, (int) $a->id, (int) $b->id],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $ordered = $roadmap->fresh()->courses()->orderByPivot('sort_order')->pluck('courses.id')->values()->all();
        $this->assertSame([(int) $c->id, (int) $a->id, (int) $b->id], array_map('intval', $ordered));
    }
}
