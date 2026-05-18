<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_profile(): void
    {
        $this->get(route('profile.index'))->assertRedirect(route('login'));
        $this->get(route('profile.notes'))->assertRedirect(route('login'));
        $this->get(route('profile.account'))->assertRedirect(route('login'));
    }

    public function test_profile_index_redirects_to_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.index'))
            ->assertRedirect(route('profile.account'));
    }

    public function test_user_sees_only_their_notes_and_can_filter(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $aliceCourse = Course::query()->create([
            'user_id' => $alice->id,
            'title' => 'Alice PHP',
        ]);
        $bobCourse = Course::query()->create([
            'user_id' => $bob->id,
            'title' => 'Bob JS',
        ]);

        $aliceVideoA = Video::query()->create([
            'course_id' => $aliceCourse->id,
            'title' => 'Lesson A',
            'file_path' => 'a.mp4',
            'sort_order' => 1,
        ]);
        $aliceVideoB = Video::query()->create([
            'course_id' => $aliceCourse->id,
            'title' => 'Lesson B',
            'file_path' => 'b.mp4',
            'sort_order' => 2,
        ]);
        $bobVideo = Video::query()->create([
            'course_id' => $bobCourse->id,
            'title' => 'Secret',
            'file_path' => 's.mp4',
            'sort_order' => 1,
        ]);

        VideoNote::query()->create([
            'video_id' => $aliceVideoA->id,
            'body' => 'Alice note on A',
        ]);
        VideoNote::query()->create([
            'video_id' => $aliceVideoB->id,
            'body' => 'Alice note on B',
        ]);
        VideoNote::query()->create([
            'video_id' => $bobVideo->id,
            'body' => 'Bob secret note',
        ]);

        $this->actingAs($alice)
            ->get(route('profile.notes'))
            ->assertOk()
            ->assertSee('Alice note on A')
            ->assertSee('Alice note on B')
            ->assertDontSee('Bob secret note');

        $this->actingAs($alice)
            ->get(route('profile.notes', ['course' => $aliceCourse->id, 'video' => $aliceVideoA->id]))
            ->assertOk()
            ->assertSee('Alice note on A')
            ->assertDontSee('Alice note on B');
    }
}
