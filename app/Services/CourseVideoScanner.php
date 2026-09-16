<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Video;
use Illuminate\Support\Facades\DB;

class CourseVideoScanner
{
    /** @var array<string, list<string>> Extension => lesson type, checked in this order */
    private const TYPE_EXTENSIONS = [
        Video::TYPE_VIDEO => ['mp4', 'webm', 'mkv', 'mov', 'm4v', 'ogv', 'avi'],
        Video::TYPE_PDF => ['pdf'],
        Video::TYPE_HTML => ['html', 'htm'],
    ];

    /**
     * Index video/PDF/HTML files under the course folder. Files stay on disk; only DB rows
     * are created/updated.
     *
     * @return int Number of lessons after sync
     */
    public function sync(Course $course): int
    {
        $root = $course->folderRootReal();
        if ($root === null) {
            return 0;
        }

        $discovered = $this->discoverRelativePaths($root);

        DB::transaction(function () use ($course, $discovered): void {
            $existing = $course->videos()->get()->keyBy(fn ($v) => $this->normalizeKey($v->file_path));
            $keepIds = [];
            $nextOrder = (int) ($course->videos()->max('sort_order') ?? 0);

            foreach ($discovered as $relPath => $type) {
                $title = $this->titleFromRelativePath($relPath);
                $key = $this->normalizeKey($relPath);

                if ($existing->has($key)) {
                    $keepIds[] = $existing->get($key)->id;
                } else {
                    $nextOrder++;
                    $video = $course->videos()->create([
                        'title' => $title,
                        'file_path' => $relPath,
                        'type' => $type,
                        'sort_order' => $nextOrder,
                    ]);
                    $keepIds[] = $video->id;
                }
            }

            if ($keepIds === []) {
                $course->videos()->delete();
            } else {
                $course->videos()->whereNotIn('id', $keepIds)->delete();
            }
        });

        return $course->videos()->count();
    }

    /**
     * @return array<string, string> Paths relative to root (forward slashes) => lesson type
     */
    private function discoverRelativePaths(string $absoluteRoot): array
    {
        $absoluteRoot = rtrim($absoluteRoot, DIRECTORY_SEPARATOR);
        $prefix = $absoluteRoot.DIRECTORY_SEPARATOR;
        $paths = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $type = $this->typeForExtension($ext);
            if ($type === null) {
                continue;
            }

            // "preview.{ext}" at any depth is reserved for the course's auto-preview clip
            // (Course::previewAbsolutePath()) and only applies to actual video files.
            if ($type === Video::TYPE_VIDEO && strtolower(pathinfo($filename, PATHINFO_FILENAME)) === 'preview') {
                continue;
            }

            $fullPath = $file->getRealPath();
            if ($fullPath === false) {
                continue;
            }

            if (! str_starts_with($fullPath, $prefix)) {
                continue;
            }

            $relative = substr($fullPath, strlen($prefix));
            $relative = str_replace('\\', '/', $relative);

            if ($relative === '') {
                continue;
            }

            $paths[$relative] = $type;
        }

        uksort($paths, fn ($a, $b) => strnatcasecmp($a, $b));

        return $paths;
    }

    private function typeForExtension(string $ext): ?string
    {
        foreach (self::TYPE_EXTENSIONS as $type => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $type;
            }
        }

        return null;
    }

    private function normalizeKey(string $filePath): string
    {
        return strtolower(str_replace('\\', '/', $filePath));
    }

    private function titleFromRelativePath(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        $base = basename($relative);
        $name = pathinfo($base, PATHINFO_FILENAME);
        $dir = dirname($relative);

        if ($dir === '.' || $dir === '') {
            return $name;
        }

        return str_replace('/', ' / ', $dir).' / '.$name;
    }
}
