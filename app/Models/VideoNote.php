<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoNote extends Model
{
    protected $fillable = [
        'video_id',
        'body',
        'timestamp_seconds',
    ];

    protected function casts(): array
    {
        return [
            'timestamp_seconds' => 'float',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Human-readable timestamp for UI (e.g. "3:24" or "1:05:02").
     */
    public function timestampLabel(): ?string
    {
        if ($this->timestamp_seconds === null) {
            return null;
        }

        $t = (int) floor((float) $this->timestamp_seconds);
        $h = intdiv($t, 3600);
        $m = intdiv($t % 3600, 60);
        $s = $t % 60;

        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }

        return sprintf('%d:%02d', $m, $s);
    }
}
