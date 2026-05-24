<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProgress;
use App\Services\ProfileAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_analytics(): void
    {
        $this->get(route('profile.analytics'))->assertRedirect(route('login'));
    }

    public function test_analytics_page_shows_user_stats(): void
    {
        $user = User::factory()->create();

        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'PHP Basics',
        ]);

        $lessonA = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Intro',
            'file_path' => 'intro.mp4',
            'sort_order' => 1,
        ]);
        $lessonB = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Types',
            'file_path' => 'types.mp4',
            'sort_order' => 2,
        ]);

        $this->createProgress($lessonA, [
            'last_position' => 100,
            'duration_seconds' => 100,
            'completed' => true,
            'created_at' => '2025-03-10 12:00:00',
            'updated_at' => '2025-03-15 12:00:00',
        ]);
        $this->createProgress($lessonB, [
            'last_position' => 20,
            'duration_seconds' => 100,
            'completed' => false,
            'created_at' => '2025-04-02 12:00:00',
            'updated_at' => '2025-04-05 12:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('profile.analytics'))
            ->assertOk()
            ->assertSee('Learning progress', false)
            ->assertSee('Lesson completed', false)
            ->assertSee('Lessons started', false)
            ->assertSee('Lessons in library', false)
            ->assertSee('PHP Basics', false)
            ->assertSee('March 2025', false)
            ->assertSee('Estimated learning time', false)
            ->assertSee('2 minutes', false)
            ->assertSee('profile-tab--active', false);
    }

    public function test_user_does_not_see_other_users_progress_in_analytics(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $bobCourse = Course::query()->create([
            'user_id' => $bob->id,
            'title' => 'Bob Secret Course',
        ]);
        $bobVideo = Video::query()->create([
            'course_id' => $bobCourse->id,
            'title' => 'Secret',
            'file_path' => 's.mp4',
            'sort_order' => 1,
        ]);
        $this->createProgress($bobVideo, [
            'last_position' => 50,
            'duration_seconds' => 100,
            'completed' => true,
        ]);

        $this->actingAs($alice)
            ->get(route('profile.analytics'))
            ->assertOk()
            ->assertDontSee('Bob Secret Course');
    }

    public function test_service_identifies_most_active_month(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Test',
        ]);

        foreach (range(1, 3) as $i) {
            $video = Video::query()->create([
                'course_id' => $course->id,
                'title' => "L{$i}",
                'file_path' => "l{$i}.mp4",
                'sort_order' => $i,
            ]);
            $this->createProgress($video, [
                'last_position' => 10,
                'duration_seconds' => 100,
                'completed' => false,
                'updated_at' => '2025-03-0'.$i.' 12:00:00',
            ]);
        }

        $videoApril = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'L4',
            'file_path' => 'l4.mp4',
            'sort_order' => 4,
        ]);
        $this->createProgress($videoApril, [
            'last_position' => 10,
            'duration_seconds' => 100,
            'completed' => false,
            'updated_at' => '2025-04-01 12:00:00',
        ]);

        $stats = app(ProfileAnalyticsService::class)->forUser($user);

        $this->assertSame('2025-03', $stats['activity']['most_active_month']['key']);
        $this->assertSame(3, $stats['activity']['most_active_month']['lessons']);
        $this->assertSame(4, $stats['summary']['lessons_started']);
        $this->assertSame(0, $stats['summary']['lessons_completed']);
    }

    public function test_watch_time_sums_furthest_point_per_lesson(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Test',
        ]);

        $completed = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Done',
            'file_path' => 'done.mp4',
            'sort_order' => 1,
        ]);
        $partial = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Part',
            'file_path' => 'part.mp4',
            'sort_order' => 2,
        ]);

        $this->createProgress($completed, [
            'last_position' => 3600,
            'duration_seconds' => 3600,
            'completed' => true,
        ]);
        $this->createProgress($partial, [
            'last_position' => 900,
            'duration_seconds' => 3600,
            'completed' => false,
        ]);

        $stats = app(ProfileAnalyticsService::class)->forUser($user);

        $this->assertSame(4500, $stats['summary']['watch_time_seconds']);
        $this->assertSame(1.3, $stats['summary']['watch_time_hours']);
        $this->assertSame('1.3 hours', $stats['summary']['watch_time_label']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProgress(Video $video, array $attributes = []): VideoProgress
    {
        $timestamps = [];
        foreach (['created_at', 'updated_at'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $timestamps[$key] = $attributes[$key];
                unset($attributes[$key]);
            }
        }

        $progress = VideoProgress::query()->create(array_merge([
            'video_id' => $video->id,
            'last_position' => 0,
            'duration_seconds' => null,
            'completed' => false,
        ], $attributes));

        if ($timestamps !== []) {
            $progress->forceFill($timestamps)->saveQuietly();
        }

        return $progress;
    }
}
