<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Video;
use App\Services\CourseVideoScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseController extends Controller
{
    public function __construct(
        private CourseVideoScanner $scanner
    ) {}

    public function index(): View
    {
        $courses = Course::query()
            ->forCurrentUser()
            ->active()
            ->with(['videos.progress'])
            ->withCount('videos')
            ->orderedForLibrary()
            ->get();

        $archivedCourses = Course::query()
            ->forCurrentUser()
            ->archived()
            ->withCount('videos')
            ->orderByDesc('archived_at')
            ->orderBy('title')
            ->get();

        return view('courses.index', compact('courses', 'archivedCourses'));
    }

    public function create(): View
    {
        return view('courses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'folder_path' => ['required', 'string', 'max:4096'],
        ]);

        $resolved = realpath(trim($validated['folder_path']));
        if ($resolved === false || ! is_dir($resolved)) {
            return back()->withInput()->withErrors([
                'folder_path' => 'That path must be an existing folder on this machine.',
            ]);
        }

        $title = trim($validated['title'] ?? '');
        if ($title === '') {
            $title = basename($resolved) ?: 'Untitled course';
        }

        $course = Course::create([
            'user_id' => $request->user()->id,
            'title' => $title,
            'folder_path' => $resolved,
            'archived_at' => null,
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
        $current = $course->currentVideo();
        $nextAfterCurrent = null;
        if ($current !== null) {
            $idx = $videos->search(fn ($v) => $v->is($current));
            $nextAfterCurrent = ($idx !== false && $idx < $videos->count() - 1)
                ? $videos[$idx + 1]
                : null;
        }

        $previewStreamUrl = null;
        $showCoursePreview = false;
        if ($videos->isNotEmpty() && ! $course->hasBeenStarted()) {
            if ($course->previewAbsolutePath() !== null) {
                $previewStreamUrl = route('courses.preview.stream', $course);
                $showCoursePreview = true;
            } else {
                $firstLesson = $videos->first();
                if ($firstLesson !== null && $firstLesson->absoluteFilePath() !== null) {
                    $previewStreamUrl = route('videos.stream', $firstLesson);
                    $showCoursePreview = true;
                }
            }
        }

        return view('courses.show', [
            'course' => $course,
            'currentVideo' => $current,
            'nextVideo' => $nextAfterCurrent,
            'showCoursePreview' => $showCoursePreview,
            'previewStreamUrl' => $previewStreamUrl,
        ]);
    }

    public function previewStream(Course $course): BinaryFileResponse
    {
        $path = $course->previewAbsolutePath();

        if ($path === null || ! is_file($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function reorderVideos(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate([
            'video_ids' => ['required', 'array', 'min:1'],
            'video_ids.*' => ['integer', 'distinct'],
        ]);

        $expected = $course->videos()->pluck('id')->sort()->values()->all();
        $incoming = collect($data['video_ids'])->unique()->sort()->values()->all();

        if ($expected !== $incoming) {
            abort(422, 'Lesson list must include every video in this course exactly once.');
        }

        DB::transaction(function () use ($course, $data): void {
            foreach ($data['video_ids'] as $i => $videoId) {
                Video::query()
                    ->where('course_id', $course->id)
                    ->whereKey($videoId)
                    ->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json(['ok' => true]);
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

    public function archive(Course $course): RedirectResponse
    {
        if ($course->archived_at !== null) {
            return back()->with('status', 'Course is already archived.');
        }

        $course->forceFill([
            'archived_at' => Carbon::now(),
        ])->save();

        return redirect()->route('courses.index')->with('status', 'Course archived.');
    }

    public function restore(Course $course): RedirectResponse
    {
        if ($course->archived_at === null) {
            return back()->with('status', 'Course is already active.');
        }

        $course->forceFill([
            'archived_at' => null,
        ])->save();

        return redirect()->route('courses.show', $course)->with('status', 'Course restored.');
    }

    public function resetProgress(Course $course): RedirectResponse
    {
        $videoIds = $course->videos()->pluck('id');

        if ($videoIds->isNotEmpty()) {
            DB::table('video_progress')
                ->whereIn('video_id', $videoIds)
                ->delete();
        }

        return back()->with('status', 'Course progress reset.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $title = $course->title;
        $course->delete();

        return redirect()->route('courses.index')->with('status', 'Deleted course: '.$title.'.');
    }
}
