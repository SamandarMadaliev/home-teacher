@extends('layouts.app')

@section('main_max_class', 'max-w-7xl')

@section('title', 'Courses — '.config('app.name'))

@section('content')
    <div class="mb-10 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-widest text-sky-500/90">Your library</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-50 sm:text-4xl">Courses</h1>
            <p class="mt-2 max-w-lg text-sm text-slate-400">Watch locally. Progress saves automatically.</p>
        </div>
        <a href="{{ route('courses.create') }}" class="btn-primary shrink-0">
            Add course
        </a>
    </div>

    @if ($courses->isEmpty())
        <div class="card-surface px-8 py-14 text-center">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/10 ring-1 ring-sky-500/25" aria-hidden="true">
                <svg class="h-8 w-8 text-sky-400/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                </svg>
            </div>
            <p class="text-lg font-medium text-slate-100">No courses yet</p>
            <p class="mt-2 text-sm text-slate-400">
                <a href="{{ route('courses.create') }}" class="font-semibold text-sky-400 underline decoration-sky-500/40 underline-offset-4 hover:text-sky-300">
                    Add a course
                </a>
                <span class="text-slate-500"> and choose the folder where your videos live.</span>
            </p>
        </div>
    @else
        <ul class="courses-grid" role="list">
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
                            <h2 class="line-clamp-3 text-lg font-semibold leading-snug text-slate-50 transition group-hover:text-white">
                                {{ $course->title }}
                            </h2>
                            <p class="mt-4 text-sm text-slate-400">
                                {{ $course->videos_count }} lesson{{ $course->videos_count === 1 ? '' : 's' }}
                            </p>
                            @if ($course->videos_count > 0)
                                <div class="mt-4">
                                    <div class="flex items-center justify-between gap-2 text-xs text-slate-500">
                                        <span>Overall progress</span>
                                        <span class="tabular-nums font-semibold text-slate-300">{{ $coursePct }}%</span>
                                    </div>
                                    <div
                                        class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-800 ring-1 ring-slate-900/80"
                                        role="progressbar"
                                        aria-valuenow="{{ $coursePct }}"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-label="Overall progress for {{ $course->title }}"
                                    >
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-sky-500 to-blue-500 transition-[width]"
                                            style="width: {{ $coursePct }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="mt-6 flex items-center justify-between border-t border-slate-700/60 pt-4">
                            <span class="text-xs font-medium text-sky-400/95 transition group-hover:text-sky-300">
                                Open
                            </span>
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-800/95 text-sky-400/95 ring-1 ring-slate-600/80 transition group-hover:bg-sky-500/15 group-hover:text-sky-300 group-hover:ring-sky-500/40"
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
@endsection
