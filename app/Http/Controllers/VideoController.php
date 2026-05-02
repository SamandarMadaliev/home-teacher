<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\VideoProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoController extends Controller
{
    public function __construct(
        private VideoProgressService $progressService
    ) {}

    public function show(Video $video): View
    {
        $video->load(['course', 'progress', 'notes']);

        $courseVideos = $video->course->videos()->with('progress')->orderBy('sort_order')->get();

        $idx = $courseVideos->search(fn ($v) => $v->id === $video->id);
        $previousVideo = ($idx !== false && $idx > 0) ? $courseVideos[$idx - 1] : null;
        $nextVideo = ($idx !== false && $idx < $courseVideos->count() - 1)
            ? $courseVideos[$idx + 1]
            : null;

        $initialPosition = 0.0;
        if ($video->progress && ! $video->progress->completed) {
            $initialPosition = (float) $video->progress->last_position;
        }

        return view('videos.show', [
            'video' => $video,
            'courseVideos' => $courseVideos,
            'previousVideo' => $previousVideo,
            'nextVideo' => $nextVideo,
            'initialPosition' => $initialPosition,
        ]);
    }

    public function stream(Video $video): BinaryFileResponse
    {
        $path = $video->absoluteFilePath();

        if ($path === null || ! is_file($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function progress(Request $request, Video $video): JsonResponse
    {
        $data = $request->validate([
            'current_time' => ['required', 'numeric', 'min:0'],
            'duration' => ['nullable', 'numeric', 'min:0'],
        ]);

        $duration = isset($data['duration']) ? (float) $data['duration'] : null;

        $row = $this->progressService->sync(
            $video,
            (float) $data['current_time'],
            $duration
        );

        return response()->json([
            'last_position' => $row->last_position,
            'completed' => $row->completed,
        ]);
    }
}
