<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProgress;
use App\Services\CourseVideoScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoursePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_has_not_been_started_without_progress(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Fresh Course',
        ]);

        Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'file_path' => 'lesson-1.mp4',
            'sort_order' => 1,
        ]);

        $course->load('videos.progress');

        $this->assertFalse($course->hasBeenStarted());
    }

    public function test_course_has_been_started_when_lesson_has_position(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Started Course',
        ]);

        $video = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'file_path' => 'lesson-1.mp4',
            'sort_order' => 1,
        ]);

        VideoProgress::query()->create([
            'video_id' => $video->id,
            'last_position' => 12.5,
            'duration_seconds' => 100,
            'completed' => false,
        ]);

        $course->load('videos.progress');

        $this->assertTrue($course->hasBeenStarted());
    }

    public function test_preview_modal_is_shown_for_unstarted_course_with_video_file(): void
    {
        $user = User::factory()->create();
        $dir = sys_get_temp_dir().'/ht-preview-'.uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/lesson-1.mp4', 'fake-video');

        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Previewable',
            'folder_path' => $dir,
        ]);

        Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'file_path' => 'lesson-1.mp4',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('course-preview-modal', false)
            ->assertSee('Watch preview', false)
            ->assertSee('Preview does not count toward your progress', false);
    }

    public function test_preview_modal_is_hidden_after_course_started(): void
    {
        $user = User::factory()->create();
        $dir = sys_get_temp_dir().'/ht-preview-'.uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/lesson-1.mp4', 'fake-video');

        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'In progress',
            'folder_path' => $dir,
        ]);

        $video = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'file_path' => 'lesson-1.mp4',
            'sort_order' => 1,
        ]);

        VideoProgress::query()->create([
            'video_id' => $video->id,
            'last_position' => 1,
            'duration_seconds' => 100,
            'completed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertDontSee('course-preview-modal', false)
            ->assertDontSee('Watch preview', false);
    }

    public function test_course_progress_can_be_reset(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Resettable Course',
        ]);

        $video = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'file_path' => 'lesson-1.mp4',
            'sort_order' => 1,
        ]);

        VideoProgress::query()->create([
            'video_id' => $video->id,
            'last_position' => 42.5,
            'duration_seconds' => 100,
            'completed' => true,
        ]);

        $this->actingAs($user)
            ->post(route('courses.reset-progress', $course))
            ->assertRedirect()
            ->assertSessionHas('status', 'Course progress reset.');

        $this->assertDatabaseMissing('video_progress', [
            'video_id' => $video->id,
        ]);
    }

    public function test_dedicated_preview_file_streams_and_is_excluded_from_scanner(): void
    {
        $user = User::factory()->create();
        $dir = sys_get_temp_dir().'/ht-preview-'.uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/preview.mp4', 'preview-bytes');
        file_put_contents($dir.'/lesson-1.mp4', 'lesson-bytes');

        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'With preview clip',
            'folder_path' => $dir,
        ]);

        $count = app(CourseVideoScanner::class)->sync($course);

        $this->assertSame(1, $count);
        $this->assertSame('lesson-1.mp4', $course->videos()->value('file_path'));
        $this->assertSame(realpath($dir.'/preview.mp4'), $course->previewAbsolutePath());

        $this->actingAs($user)
            ->get(route('courses.preview.stream', $course))
            ->assertOk();
    }
}
