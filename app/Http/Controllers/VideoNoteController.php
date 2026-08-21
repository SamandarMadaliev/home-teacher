<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\VideoNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class VideoNoteController extends Controller
{
    public function preview(Request $request, Video $video): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:10000'],
        ]);

        $html = VideoNote::markdownToHtml($data['body'] ?? '');

        return response()->json([
            'html' => $html,
        ]);
    }

    public function store(Request $request, Video $video): JsonResponse|RedirectResponse
    {
        if ($request->input('timestamp_seconds') === '' || $request->input('timestamp_seconds') === null) {
            $request->merge(['timestamp_seconds' => null]);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'timestamp_seconds' => ['nullable', 'numeric', 'min:0'],
        ]);

        $note = $video->notes()->create([
            'body' => $data['body'],
            'timestamp_seconds' => isset($data['timestamp_seconds']) ? (float) $data['timestamp_seconds'] : null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Note saved.',
                'note' => [
                    'id' => $note->id,
                    'html' => view('videos.partials.note-list-item', [
                        'video' => $video,
                        'note' => $note,
                    ])->render(),
                ],
            ]);
        }

        return back()->with('status', 'Note saved.');
    }

    public function destroy(Video $video, VideoNote $note): RedirectResponse
    {
        if ($note->video_id !== $video->id) {
            abort(404);
        }

        $note->delete();

        return back()->with('status', 'Note removed.');
    }
}
