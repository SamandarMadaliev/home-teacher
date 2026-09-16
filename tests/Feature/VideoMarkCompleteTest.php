<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoMarkCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_pdf_lesson_complete_creates_progress_without_a_duration(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Docs course',
        ]);

        $lesson = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Cheat sheet',
            'file_path' => 'cheat-sheet.pdf',
            'type' => Video::TYPE_PDF,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->postJson(route('videos.mark-complete', $lesson), ['completed' => true])
            ->assertOk()
            ->assertJson([
                'completed' => true,
                'course_completed' => true,
                'course_just_completed' => true,
            ]);

        $this->assertDatabaseHas('video_progress', [
            'video_id' => $lesson->id,
            'completed' => true,
        ]);
    }

    public function test_unmarking_a_lesson_reverts_course_completion(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Docs course',
        ]);

        $lesson = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Guide',
            'file_path' => 'guide.html',
            'type' => Video::TYPE_HTML,
            'sort_order' => 1,
        ]);

        VideoProgress::query()->create([
            'video_id' => $lesson->id,
            'completed' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('videos.mark-complete', $lesson), ['completed' => false])
            ->assertOk()
            ->assertJson([
                'completed' => false,
                'course_completed' => false,
                'course_just_completed' => false,
            ]);
    }
}
