<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_flags_course_just_completed_when_last_lesson_finishes(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Laravel Deep Dive',
        ]);

        $first = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Intro',
            'file_path' => 'intro.mp4',
            'sort_order' => 1,
        ]);
        $last = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Outro',
            'file_path' => 'outro.mp4',
            'sort_order' => 2,
        ]);

        VideoProgress::query()->create([
            'video_id' => $first->id,
            'last_position' => 100,
            'duration_seconds' => 100,
            'completed' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('videos.progress', $last), [
                'current_time' => 92,
                'duration' => 100,
            ])
            ->assertOk()
            ->assertJson([
                'completed' => true,
                'course_completed' => true,
                'course_just_completed' => true,
                'course_title' => 'Laravel Deep Dive',
            ]);
    }

    public function test_progress_does_not_repeat_course_just_completed(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Done Course',
        ]);

        $video = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Only',
            'file_path' => 'only.mp4',
            'sort_order' => 1,
        ]);

        VideoProgress::query()->create([
            'video_id' => $video->id,
            'last_position' => 100,
            'duration_seconds' => 100,
            'completed' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('videos.progress', $video), [
                'current_time' => 100,
                'duration' => 100,
            ])
            ->assertOk()
            ->assertJson([
                'course_completed' => true,
                'course_just_completed' => false,
            ]);
    }

    public function test_course_is_fully_completed_only_when_all_lessons_are_done(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Partial',
        ]);

        $done = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'A',
            'file_path' => 'a.mp4',
            'sort_order' => 1,
        ]);
        $open = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'B',
            'file_path' => 'b.mp4',
            'sort_order' => 2,
        ]);

        VideoProgress::query()->create([
            'video_id' => $done->id,
            'last_position' => 50,
            'duration_seconds' => 50,
            'completed' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('videos.progress', $open), [
                'current_time' => 10,
                'duration' => 100,
            ])
            ->assertOk()
            ->assertJson([
                'completed' => false,
                'course_completed' => false,
                'course_just_completed' => false,
            ]);

        $course->refresh();
        $this->assertFalse($course->isFullyCompleted());
    }
}
