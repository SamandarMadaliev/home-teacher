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
        $courseId = DB::table('video_progress')
            ->join('videos', 'videos.id', '=', 'video_progress.video_id')
            ->orderByDesc('video_progress.updated_at')
            ->value('videos.course_id');

        $lastWatchedCourse = $courseId !== null
            ? Course::query()
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
            ->withCount('courses')
            ->orderByDesc('updated_at')
            ->orderBy('title')
            ->limit(9)
            ->get();

        $courses = Course::query()
            ->with(['videos.progress'])
            ->withCount('videos')
            ->orderedForLibrary()
            ->get();

        return view('home', [
            'lastWatchedCourse' => $lastWatchedCourse,
            'continueUrl' => $continueUrl,
            'continueVideo' => $continueVideo,
            'roadmaps' => $roadmaps,
            'courses' => $courses,
        ]);
    }
}
