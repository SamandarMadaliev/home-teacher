<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoProgress extends Model
{
    protected $table = 'video_progress';

    protected $fillable = [
        'video_id',
        'last_position',
        'duration_seconds',
        'completed',
    ];

    protected function casts(): array
    {
        return [
            'last_position' => 'float',
            'duration_seconds' => 'float',
            'completed' => 'boolean',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function progressRatio(): float
    {
        if ($this->completed) {
            return 1.0;
        }

        $duration = $this->duration_seconds ?? 0;

        if ($duration <= 0) {
            return 0.0;
        }

        return min(1.0, max(0.0, $this->last_position / $duration));
    }

    public function progressPercent(): int
    {
        return (int) round($this->progressRatio() * 100);
    }
}
