<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\VideoNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VideoNoteController extends Controller
{
    public function store(Request $request, Video $video): RedirectResponse
    {
        if ($request->input('timestamp_seconds') === '') {
            $request->merge(['timestamp_seconds' => null]);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'timestamp_seconds' => ['nullable', 'numeric', 'min:0'],
        ]);

        $video->notes()->create([
            'body' => $data['body'],
            'timestamp_seconds' => isset($data['timestamp_seconds']) ? (float) $data['timestamp_seconds'] : null,
        ]);

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
