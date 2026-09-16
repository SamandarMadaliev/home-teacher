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

    /**
     * Set completion for a lesson with no play position (PDF / HTML). There is no
     * duration/current_time to speak of, so this is a plain on/off toggle.
     */
    public function setManualCompletion(Video $video, bool $completed): VideoProgress
    {
        /** @var VideoProgress $row */
        $row = VideoProgress::query()->updateOrCreate(
            ['video_id' => $video->getKey()],
            ['completed' => $completed]
        );

        return $row->fresh() ?? $row;
    }
}
