<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\VideoAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoAttachmentController extends Controller
{
    public function store(Request $request, Video $video): RedirectResponse
    {
        $kind = $request->input('kind');

        if ($kind === VideoAttachment::KIND_LINK) {
            return $this->storeLink($request, $video);
        }

        if ($kind === VideoAttachment::KIND_FILE) {
            return $this->storeFile($request, $video);
        }

        return back()
            ->withInput()
            ->withErrors(['kind' => 'Pick whether to attach a file or a link.']);
    }

    public function destroy(Video $video, VideoAttachment $attachment): RedirectResponse
    {
        if ($attachment->video_id !== $video->id) {
            abort(404);
        }

        $attachment->delete();

        return back()->with('status', 'Attachment removed.');
    }

    public function download(Video $video, VideoAttachment $attachment): BinaryFileResponse
    {
        if ($attachment->video_id !== $video->id || ! $attachment->isFile()) {
            abort(404);
        }

        $absolute = $attachment->absoluteFilePath();

        if ($absolute === null) {
            abort(404);
        }

        $headers = $attachment->mime_type ? ['Content-Type' => $attachment->mime_type] : [];

        return response()->file($absolute, $headers);
    }

    private function storeLink(Request $request, Video $video): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048', 'url'],
        ]);

        $url = trim($data['url']);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = $this->deriveLinkTitle($url);
        }

        $video->attachments()->create([
            'kind' => VideoAttachment::KIND_LINK,
            'title' => $title,
            'url' => $url,
            'sort_order' => $this->nextSortOrder($video),
        ]);

        return back()->with('status', 'Link attached.');
    }

    private function storeFile(Request $request, Video $video): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file_path' => ['required', 'string', 'max:4096'],
        ]);

        $rawPath = trim($data['file_path']);

        $absolute = $this->resolveAttachmentPath($video, $rawPath);

        if ($absolute === null) {
            return back()
                ->withInput()
                ->withErrors(['file_path' => 'That path does not point to a readable file on this machine.']);
        }

        $stored = $this->normalizeStoredPath($video, $rawPath, $absolute);

        $basename = basename($absolute);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = $basename;
        }

        $video->attachments()->create([
            'kind' => VideoAttachment::KIND_FILE,
            'title' => $title,
            'file_path' => $stored,
            'mime_type' => $this->detectMime($absolute),
            'size_bytes' => @filesize($absolute) ?: null,
            'sort_order' => $this->nextSortOrder($video),
        ]);

        return back()->with('status', 'File attached.');
    }

    private function nextSortOrder(Video $video): int
    {
        return (int) $video->attachments()->max('sort_order') + 1;
    }

    private function deriveLinkTitle(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $url;
    }

    /**
     * Resolve a user-supplied path (absolute, ~/-prefixed, or relative to the course folder)
     * to a canonical absolute file path on disk. Returns null if it isn't a readable file.
     */
    private function resolveAttachmentPath(Video $video, string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        $expanded = $this->expandHome($raw);

        if ($this->looksAbsolute($expanded)) {
            $real = realpath($expanded);

            return ($real !== false && is_file($real)) ? $real : null;
        }

        $video->loadMissing('course');
        $root = $video->course?->folderRootReal();
        if ($root === null) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $expanded), '/');
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '..' || $segment === '.') {
                return null;
            }
        }

        $real = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($real === false || ! is_file($real)) {
            return null;
        }

        $prefix = realpath($root).DIRECTORY_SEPARATOR;

        return str_starts_with($real, $prefix) ? $real : null;
    }

    /**
     * Decide what to persist in `file_path`. If the user gave a path inside the course folder,
     * store the *relative* path (so the course can be moved); otherwise store the absolute path.
     */
    private function normalizeStoredPath(Video $video, string $raw, string $absolute): string
    {
        $expanded = $this->expandHome($raw);

        if (! $this->looksAbsolute($expanded)) {
            return ltrim(str_replace('\\', '/', $expanded), '/');
        }

        $video->loadMissing('course');
        $root = $video->course?->folderRootReal();
        if ($root === null) {
            return $absolute;
        }

        $prefix = $root.DIRECTORY_SEPARATOR;
        if (str_starts_with($absolute, $prefix)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($absolute, strlen($prefix)));
        }

        return $absolute;
    }

    private function expandHome(string $path): string
    {
        if ($path === '' || $path[0] !== '~') {
            return $path;
        }

        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: null;
        if (! is_string($home) || $home === '') {
            return $path;
        }

        return $home.substr($path, 1);
    }

    private function looksAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    private function detectMime(string $absolute): ?string
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($absolute);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        return null;
    }
}
