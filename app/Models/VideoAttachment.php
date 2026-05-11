<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoAttachment extends Model
{
    public const KIND_FILE = 'file';
    public const KIND_LINK = 'link';

    protected $fillable = [
        'video_id',
        'kind',
        'title',
        'url',
        'file_path',
        'mime_type',
        'size_bytes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function isFile(): bool
    {
        return $this->kind === self::KIND_FILE;
    }

    public function isLink(): bool
    {
        return $this->kind === self::KIND_LINK;
    }

    /**
     * Resolve this attachment's `file_path` to a real, readable file on disk.
     *
     * Accepts either an absolute path, or a path relative to the parent course's
     * `folder_path`. Returns `null` if the file is missing / unreadable / not a regular file.
     * Like {@see Video::absoluteFilePath()}, this is a guard against bad path values
     * (traversal, broken courses, deleted files).
     */
    public function absoluteFilePath(): ?string
    {
        if (! $this->isFile() || ! $this->file_path) {
            return null;
        }

        $raw = trim((string) $this->file_path);
        if ($raw === '') {
            return null;
        }

        if (self::looksAbsolute($raw)) {
            $real = realpath($raw);

            return ($real !== false && is_file($real)) ? $real : null;
        }

        $this->loadMissing('video.course');
        $root = $this->video?->course?->folderRootReal();
        if ($root === null) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $raw), '/');
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
     * True if the stored `file_path` is currently resolvable to a real file on disk.
     */
    public function isAvailable(): bool
    {
        return $this->absoluteFilePath() !== null;
    }

    /**
     * Best-effort display name for a file attachment ("My slides.pdf").
     */
    public function fileBasename(): ?string
    {
        if (! $this->isFile() || ! $this->file_path) {
            return null;
        }

        return basename(str_replace('\\', '/', $this->file_path));
    }

    /**
     * Human-readable size for stored files (e.g. "2.4 MB"). Returns null for links / unknown.
     */
    public function sizeLabel(): ?string
    {
        if (! $this->isFile() || $this->size_bytes === null) {
            return null;
        }

        $bytes = (int) $this->size_bytes;
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024) {
                return number_format($value, $value < 10 ? 1 : 0).' '.$unit;
            }
            $value /= 1024;
        }

        return number_format($value, 1).' PB';
    }

    /**
     * Host (e.g. "github.com") for link attachments; null for files or invalid URLs.
     */
    public function linkHost(): ?string
    {
        if (! $this->isLink() || ! $this->url) {
            return null;
        }

        $host = parse_url($this->url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    private static function looksAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }
}
