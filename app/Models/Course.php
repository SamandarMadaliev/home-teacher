<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'title',
        'folder_path',
    ];

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class)->orderBy('sort_order');
    }

    /**
     * Average lesson completion for this course (0–100). Uses each lesson's VideoProgress.
     */
    public function aggregateProgressPercent(): int
    {
        $videos = $this->videos;
        if ($videos->isEmpty()) {
            return 0;
        }

        $sum = 0;
        foreach ($videos as $video) {
            $sum += $video->progress ? $video->progress->progressPercent() : 0;
        }

        return (int) round($sum / $videos->count());
    }

    /**
     * Canonical absolute directory for this course's media files.
     */
    public function folderRootReal(): ?string
    {
        if ($this->folder_path === null || $this->folder_path === '') {
            $fallback = rtrim((string) config('course.videos_path'), DIRECTORY_SEPARATOR);
            if ($fallback === '') {
                return null;
            }

            $real = realpath($fallback);

            return ($real !== false && is_dir($real)) ? $real : null;
        }

        $trimmed = rtrim($this->folder_path, DIRECTORY_SEPARATOR.'\\/');
        $real = realpath($trimmed);

        return ($real !== false && is_dir($real)) ? $real : null;
    }
}
