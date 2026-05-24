<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Video;
use App\Models\VideoNote;
use App\Services\ProfileAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileAnalyticsService $analytics,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('profile.account');
    }

    public function account(Request $request): View
    {
        $user = $request->user();

        $coursesCount = Course::query()->forCurrentUser()->count();

        $notesCount = VideoNote::query()
            ->whereHas('video.course', fn ($q) => $q->forCurrentUser())
            ->count();

        return view('profile.account', [
            'activeTab' => 'account',
            'user' => $user,
            'coursesCount' => $coursesCount,
            'notesCount' => $notesCount,
        ]);
    }

    public function analytics(Request $request): View
    {
        $stats = $this->analytics->forUser($request->user());

        return view('profile.analytics', [
            'activeTab' => 'analytics',
            'stats' => $stats,
        ]);
    }

    public function notes(Request $request): View
    {
        $courses = Course::query()
            ->forCurrentUser()
            ->orderBy('title')
            ->get(['id', 'title']);

        $courseId = $request->filled('course') ? (int) $request->input('course') : null;
        $videoId = $request->filled('video') ? (int) $request->input('video') : null;

        if ($courseId !== null && ! $courses->contains('id', $courseId)) {
            $courseId = null;
            $videoId = null;
        }

        $videos = collect();
        if ($courseId !== null) {
            $videos = Video::query()
                ->where('course_id', $courseId)
                ->orderBy('sort_order')
                ->get(['id', 'title', 'course_id']);
        }

        if ($videoId !== null) {
            $videoBelongs = $videos->contains('id', $videoId)
                || Video::query()
                    ->whereKey($videoId)
                    ->whereHas('course', fn ($q) => $q->forCurrentUser())
                    ->exists();

            if (! $videoBelongs) {
                $videoId = null;
            } elseif ($courseId === null) {
                $courseId = (int) Video::query()->whereKey($videoId)->value('course_id');
                $videos = Video::query()
                    ->where('course_id', $courseId)
                    ->orderBy('sort_order')
                    ->get(['id', 'title', 'course_id']);
            }
        }

        $notes = VideoNote::query()
            ->whereHas('video.course', fn ($q) => $q->forCurrentUser())
            ->when($courseId !== null, function ($query) use ($courseId): void {
                $query->whereHas('video', fn ($v) => $v->where('course_id', $courseId));
            })
            ->when($videoId !== null, fn ($query) => $query->where('video_id', $videoId))
            ->with(['video.course'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('profile.notes', [
            'activeTab' => 'notes',
            'courses' => $courses,
            'videos' => $videos,
            'notes' => $notes,
            'selectedCourseId' => $courseId,
            'selectedVideoId' => $videoId,
        ]);
    }
}
