<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\DB;

class CourseVideoScanner
{
    /** @var list<string> */
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mkv', 'mov', 'm4v', 'ogv', 'avi'];

    /**
     * Index video files under the course folder. Files stay on disk; only DB rows are created/updated.
     *
     * @return int Number of lessons after sync
     */
    public function sync(Course $course): int
    {
        $root = $course->folderRootReal();
        if ($root === null) {
            return 0;
        }

        $relativePaths = $this->discoverRelativePaths($root);

        DB::transaction(function () use ($course, $relativePaths): void {
            $existing = $course->videos()->get()->keyBy(fn ($v) => $this->normalizeKey($v->file_path));
            $keepIds = [];
            $nextOrder = (int) ($course->videos()->max('sort_order') ?? 0);

            foreach ($relativePaths as $relPath) {
                $title = $this->titleFromRelativePath($relPath);
                $key = $this->normalizeKey($relPath);

                if ($existing->has($key)) {
                    $video = $existing->get($key);
                    $video->update([
                        'title' => $title,
                    ]);
                    $keepIds[] = $video->id;
                } else {
                    $nextOrder++;
                    $video = $course->videos()->create([
                        'title' => $title,
                        'file_path' => $relPath,
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
     * @return list<string> Paths relative to root, using forward slashes
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

            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (! in_array($ext, self::VIDEO_EXTENSIONS, true)) {
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

            $paths[] = $relative;
        }

        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
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
