<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoNote;
use App\Models\VideoProgress;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfileAnalyticsService
{
    private const MONTHLY_CHART_MONTHS = 12;

    private const MONTHLY_CHART_MAX_MONTHS = 24;

    /**
     * @return array{
     *     summary: array{
     *         total_lessons: int,
     *         lessons_started: int,
     *         lessons_completed: int,
     *         lessons_not_started: int,
     *         overall_percent: int,
     *         courses_count: int,
     *         active_courses_count: int,
     *         notes_count: int,
     *         watch_time_seconds: int,
     *         watch_time_hours: float,
     *         watch_time_label: string,
     *     },
     *     activity: array{
     *         first_watch_at: ?Carbon,
     *         last_watch_at: ?Carbon,
     *         most_active_month: ?array{key: string, label: string, lessons: int},
     *         monthly: list<array{key: string, label: string, lessons: int, bar_percent: int}>,
     *     },
     *     courses: list<array{title: string, lessons: int, completed: int, percent: int}>,
     * }
     */
    public function forUser(User $user): array
    {
        $coursesCount = Course::query()->forUser($user)->count();
        $activeCoursesCount = Course::query()->forUser($user)->active()->count();

        $notesCount = VideoNote::query()
            ->whereHas('video.course', fn ($q) => $q->forUser($user))
            ->count();

        $totalLessons = Video::query()
            ->whereHas('course', fn ($q) => $q->forUser($user))
            ->count();

        $progressBase = VideoProgress::query()
            ->join('videos', 'videos.id', '=', 'video_progress.video_id')
            ->join('courses', 'courses.id', '=', 'videos.course_id')
            ->where('courses.user_id', $user->id);

        $lessonsStarted = (clone $progressBase)
            ->where(function ($query): void {
                $query->where('video_progress.completed', true)
                    ->orWhere('video_progress.last_position', '>', 0);
            })
            ->count();

        $lessonsCompleted = (clone $progressBase)
            ->where('video_progress.completed', true)
            ->count();

        $overallPercent = $this->overallProgressPercent($user->id);
        $watchTime = $this->watchTimeSummary($user->id);

        $firstWatchAt = $this->toCarbon((clone $progressBase)->min('video_progress.created_at'));
        $lastWatchAt = $this->toCarbon((clone $progressBase)->max('video_progress.updated_at'));

        $monthlyCounts = $this->monthlyLessonActivity($user->id);
        $monthlyChart = $this->buildMonthlyChart($monthlyCounts);
        $mostActiveMonth = $this->mostActiveMonth($monthlyCounts);

        return [
            'summary' => [
                'total_lessons' => $totalLessons,
                'lessons_started' => $lessonsStarted,
                'lessons_completed' => $lessonsCompleted,
                'lessons_not_started' => max(0, $totalLessons - $lessonsStarted),
                'overall_percent' => $overallPercent,
                'courses_count' => $coursesCount,
                'active_courses_count' => $activeCoursesCount,
                'notes_count' => $notesCount,
                'watch_time_seconds' => $watchTime['seconds'],
                'watch_time_hours' => $watchTime['hours'],
                'watch_time_label' => $watchTime['label'],
            ],
            'activity' => [
                'first_watch_at' => $firstWatchAt,
                'last_watch_at' => $lastWatchAt,
                'most_active_month' => $mostActiveMonth,
                'monthly' => $monthlyChart,
            ],
            'courses' => $this->courseBreakdown($user->id),
        ];
    }

    /**
     * Estimated watch time from saved playhead position per lesson (not cumulative rewatches).
     *
     * @return array{seconds: int, hours: float, label: string}
     */
    private function watchTimeSummary(int $userId): array
    {
        $row = VideoProgress::query()
            ->join('videos', 'videos.id', '=', 'video_progress.video_id')
            ->join('courses', 'courses.id', '=', 'videos.course_id')
            ->where('courses.user_id', $userId)
            ->selectRaw(
                'SUM(CASE
                    WHEN video_progress.completed = 1 AND video_progress.duration_seconds > 0
                        THEN video_progress.duration_seconds
                    WHEN video_progress.duration_seconds > 0
                        THEN MIN(video_progress.last_position, video_progress.duration_seconds)
                    ELSE video_progress.last_position
                END) as total_seconds'
            )
            ->first();

        $seconds = (int) round((float) ($row->total_seconds ?? 0));

        return [
            'seconds' => $seconds,
            ...$this->formatWatchDuration($seconds),
        ];
    }

    /**
     * @return array{hours: float, label: string}
     */
    private function formatWatchDuration(int $seconds): array
    {
        if ($seconds <= 0) {
            return ['hours' => 0.0, 'label' => '0 hours'];
        }

        if ($seconds >= 3600) {
            $hours = round($seconds / 3600, 1);
            $label = $hours == 1.0 ? '1 hour' : "{$hours} hours";

            return ['hours' => $hours, 'label' => $label];
        }

        $minutes = max(1, (int) round($seconds / 60));
        $label = $minutes === 1 ? '1 minute' : "{$minutes} minutes";

        return ['hours' => round($seconds / 3600, 2), 'label' => $label];
    }

    private function overallProgressPercent(int $userId): int
    {
        $row = Video::query()
            ->whereHas('course', fn ($q) => $q->forUser($userId))
            ->leftJoin('video_progress', 'videos.id', '=', 'video_progress.video_id')
            ->selectRaw(
                'ROUND(AVG(CASE
                    WHEN video_progress.completed = 1 THEN 100
                    WHEN video_progress.duration_seconds > 0 THEN MIN(100, (video_progress.last_position / video_progress.duration_seconds) * 100)
                    ELSE 0
                END)) as avg_percent'
            )
            ->first();

        return (int) ($row->avg_percent ?? 0);
    }

    /**
     * Lessons with progress activity in each calendar month (by last update).
     *
     * @return Collection<string, int> month key YYYY-MM => count
     */
    private function monthlyLessonActivity(int $userId): Collection
    {
        $monthExpr = $this->monthExpression('video_progress.updated_at');

        $rows = VideoProgress::query()
            ->join('videos', 'videos.id', '=', 'video_progress.video_id')
            ->join('courses', 'courses.id', '=', 'videos.course_id')
            ->where('courses.user_id', $userId)
            ->whereNotNull('video_progress.updated_at')
            ->selectRaw("{$monthExpr} as month_key, count(*) as lesson_count")
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [(string) $row->month_key => (int) $row->lesson_count]);
    }

    /**
     * @param  Collection<string, int>  $monthlyCounts
     * @return list<array{key: string, label: string, lessons: int, bar_percent: int}>
     */
    private function buildMonthlyChart(Collection $monthlyCounts): array
    {
        $end = now()->startOfMonth();
        $start = $end->copy()->subMonths(self::MONTHLY_CHART_MONTHS - 1);

        if ($monthlyCounts->isNotEmpty()) {
            $earliestKey = $monthlyCounts->keys()->sort()->first();
            $earliest = Carbon::createFromFormat('Y-m', (string) $earliestKey)->startOfMonth();

            if ($earliest->lt($start)) {
                $start = $earliest;
            }
        }

        while ($start->diffInMonths($end) >= self::MONTHLY_CHART_MAX_MONTHS) {
            $start = $start->copy()->addMonth();
        }

        $peak = 0;
        $months = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $lessons = (int) ($monthlyCounts[$key] ?? 0);
            $peak = max($peak, $lessons);
            $months[] = [
                'key' => $key,
                'label' => $cursor->format('M Y'),
                'lessons' => $lessons,
                'bar_percent' => 0,
            ];
            $cursor = $cursor->copy()->addMonth();
        }

        if ($peak > 0) {
            foreach ($months as $index => $month) {
                $months[$index]['bar_percent'] = (int) round(($month['lessons'] / $peak) * 100);
            }
        }

        return $months;
    }

    /**
     * @param  Collection<string, int>  $monthlyCounts
     * @return ?array{key: string, label: string, lessons: int}
     */
    private function mostActiveMonth(Collection $monthlyCounts): ?array
    {
        if ($monthlyCounts->isEmpty()) {
            return null;
        }

        $key = $monthlyCounts->sortDesc()->keys()->first();
        $lessons = (int) $monthlyCounts[$key];

        if ($lessons <= 0) {
            return null;
        }

        $label = Carbon::createFromFormat('Y-m', $key)->format('F Y');

        return [
            'key' => $key,
            'label' => $label,
            'lessons' => $lessons,
        ];
    }

    /**
     * @return list<array{title: string, lessons: int, completed: int, percent: int}>
     */
    private function courseBreakdown(int $userId): array
    {
        $courses = Course::query()
            ->forUser($userId)
            ->withCount('videos')
            ->with(['videos.progress'])
            ->orderBy('title')
            ->get();

        return $courses
            ->filter(fn (Course $course) => $course->videos_count > 0)
            ->map(function (Course $course): array {
                $completed = 0;
                $sumPercent = 0;

                foreach ($course->videos as $video) {
                    if ($video->progress?->completed) {
                        $completed++;
                    }
                    $sumPercent += $video->progress ? $video->progress->progressPercent() : 0;
                }

                $lessons = $course->videos_count;

                return [
                    'title' => $course->title,
                    'lessons' => $lessons,
                    'completed' => $completed,
                    'percent' => $lessons > 0 ? (int) round($sumPercent / $lessons) : 0,
                ];
            })
            ->sortByDesc('percent')
            ->values()
            ->all();
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m')",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "strftime('%Y-%m', {$column})",
        };
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value);
    }
}
