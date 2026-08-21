<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoNoteStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_store_notes(): void
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

        $this->postJson(route('videos.notes.store', $video), ['body' => 'Hello'])
            ->assertUnauthorized();
    }

    public function test_user_can_store_note_via_json_without_reload(): void
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
            ->postJson(route('videos.notes.store', $video), [
                'body' => '**bold** cue',
                'timestamp_seconds' => 125,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Note saved.');

        $note = VideoNote::query()->first();
        $this->assertNotNull($note);
        $this->assertSame($video->id, $note->video_id);
        $this->assertSame('**bold** cue', $note->body);
        $this->assertSame(125.0, $note->timestamp_seconds);

        $html = (string) $response->json('note.html');
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('data-note-seek="125"', $html);
        $this->assertStringContainsString('2:05', $html);
    }

    public function test_user_cannot_store_notes_for_another_users_video(): void
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
            ->postJson(route('videos.notes.store', $video), ['body' => 'nope'])
            ->assertNotFound();
    }
}
