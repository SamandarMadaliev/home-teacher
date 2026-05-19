@extends('layouts.app')

@section('main_max_class', 'max-w-7xl')

@section('title', 'Courses — '.config('app.name'))

@section('content')
    <div class="mb-10 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-eyebrow text-accent">Your library</p>
            <h1 class="home-page-title mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Courses</h1>
            <p class="mt-2 max-w-lg text-sm leading-relaxed text-slate-600 dark:text-slate-500">
                Watch locally. Progress saves automatically. Courses you watched most recently appear first.
            </p>
        </div>
        <a href="{{ route('courses.create') }}" class="btn-primary shrink-0">
            Add course
        </a>
    </div>

    @if ($courses->isEmpty())
        <div class="card-surface px-8 py-14 text-center">
            <div class="icon-empty-accent" aria-hidden="true">
                <svg class="h-8 w-8 text-accent/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                </svg>
            </div>
            <p class="text-lg font-medium text-slate-800 dark:text-slate-100">No courses yet</p>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                <a href="{{ route('courses.create') }}" class="link-accent-underline">
                    Add a course
                </a>
                <span class="text-slate-600 dark:text-slate-500"> and choose the folder where your videos live.</span>
            </p>
        </div>
    @else
        <ul class="courses-grid courses-grid--roomy" role="list">
            @foreach ($courses as $course)
                @php
                    $accents = ['course-card-accent-sky', 'course-card-accent-slate', 'course-card-accent-emerald'];
                    $accentClass = $accents[$loop->index % 3];
                    $coursePct = $course->aggregateProgressPercent();
                @endphp
                <li class="flex min-h-0">
                    <a
                        href="{{ route('courses.show', $course) }}"
                        class="group course-card-box {{ $accentClass }} h-full w-full min-h-[12rem] flex-1"
                    >
                        <div class="min-w-0 flex-1">
                            <h2 class="line-clamp-3 text-lg font-semibold leading-snug text-slate-900 transition group-hover:text-slate-950 dark:text-slate-50 dark:group-hover:text-white">
                                {{ $course->title }}
                            </h2>
                            <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">
                                {{ $course->videos_count }} lesson{{ $course->videos_count === 1 ? '' : 's' }}
                            </p>
                            @if ($course->videos_count > 0)
                                <div class="mt-4">
                                    <div class="flex items-center justify-between gap-2 text-xs text-slate-600 dark:text-slate-500">
                                        <span>Overall progress</span>
                                        <span class="tabular-nums font-semibold text-slate-700 dark:text-slate-300">{{ $coursePct }}%</span>
                                    </div>
                                    <div
                                        class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-200 ring-1 ring-slate-300/95 dark:bg-slate-800 dark:ring-slate-900/80"
                                        role="progressbar"
                                        aria-valuenow="{{ $coursePct }}"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-label="Overall progress for {{ $course->title }}"
                                    >
                                        <div
                                            class="progress-bar-fill"
                                            style="width: {{ $coursePct }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="mt-6 flex items-center justify-between border-t border-slate-200/98 pt-4 dark:border-slate-700/60">
                            <span class="card-link-label">
                                Open
                            </span>
                            <span
                                class="arrow-box-accent"
                                aria-hidden="true"
                            >
                                →
                            </span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($archivedCourses->isNotEmpty())
        <section class="mt-12">
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Archived courses</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">Archived courses are hidden from home and active library cards.</p>
            </div>
            <ul class="space-y-3">
                @foreach ($archivedCourses as $course)
                    <li class="card-surface flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900 dark:text-slate-100">{{ $course->title }}</p>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">{{ $course->videos_count }} lesson{{ $course->videos_count === 1 ? '' : 's' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('courses.show', $course) }}" class="btn-secondary px-3 py-2 text-xs">Open</a>
                            <form action="{{ route('courses.restore', $course) }}" method="post">
                                @csrf
                                <button type="submit" class="btn-secondary px-3 py-2 text-xs">Restore</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
