<?php

namespace App\Services;

use App\Models\Video;
use App\Models\VideoProgress;

class VideoProgressService
{
    private const COMPLETED_THRESHOLD = 0.9;

    public function sync(Video $video, float $currentTime, ?float $duration): VideoProgress
    {
        $currentTime = max(0, $currentTime);
        $completed = $this->shouldMarkCompleted($currentTime, $duration);

        $payload = [
            'last_position' => $currentTime,
            'completed' => $completed,
        ];

        if ($duration !== null && $duration > 0) {
            $payload['duration_seconds'] = $duration;
        }

        /** @var VideoProgress $row */
        $row = VideoProgress::query()->updateOrCreate(
            ['video_id' => $video->getKey()],
            $payload
        );

        return $row->fresh() ?? $row;
    }

    private function shouldMarkCompleted(float $currentTime, ?float $duration): bool
    {
        if ($duration === null || $duration <= 0) {
            return false;
        }

        return ($currentTime / $duration) >= self::COMPLETED_THRESHOLD;
    }
}
