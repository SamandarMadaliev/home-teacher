<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Course extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'title',
        'folder_path',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Course $course): void {
            // New courses should always start active unless explicitly archived.
            if (! $course->isDirty('archived_at')) {
                $course->archived_at = null;
            }
        });
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class)->orderBy('sort_order');
    }

    public function roadmaps(): BelongsToMany
    {
        return $this->belongsToMany(Roadmap::class, 'roadmap_course')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * Courses list ordering: recently watched first, then never-watched by title.
     */
    public function scopeOrderedForLibrary(Builder $query): Builder
    {
        $lastWatch = DB::table('videos')
            ->join('video_progress', 'videos.id', '=', 'video_progress.video_id')
            ->selectRaw('videos.course_id, max(video_progress.updated_at) as last_watch_at')
            ->groupBy('videos.course_id');

        return $query
            ->leftJoinSub($lastWatch, 'lw', 'lw.course_id', '=', 'courses.id')
            ->select('courses.*')
            ->orderByRaw('CASE WHEN lw.last_watch_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('lw.last_watch_at')
            ->orderBy('courses.title');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * First lesson not marked completed; otherwise last lesson (single-user flow).
     */
    public function currentVideo(): ?Video
    {
        $this->loadMissing(['videos.progress']);
        $videos = $this->videos;
        if ($videos->isEmpty()) {
            return null;
        }

        foreach ($videos as $video) {
            if (! $video->progress?->completed) {
                return $video;
            }
        }

        return $videos->last();
    }

    /**
     * True when the course has at least one lesson and every lesson is marked completed.
     */
    public function isFullyCompleted(): bool
    {
        $this->loadMissing(['videos.progress']);
        $videos = $this->videos;

        if ($videos->isEmpty()) {
            return false;
        }

        foreach ($videos as $video) {
            if (! $video->progress?->completed) {
                return false;
            }
        }

        return true;
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
