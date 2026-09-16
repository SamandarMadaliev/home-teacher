<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\Video;
use App\Services\CourseVideoScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseVideoScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_indexes_video_pdf_and_html_files_with_the_right_type(): void
    {
        $user = User::factory()->create();
        $dir = sys_get_temp_dir().'/ht-scan-types-'.uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/lesson-1.mp4', 'video-bytes');
        file_put_contents($dir.'/slides.pdf', 'pdf-bytes');
        file_put_contents($dir.'/notes.html', '<p>notes</p>');
        file_put_contents($dir.'/readme.txt', 'ignored');

        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Mixed media course',
            'folder_path' => $dir,
        ]);

        $count = app(CourseVideoScanner::class)->sync($course);

        $this->assertSame(3, $count);

        $byPath = $course->videos()->get()->keyBy('file_path');
        $this->assertSame(Video::TYPE_VIDEO, $byPath->get('lesson-1.mp4')->type);
        $this->assertSame(Video::TYPE_PDF, $byPath->get('slides.pdf')->type);
        $this->assertSame(Video::TYPE_HTML, $byPath->get('notes.html')->type);
        $this->assertFalse($byPath->has('readme.txt'));
    }

    public function test_rescan_does_not_duplicate_or_retype_existing_lessons(): void
    {
        $user = User::factory()->create();
        $dir = sys_get_temp_dir().'/ht-scan-rescan-'.uniqid('', true);
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/slides.pdf', 'pdf-bytes');

        $course = Course::query()->create([
            'user_id' => $user->id,
            'title' => 'Rescan course',
            'folder_path' => $dir,
        ]);

        app(CourseVideoScanner::class)->sync($course);
        $video = $course->videos()->sole();
        $video->update(['title' => 'Custom title']);

        file_put_contents($dir.'/extra.htm', '<p>extra</p>');
        $count = app(CourseVideoScanner::class)->sync($course->fresh());

        $this->assertSame(2, $count);
        $video->refresh();
        $this->assertSame('Custom title', $video->title);
        $this->assertSame(Video::TYPE_HTML, $course->videos()->where('file_path', 'extra.htm')->value('type'));
    }
}
