@extends('layouts.app')

@section('title', $roadmap->title.' — '.config('app.name'))

@if ($courses->isNotEmpty())
    @push('head')
        @vite(['resources/js/roadmap-courses.js'])
    @endpush
@endif

@section('content')
    <div class="mb-10 flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <a href="{{ route('roadmaps.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-sky-400/95 transition hover:text-sky-300">
                <span aria-hidden="true">←</span> All roadmaps
            </a>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-50 sm:text-4xl">{{ $roadmap->title }}</h1>
            @if ($roadmap->description)
                <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-400">
                    {{ $roadmap->description }}
                </p>
            @endif
            <p class="mt-3 text-sm text-slate-500">
                {{ $roadmap->courses_count }} {{ $roadmap->courses_count === 1 ? 'course' : 'courses' }} in this order
            </p>
        </div>
        <a href="{{ route('roadmaps.edit', $roadmap) }}" class="btn-secondary shrink-0 self-start">
            Edit details
        </a>
    </div>

    @if ($allComplete && $courses->isNotEmpty())
        <div class="mb-8 rounded-2xl border border-emerald-800/50 bg-emerald-950/35 px-5 py-4 text-sm text-emerald-100 shadow-lg shadow-emerald-950/20">
            <span class="font-semibold text-emerald-50">You completed this path.</span>
            <span class="text-emerald-100/90"> Every course in this roadmap is at 100% overall progress.</span>
        </div>
    @endif

    @if ($availableCourses->isNotEmpty())
        <div class="card-surface mb-10 max-w-2xl p-6 sm:p-8">
            <h2 class="text-sm font-semibold text-slate-200">Add a course</h2>
            <p class="mt-2 text-sm text-slate-400">
                Choose a course from your library. You can drag rows below to change order anytime.
            </p>
            <form action="{{ route('roadmaps.courses.attach', $roadmap) }}" method="post" class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end">
                @csrf
                <div class="min-w-0 flex-1">
                    <label for="course_id" class="sr-only">Course</label>
                    <select name="course_id" id="course_id" required class="input-field font-sans">
                        <option value="" disabled selected>Select a course…</option>
                        @foreach ($availableCourses as $c)
                            <option value="{{ $c->id }}">{{ $c->title }}</option>
                        @endforeach
                    </select>
                    @error('course_id')
                        <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-primary shrink-0">
                    Add to roadmap
                </button>
            </form>
        </div>
    @elseif ($courses->isEmpty())
        <div class="card-surface mb-10 max-w-2xl px-6 py-8 text-sm text-slate-400">
            @if ($libraryHasCourses)
                <p>All of your courses are already on this roadmap, or you need to add courses under <strong class="text-slate-300">Courses</strong> first.</p>
            @else
                <p>
                    You do not have any courses yet.
                    <a href="{{ route('courses.create') }}" class="font-semibold text-sky-400 underline decoration-sky-500/40 underline-offset-4 hover:text-sky-300">Add a course</a>
                    <span class="text-slate-500"> to index a folder of videos, then return here to build your order.</span>
                </p>
            @endif
        </div>
    @endif

    @if ($courses->isEmpty())
        <div class="card-surface px-8 py-14 text-center text-slate-400">
            <p class="text-lg font-medium text-slate-200">No courses in this roadmap yet</p>
            <p class="mt-2 text-sm">
                Use <strong class="text-slate-300">Add a course</strong> above when you have courses in your library.
            </p>
        </div>
    @else
        <p class="mb-4 text-sm text-slate-400">
            Drag the handle (<span class="font-medium text-slate-300">⠿</span>) to reorder. Order saves when you drop a row. Open a course to watch lessons in your usual flow.
        </p>
        <ol
            id="roadmap-courses-sortable"
            class="space-y-3"
            data-reorder-url="{{ route('roadmaps.courses.reorder', $roadmap) }}"
        >
            @foreach ($courses as $course)
                @php
                    $pct = $course->aggregateProgressPercent();
                    $isCurrent = $currentCourse && $currentCourse->is($course) && ! $allComplete;
                    $rowId = $isCurrent ? 'roadmap-current' : null;
                @endphp
                <li
                    id="{{ $rowId }}"
                    data-course-id="{{ $course->id }}"
                    class="rounded-2xl border px-5 py-4 transition sm:px-6 sm:py-5
                        @if ($isCurrent)
                            border-violet-500/45 bg-gradient-to-r from-violet-950/45 to-blue-950/35 shadow-lg shadow-violet-950/20 ring-1 ring-violet-500/30
                        @else
                            card-surface border-slate-800/90
                        @endif"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-start gap-3 sm:gap-4">
                            <button
                                type="button"
                                class="roadmap-drag-handle mt-1 inline-flex cursor-grab touch-none items-center justify-center rounded-lg border border-slate-700/80 bg-slate-900/80 px-2 py-2 text-slate-500 ring-1 ring-slate-800/80 transition hover:border-slate-600 hover:text-slate-300 active:cursor-grabbing"
                                aria-label="Drag to reorder {{ $course->title }}"
                            >
                                <span class="select-none text-base leading-none tracking-tighter" aria-hidden="true">⠿</span>
                            </button>
                            <span class="mt-0.5 inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl bg-slate-800/95 text-xs font-semibold tabular-nums text-violet-300/90 ring-1 ring-slate-700/80">
                                {{ $loop->iteration }}
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('courses.show', $course) }}" class="font-semibold text-slate-50 transition hover:text-white hover:underline decoration-violet-600/55 underline-offset-2">
                                    {{ $course->title }}
                                </a>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                    @if ($isCurrent)
                                        <span class="rounded-full bg-violet-500/25 px-2.5 py-0.5 font-medium text-violet-200 ring-1 ring-violet-400/35">Up next</span>
                                    @endif
                                    @if ($pct >= 100)
                                        <span class="font-medium text-emerald-400/90">Complete</span>
                                    @endif
                                    <span class="text-slate-500">
                                        {{ $course->videos_count }} {{ $course->videos_count === 1 ? 'lesson' : 'lessons' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-6">
                            <div class="min-w-[160px] flex-1 sm:max-w-xs">
                                <div class="flex items-center justify-between gap-2 text-xs text-slate-500">
                                    <span>Overall</span>
                                    <span class="tabular-nums font-semibold text-slate-300">{{ $pct }}%</span>
                                </div>
                                <div
                                    class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-800 ring-1 ring-slate-900"
                                    role="progressbar"
                                    aria-valuenow="{{ $pct }}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-label="Overall progress for {{ $course->title }}"
                                >
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-violet-500 to-blue-500 transition-[width] duration-300"
                                        style="width: {{ $pct }}%"
                                    ></div>
                                </div>
                            </div>
                            <form action="{{ route('roadmaps.courses.detach', [$roadmap, $course]) }}" method="post" class="shrink-0" onsubmit="return confirm('Remove “{{ $course->title }}” from this roadmap?');">
                                @csrf
                                @method('delete')
                                <button type="submit" class="btn-ghost text-rose-300/95 hover:border-rose-800/80 hover:bg-rose-950/35 hover:text-rose-100">
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>

        <script>
            document.getElementById('roadmap-current')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        </script>
    @endif
@endsection
