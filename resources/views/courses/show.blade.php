@extends('layouts.app')

@section('title', $course->title.' — '.config('app.name'))

@if (! $course->videos->isEmpty())
    @push('head')
        @vite(['resources/js/course-lessons.js'])
    @endpush
@endif

@section('content')
    <div class="mb-10">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm font-medium text-sky-400/95 transition hover:text-sky-300">
            <span aria-hidden="true">←</span> Home
        </a>
        <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-50 sm:text-4xl">{{ $course->title }}</h1>
        @php
            $videosCount = $course->videos->count();
        @endphp
        <p class="mt-2 text-sm text-slate-400">
            {{ $videosCount }} {{ $videosCount === 1 ? 'lesson' : 'lessons' }}
        </p>

        @if (! $course->videos->isEmpty())
            @php
                $coursePct = $course->aggregateProgressPercent();
            @endphp
            <div
                class="mt-6 max-w-2xl rounded-2xl border border-slate-800/90 bg-slate-900/40 px-5 py-4 ring-1 ring-slate-800/60"
                role="group"
                aria-label="Course completion"
            >
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="font-medium text-slate-200">Your progress in this course</span>
                    <span class="tabular-nums text-base font-semibold text-sky-300/95">{{ $coursePct }}%</span>
                </div>
                <div
                    class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-800 ring-1 ring-slate-900/80"
                    role="progressbar"
                    aria-valuenow="{{ $coursePct }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Overall course progress"
                >
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-sky-500 to-blue-500 transition-[width] duration-300"
                        style="width: {{ $coursePct }}%"
                    ></div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Average completion across all lessons. Keep going.</p>
            </div>
        @endif

        @if ($course->folder_path)
            <p class="mt-4 max-w-3xl break-all rounded-xl bg-slate-900/55 px-4 py-3 font-mono text-xs text-slate-400 ring-1 ring-slate-800/90" title="Video folder">
                {{ $course->folder_path }}
            </p>
            <form action="{{ route('courses.rescan', $course) }}" method="post" class="mt-5 flex flex-wrap items-center gap-3">
                @csrf
                <button type="submit" class="btn-secondary text-sm">
                    Rescan folder
                </button>
                <span class="text-xs text-slate-500">Adds new files from disk and removes missing ones. Your lesson order and <strong class="font-medium text-slate-400">renamed titles</strong> for existing files are kept.</span>
            </form>
        @endif
    </div>

    @if ($course->videos->isEmpty())
        <div class="card-surface px-8 py-12 text-center text-slate-400">
            This course has no lessons yet. Use <strong class="text-sky-300/95">Rescan folder</strong> if videos are already on disk.
        </div>
    @else
        <p class="mb-4 text-sm text-slate-400">
            Drag the handle (<span class="font-medium text-slate-300">⠿</span>) to reorder lessons. Order saves when you drop a row.
            <span class="text-slate-500">Use the pencil icon beside a lesson title to rename it.</span>
        </p>
        <ol
            id="course-lessons-sortable"
            class="space-y-3"
            data-reorder-url="{{ route('courses.videos.reorder', $course) }}"
        >
            @foreach ($course->videos as $video)
                @php
                    $p = $video->progress;
                    $pct = $p ? $p->progressPercent() : 0;
                    $isCurrent = $currentVideo && $currentVideo->is($video);
                    $isNext = $nextVideo && $nextVideo->is($video);
                    $rowId = $isCurrent ? 'lesson-current' : null;
                @endphp
                <li
                    id="{{ $rowId }}"
                    data-video-id="{{ $video->id }}"
                    class="rounded-2xl border px-5 py-4 transition sm:px-6 sm:py-5
                        @if ($isCurrent)
                            border-sky-500/45 bg-gradient-to-r from-sky-950/55 to-blue-950/35 shadow-lg shadow-blue-950/25 ring-1 ring-sky-500/30
                        @elseif ($isNext)
                            border-slate-700/80 bg-slate-900/75 ring-1 ring-slate-800/80
                        @else
                            card-surface border-slate-800/90
                        @endif"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3 sm:gap-4">
                            <button
                                type="button"
                                class="lesson-drag-handle mt-1 inline-flex cursor-grab touch-none items-center justify-center rounded-lg border border-slate-700/80 bg-slate-900/80 px-2 py-2 text-slate-500 ring-1 ring-slate-800/80 transition hover:border-slate-600 hover:text-slate-300 active:cursor-grabbing"
                                aria-label="Drag to reorder {{ $video->title }}"
                            >
                                <span class="select-none text-base leading-none tracking-tighter" aria-hidden="true">⠿</span>
                            </button>
                            <span class="mt-0.5 inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl bg-slate-800/95 text-xs font-semibold tabular-nums text-sky-300/90 ring-1 ring-slate-700/80">
                                {{ $video->sort_order }}
                            </span>
                            @php
                                $lessonRenameEditing =
                                    $errors->has('title') && (string) old('lesson_video_id') === (string) $video->id;
                            @endphp
                            <div class="min-w-0 flex-1 space-y-2">
                            <div class="lesson-rename {{ $lessonRenameEditing ? 'lesson-rename--editing' : '' }}" data-lesson-rename>
                                <div class="lesson-rename-view">
                                    <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                        <a
                                            href="{{ route('videos.show', $video) }}"
                                            class="min-w-0 max-w-[min(100%,28rem)] truncate font-semibold text-slate-50 transition hover:text-white hover:underline decoration-sky-600/55 underline-offset-2"
                                        >
                                            {{ $video->title }}
                                        </a>
                                        <button
                                            type="button"
                                            class="lesson-rename-trigger"
                                            aria-label="Rename {{ $video->title }}"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <form
                                    action="{{ route('videos.update', $video) }}"
                                    method="post"
                                    class="lesson-rename-edit flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
                                    data-original-title="{{ $video->title }}"
                                    aria-label="Rename lesson"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="lesson_video_id" value="{{ $video->id }}" />
                                    <label for="lesson-title-input-{{ $video->id }}" class="sr-only">Lesson display name</label>
                                    <input
                                        id="lesson-title-input-{{ $video->id }}"
                                        type="text"
                                        name="title"
                                        value="{{ (string) old('lesson_video_id') === (string) $video->id ? old('title', $video->title) : $video->title }}"
                                        required
                                        maxlength="255"
                                        class="input-field min-h-[40px] min-w-0 flex-1 font-medium text-slate-50 sm:max-w-md"
                                        autocomplete="off"
                                    />
                                    <div class="flex shrink-0 flex-wrap gap-2">
                                        <button type="submit" class="btn-primary py-2 text-xs sm:text-sm">Save</button>
                                        <button type="button" class="lesson-rename-cancel btn-secondary py-2 text-xs sm:text-sm">Cancel</button>
                                    </div>
                                    <p class="basis-full text-[0.65rem] text-slate-500">Press <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono ring-1 ring-slate-700">Esc</kbd> to cancel.</p>
                                </form>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                @if ($isCurrent)
                                    <span class="rounded-full bg-sky-500/25 px-2.5 py-0.5 font-medium text-sky-200 ring-1 ring-sky-400/35">Current</span>
                                @endif
                                @if ($isNext && ! $isCurrent)
                                    <span class="rounded-full bg-slate-800 px-2.5 py-0.5 font-medium text-slate-300 ring-1 ring-slate-700">Up next</span>
                                @endif
                                @if ($p?->completed)
                                    <span class="font-medium text-emerald-400/90">Completed</span>
                                @endif
                            </div>
                            </div>
                        </div>
                        <div class="min-w-[160px] flex-1 sm:max-w-xs">
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-800 ring-1 ring-slate-900">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-sky-500 to-blue-500 transition-[width] duration-300"
                                    style="width: {{ $pct }}%"
                                ></div>
                            </div>
                            <p class="mt-1.5 text-right text-xs tabular-nums text-slate-500">{{ $pct }}%</p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>

        <script>
            document.getElementById('lesson-current')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        </script>
    @endif
@endsection
