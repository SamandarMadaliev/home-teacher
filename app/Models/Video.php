<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Video extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'file_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function progress(): HasOne
    {
        return $this->hasOne(VideoProgress::class);
    }

    public function absoluteFilePath(): ?string
    {
        $this->loadMissing('course');
        $root = $this->course->folderRootReal();
        if ($root === null) {
            return null;
        }

        $relative = str_replace('\\', '/', $this->file_path);
        $relative = ltrim($relative, '/');
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '..' || $segment === '.') {
                return null;
            }
        }

        $full = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $real = realpath($full);
        $rootReal = realpath($root);

        if ($real === false || $rootReal === false || ! is_file($real)) {
            return null;
        }

        $prefix = $rootReal.DIRECTORY_SEPARATOR;

        return str_starts_with($real, $prefix) ? $real : null;
    }
}
