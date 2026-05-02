<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Video;
use App\Services\CourseVideoScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(
        private CourseVideoScanner $scanner
    ) {}

    public function index(): View
    {
        $courses = Course::query()->withCount('videos')->orderBy('title')->get();

        return view('courses.index', compact('courses'));
    }

    public function create(): View
    {
        return view('courses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'folder_path' => ['required', 'string', 'max:4096'],
        ]);

        $resolved = realpath(trim($validated['folder_path']));
        if ($resolved === false || ! is_dir($resolved)) {
            return back()->withInput()->withErrors([
                'folder_path' => 'That path must be an existing folder on this machine.',
            ]);
        }

        $course = Course::create([
            'title' => $validated['title'],
            'folder_path' => $resolved,
        ]);

        $count = $this->scanner->sync($course);

        $message = $count === 0
            ? 'Course added. No supported video files were found — put videos in that folder and click Rescan.'
            : 'Course added with '.$count.' lesson(s).';

        return redirect()->route('courses.show', $course)->with('status', $message);
    }

    public function show(Course $course): View
    {
        $course->load(['videos.progress']);

        $videos = $course->videos;
        $current = $this->resolveCurrentVideo($videos);
        $nextAfterCurrent = $current ? $videos->firstWhere('sort_order', '>', $current->sort_order) : null;

        return view('courses.show', [
            'course' => $course,
            'currentVideo' => $current,
            'nextVideo' => $nextAfterCurrent,
        ]);
    }

    public function rescan(Course $course): RedirectResponse
    {
        if ($course->folder_path === null || $course->folder_path === '') {
            return back()->withErrors([
                'folder' => 'This course has no folder path set.',
            ]);
        }

        if ($course->folderRootReal() === null) {
            return back()->withErrors([
                'folder' => 'That folder is missing or not reachable.',
            ]);
        }

        $count = $this->scanner->sync($course);

        $message = $count === 0
            ? 'Rescanned. No supported video files found.'
            : 'Rescanned: '.$count.' lesson(s) indexed.';

        return back()->with('status', $message);
    }

    /**
     * First lesson that is not completed (single-user course flow).
     *
     * @param  \Illuminate\Support\Collection<int, Video>  $videos
     */
    private function resolveCurrentVideo($videos): ?Video
    {
        foreach ($videos as $video) {
            if (! $video->progress?->completed) {
                return $video;
            }
        }

        return $videos->last();
    }
}
