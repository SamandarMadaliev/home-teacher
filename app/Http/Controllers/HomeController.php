<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Roadmap;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        if (! auth()->check()) {
            return view('home', [
                'guest' => true,
            ]);
        }

        $userId = auth()->id();

        $courseId = DB::table('video_progress')
            ->join('videos', 'videos.id', '=', 'video_progress.video_id')
            ->join('courses', 'courses.id', '=', 'videos.course_id')
            ->where('courses.user_id', $userId)
            ->orderByDesc('video_progress.updated_at')
            ->value('videos.course_id');

        $lastWatchedCourse = $courseId !== null
            ? Course::query()
                ->forCurrentUser()
                ->active()
                ->with(['videos.progress'])
                ->withCount('videos')
                ->find($courseId)
            : null;

        $continueVideo = $lastWatchedCourse?->currentVideo();
        $continueUrl = $lastWatchedCourse !== null
            ? ($continueVideo !== null
                ? route('videos.show', $continueVideo)
                : route('courses.show', $lastWatchedCourse))
            : null;

        $roadmaps = Roadmap::query()
            ->forCurrentUser()
            ->withCount('courses')
            ->orderByDesc('updated_at')
            ->orderBy('title')
            ->limit(9)
            ->get();

        $coursesQuery = Course::query()
            ->forCurrentUser()
            ->active()
            ->with(['videos.progress'])
            ->withCount('videos')
            ->orderedForLibrary();

        $coursesTotalCount = (clone $coursesQuery)->count();

        $courses = $coursesQuery->limit(3)->get();

        return view('home', [
            'guest' => false,
            'lastWatchedCourse' => $lastWatchedCourse,
            'continueUrl' => $continueUrl,
            'continueVideo' => $continueVideo,
            'roadmaps' => $roadmaps,
            'courses' => $courses,
            'coursesTotalCount' => $coursesTotalCount,
        ]);
    }
}
