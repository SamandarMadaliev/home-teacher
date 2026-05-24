<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoNotePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_preview_notes(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Test',
        ]);
        $video = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson',
            'file_path' => 'l.mp4',
            'sort_order' => 1,
        ]);

        $this->postJson(route('videos.notes.preview', $video), ['body' => '**hi**'])
            ->assertUnauthorized();
    }

    public function test_user_can_preview_markdown_as_safe_html(): void
    {
        $user = User::factory()->create();
        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Test',
        ]);
        $video = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson',
            'file_path' => 'l.mp4',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('videos.notes.preview', $video), [
                'body' => '**bold** and <script>alert(1)</script>',
            ])
            ->assertOk();

        $html = (string) $response->json('html');
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_user_cannot_preview_notes_for_another_users_video(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $course = Course::query()->create([
            'user_id' => $bob->id,
            'title' => 'Secret',
        ]);
        $video = Video::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson',
            'file_path' => 'l.mp4',
            'sort_order' => 1,
        ]);

        $this->actingAs($alice)
            ->postJson(route('videos.notes.preview', $video), ['body' => 'nope'])
            ->assertNotFound();
    }
}
