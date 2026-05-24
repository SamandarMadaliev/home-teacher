@extends('profile._layout')

@section('title', 'Progress — '.config('app.name'))

@section('profile_panel')
    @php
        $summary = $stats['summary'];
        $activity = $stats['activity'];
        $courses = $stats['courses'];
        $hasWatchHistory = $summary['lessons_started'] > 0;
    @endphp

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Learning progress</h2>
        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-500">
            Overview of lessons watched, time spent learning, and when you were most active. Watch time is estimated from saved playhead positions.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <div class="card-surface p-5">
            <p class="text-2xl font-bold tabular-nums text-accent-stat">{{ $summary['watch_time_label'] }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">Estimated learning time</p>
            @if ($summary['watch_time_seconds'] >= 3600)
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-500">
                    ≈ {{ number_format($summary['watch_time_hours'], 1) }} hours total
                </p>
            @endif
        </div>
        <div class="card-surface p-5">
            <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $summary['lessons_completed'] }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">
                {{ $summary['lessons_completed'] === 1 ? 'Lesson' : 'Lessons' }} completed
            </p>
        </div>
        <div class="card-surface p-5">
            <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $summary['lessons_started'] }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">
                {{ $summary['lessons_started'] === 1 ? 'Lesson' : 'Lessons' }} started
            </p>
        </div>
        <div class="card-surface p-5">
            <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $summary['total_lessons'] }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">
                {{ $summary['total_lessons'] === 1 ? 'Lesson' : 'Lessons' }} in library
            </p>
        </div>
        <div class="card-surface p-5">
            <p class="text-2xl font-bold tabular-nums text-accent-stat">{{ $summary['overall_percent'] }}<span class="text-lg font-semibold text-accent-stat-muted">%</span></p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">Overall completion</p>
        </div>
    </div>

    @if ($hasWatchHistory)
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <div class="card-surface p-6 sm:p-8">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Most active month</h3>
                @if ($activity['most_active_month'])
                    <p class="mt-3 text-2xl font-bold text-slate-900 dark:text-white">{{ $activity['most_active_month']['label'] }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">
                        {{ $activity['most_active_month']['lessons'] }}
                        {{ $activity['most_active_month']['lessons'] === 1 ? 'lesson' : 'lessons' }}
                        with recorded activity
                    </p>
                @else
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-500">No monthly activity yet.</p>
                @endif
            </div>

            <div class="card-surface p-6 sm:p-8">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Watch timeline</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    @if ($activity['first_watch_at'])
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-500">First lesson activity</dt>
                            <dd class="font-medium tabular-nums text-slate-900 dark:text-slate-100">{{ $activity['first_watch_at']->format('M j, Y') }}</dd>
                        </div>
                    @endif
                    @if ($activity['last_watch_at'])
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-600 dark:text-slate-500">Last lesson activity</dt>
                            <dd class="font-medium tabular-nums text-slate-900 dark:text-slate-100">{{ $activity['last_watch_at']->format('M j, Y') }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-600 dark:text-slate-500">Estimated learning time</dt>
                        <dd class="font-medium tabular-nums text-slate-900 dark:text-slate-100">{{ $summary['watch_time_label'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-600 dark:text-slate-500">Not started yet</dt>
                        <dd class="font-medium tabular-nums text-slate-900 dark:text-slate-100">{{ $summary['lessons_not_started'] }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card-surface mt-6 p-6 sm:p-8">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Activity by month</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Lessons with progress updates in each month (up to the last 24 months)</p>
            <ul class="analytics-month-chart mt-6" role="list">
                @foreach ($activity['monthly'] as $month)
                    <li class="analytics-month-chart__row">
                        <span class="analytics-month-chart__label tabular-nums">{{ $month['label'] }}</span>
                        <div class="analytics-month-chart__track" aria-hidden="true">
                            <span
                                class="analytics-month-chart__bar progress-bar-fill"
                                style="width: {{ max($month['bar_percent'], $month['lessons'] > 0 ? 4 : 0) }}%"
                            ></span>
                        </div>
                        <span class="analytics-month-chart__value tabular-nums">{{ $month['lessons'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="card-surface mt-6 p-8 text-center">
            <p class="text-sm text-slate-600 dark:text-slate-500">Start watching a lesson to see activity trends and your busiest month.</p>
            <a href="{{ route('courses.index') }}" class="btn-primary mt-4 inline-flex">Browse courses</a>
        </div>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="card-surface p-5">
            <p class="text-xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $summary['active_courses_count'] }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">Active {{ $summary['active_courses_count'] === 1 ? 'course' : 'courses' }}</p>
        </div>
        <div class="card-surface p-5">
            <p class="text-xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $summary['courses_count'] }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">Total {{ $summary['courses_count'] === 1 ? 'course' : 'courses' }}</p>
        </div>
        <div class="card-surface p-5">
            <p class="text-xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $summary['notes_count'] }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">{{ $summary['notes_count'] === 1 ? 'Note' : 'Notes' }} saved</p>
        </div>
    </div>

    @if ($courses !== [])
        <div class="card-surface mt-6 p-6 sm:p-8">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">By course</h3>
            <ul class="mt-5 divide-y divide-slate-200/90 dark:divide-slate-800/90" role="list">
                @foreach ($courses as $course)
                    <li class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:gap-6">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-slate-900 dark:text-slate-100">{{ $course['title'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-500">
                                {{ $course['completed'] }} / {{ $course['lessons'] }} lessons completed
                            </p>
                        </div>
                        <div class="flex w-full items-center gap-3 sm:w-48">
                            <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-200/95 dark:bg-slate-800">
                                <span class="progress-bar-fill block h-full" style="width: {{ $course['percent'] }}%"></span>
                            </div>
                            <span class="w-9 shrink-0 text-right text-xs font-semibold tabular-nums text-slate-700 dark:text-slate-300">{{ $course['percent'] }}%</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
