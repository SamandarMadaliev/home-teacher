@extends('layouts.app')

@section('title', $course->title.' — '.config('app.name'))

@if (! $course->videos->isEmpty())
    @push('head')
        @vite(['resources/js/course-lessons.js'])
    @endpush
@endif

@php
    $videosCount = $course->videos->count();
    $completedCount = $course->videos->filter(fn ($v) => $v->progress?->completed)->count();
    $coursePct = $videosCount > 0 ? $course->aggregateProgressPercent() : 0;
    $allCompleted = $videosCount > 0 && $completedCount === $videosCount;

    $resumeLabel = 'Start course';
    if ($currentVideo) {
        if ($allCompleted) {
            $resumeLabel = 'Replay last lesson';
        } elseif ($completedCount > 0 || ($currentVideo->progress && $currentVideo->progress->last_position > 0)) {
            $resumeLabel = 'Resume lesson';
        } else {
            $resumeLabel = 'Start course';
        }
    }
@endphp

@section('content')
    <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm font-medium text-sky-600 transition hover:text-sky-700 dark:text-sky-400/95 dark:hover:text-sky-300">
        <span aria-hidden="true">←</span> Home
    </a>

    <section
        class="mt-4 rounded-3xl border border-slate-200/95 bg-gradient-to-br from-white via-white to-sky-50/65 shadow-xl shadow-slate-300/35 ring-1 ring-sky-100/55 dark:border-slate-800/90 dark:from-slate-900/85 dark:via-slate-900/70 dark:to-sky-950/35 dark:shadow-black/30 dark:ring-sky-950/35"
        aria-label="Course overview"
    >
        <div class="px-5 py-6 sm:px-7 sm:py-7">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    @if ($course->archived_at !== null)
                        <span class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-amber-800 ring-1 ring-amber-300/55 dark:bg-amber-950/45 dark:text-amber-300 dark:ring-amber-700/40">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                            Archived
                        </span>
                    @endif

                    <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50 sm:text-4xl">
                        {{ $course->title }}
                    </h1>

                    <dl class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-sm text-slate-600 dark:text-slate-400">
                        <div class="flex items-center gap-1.5">
                            <dt class="sr-only">Lessons</dt>
                            <dd class="font-semibold text-slate-800 dark:text-slate-200 tabular-nums">{{ $videosCount }}</dd>
                            <span>{{ $videosCount === 1 ? 'lesson' : 'lessons' }}</span>
                        </div>
                        @if ($videosCount > 0)
                            <div class="flex items-center gap-1.5">
                                <dt class="sr-only">Completed lessons</dt>
                                <dd class="font-semibold tabular-nums text-emerald-700 dark:text-emerald-400/90">{{ $completedCount }}</dd>
                                <span>completed</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <dt class="sr-only">Remaining lessons</dt>
                                <dd class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ max(0, $videosCount - $completedCount) }}</dd>
                                <span>to go</span>
                            </div>
                        @endif
                    </dl>

                    @if ($videosCount > 0)
                        <div class="mt-5">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="font-medium uppercase tracking-wider text-slate-600 dark:text-slate-400">Overall progress</span>
                                <span class="tabular-nums font-semibold text-sky-700 dark:text-sky-300/95">{{ $coursePct }}%</span>
                            </div>
                            <div
                                class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-200 ring-1 ring-slate-300/95 dark:bg-slate-800 dark:ring-slate-900/80"
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
                        </div>
                    @endif
                </div>

                @if ($videosCount > 0 && $currentVideo)
                    <div class="flex shrink-0 flex-col items-stretch gap-3 lg:items-end lg:text-right">
                        <div class="min-w-0 lg:max-w-[20rem]">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-400/90">
                                {{ $allCompleted ? 'Course complete' : ($completedCount > 0 ? 'Pick up where you left off' : 'Next up') }}
                            </p>
                            <p class="mt-1.5 line-clamp-2 text-base font-semibold text-slate-900 dark:text-slate-100">
                                {{ $currentVideo->title }}
                            </p>
                        </div>
                        <a
                            href="{{ route('videos.show', $currentVideo) }}"
                            class="btn-primary inline-flex items-center justify-center gap-2 px-5 py-3 text-sm sm:text-base"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4" aria-hidden="true">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                            {{ $resumeLabel }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <div class="relative flex flex-wrap items-center gap-x-3 gap-y-2 rounded-b-3xl border-t border-slate-200/85 bg-slate-50/75 px-5 py-3.5 text-xs text-slate-600 sm:px-7 dark:border-slate-800/85 dark:bg-slate-950/30 dark:text-slate-400">
            @if ($course->folder_path)
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 shrink-0 text-slate-500 dark:text-slate-500" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                    </svg>
                    <span class="truncate font-mono text-[0.7rem] tracking-tight text-slate-700 dark:text-slate-300" title="{{ $course->folder_path }}">
                        {{ $course->folder_path }}
                    </span>
                </div>
                <form action="{{ route('courses.rescan', $course) }}" method="post" class="shrink-0">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300/95 bg-white/95 px-2.5 py-1.5 text-[0.7rem] font-semibold text-slate-700 shadow-sm transition hover:border-sky-500/45 hover:bg-sky-50/95 hover:text-sky-800 dark:border-slate-700/85 dark:bg-slate-900/70 dark:text-slate-200 dark:hover:border-sky-600/55 dark:hover:bg-slate-800/85 dark:hover:text-white"
                        title="Adds new files from disk and removes missing ones. Your lesson order and renamed titles are kept."
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Rescan folder
                    </button>
                </form>
            @endif

            <details class="manage-menu group relative ml-auto shrink-0">
                <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-lg border border-slate-300/95 bg-white/95 px-2.5 py-1.5 text-[0.7rem] font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:text-slate-900 group-open:border-sky-500/55 group-open:bg-sky-50/85 dark:border-slate-700/85 dark:bg-slate-900/70 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:text-white dark:group-open:border-sky-600/55 dark:group-open:bg-slate-800/85">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-3.5" aria-hidden="true">
                        <path d="M12 6.75a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm0 6.75a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm0 6.75a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z" />
                    </svg>
                    Manage
                </summary>
                <div class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200/95 bg-white shadow-2xl ring-1 ring-slate-200/85 dark:border-slate-800/90 dark:bg-slate-900 dark:ring-slate-800/80">
                    @if ($course->archived_at === null)
                        <form action="{{ route('courses.archive', $course) }}" method="post">
                            @csrf
                            <button
                                type="submit"
                                class="flex w-full items-center gap-2 px-3.5 py-2.5 text-left text-xs font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800/80"
                                onclick="return confirm('Archive this course? You can restore it later from the Courses page.')"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-slate-500" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                                Archive course
                            </button>
                        </form>
                    @else
                        <form action="{{ route('courses.restore', $course) }}" method="post">
                            @csrf
                            <button
                                type="submit"
                                class="flex w-full items-center gap-2 px-3.5 py-2.5 text-left text-xs font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800/80"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                Restore course
                            </button>
                        </form>
                    @endif
                    <form action="{{ route('courses.destroy', $course) }}" method="post" class="border-t border-slate-200/95 dark:border-slate-800/85">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="flex w-full items-center gap-2 px-3.5 py-2.5 text-left text-xs font-semibold text-rose-700 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/15"
                            onclick="return confirm('Delete this course permanently? This removes all lessons, progress, and notes for this course.')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Delete permanently
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </section>

    @if ($videosCount === 0)
        <div class="card-surface mt-8 px-8 py-12 text-center text-slate-600 dark:text-slate-400">
            This course has no lessons yet.
            @if ($course->folder_path)
                Use <strong class="text-sky-600 dark:text-sky-300/95">Rescan folder</strong> if videos are already on disk.
            @else
                Add a folder path to this course to scan for lessons.
            @endif
        </div>
    @else
        <section class="mt-8" aria-labelledby="course-lessons-heading">
            <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                <div class="min-w-0">
                    <h2 id="course-lessons-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                        Lessons
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-500">
                        Drag <span class="font-medium text-slate-700 dark:text-slate-300">⠿</span> to reorder · click the pencil to rename · order saves on drop.
                    </p>
                </div>
                <span class="text-xs tabular-nums text-slate-600 dark:text-slate-500">
                    {{ $completedCount }} / {{ $videosCount }} complete
                </span>
            </div>

            <ol
                id="course-lessons-sortable"
                class="mt-4 space-y-2.5"
                data-reorder-url="{{ route('courses.videos.reorder', $course) }}"
            >
                @foreach ($course->videos as $video)
                    @php
                        $p = $video->progress;
                        $pct = $p ? $p->progressPercent() : 0;
                        $isCurrent = $currentVideo && $currentVideo->is($video);
                        $isNext = $nextVideo && $nextVideo->is($video);
                        $completed = (bool) $p?->completed;
                        $rowId = $isCurrent ? 'lesson-current' : null;
                        $lessonRenameEditing =
                            $errors->has('title') && (string) old('lesson_video_id') === (string) $video->id;
                    @endphp
                    <li
                        @if ($rowId) id="{{ $rowId }}" @endif
                        data-video-id="{{ $video->id }}"
                        class="group relative overflow-hidden rounded-2xl border transition
                            @if ($isCurrent)
                                border-sky-400/65 bg-gradient-to-r from-sky-100/96 to-blue-50/93 shadow-lg shadow-sky-300/30 ring-1 ring-sky-400/45 dark:border-sky-500/45 dark:from-sky-950/55 dark:to-blue-950/35 dark:shadow-blue-950/25 dark:ring-sky-500/30
                            @elseif ($completed)
                                border-emerald-200/85 bg-emerald-50/35 ring-1 ring-emerald-200/60 dark:border-emerald-900/40 dark:bg-emerald-950/15 dark:ring-emerald-900/30
                            @else
                                border-slate-200/95 bg-white/92 ring-1 ring-slate-200/90 hover:border-slate-300 dark:border-slate-800/90 dark:bg-slate-900/55 dark:ring-slate-800/80 dark:hover:border-slate-700
                            @endif"
                    >
                        <div class="flex items-stretch">
                            <button
                                type="button"
                                class="lesson-drag-handle flex w-9 shrink-0 cursor-grab touch-none items-center justify-center border-r border-slate-200/85 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 active:cursor-grabbing dark:border-slate-800/85 dark:text-slate-600 dark:hover:bg-slate-800/60 dark:hover:text-slate-300"
                                aria-label="Drag to reorder {{ $video->title }}"
                            >
                                <span class="select-none text-base leading-none" aria-hidden="true">⠿</span>
                            </button>

                            <div class="min-w-0 flex-1 px-4 py-3.5 sm:px-5">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-5">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <span class="mt-0.5 inline-flex h-8 min-w-[2.25rem] shrink-0 items-center justify-center rounded-lg text-xs font-semibold tabular-nums
                                            @if ($completed)
                                                bg-emerald-100 text-emerald-700 ring-1 ring-emerald-300/60 dark:bg-emerald-950/55 dark:text-emerald-300 dark:ring-emerald-700/40
                                            @elseif ($isCurrent)
                                                bg-sky-200 text-sky-900 ring-1 ring-sky-400/55 dark:bg-sky-500/35 dark:text-sky-100 dark:ring-sky-400/40
                                            @else
                                                bg-slate-100 text-slate-600 ring-1 ring-slate-300/95 dark:bg-slate-800/80 dark:text-slate-400 dark:ring-slate-700/80
                                            @endif">
                                            @if ($completed)
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" clip-rule="evenodd" />
                                                </svg>
                                            @else
                                                {{ $video->sort_order }}
                                            @endif
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <div class="lesson-rename {{ $lessonRenameEditing ? 'lesson-rename--editing' : '' }}" data-lesson-rename>
                                                <div class="lesson-rename-view">
                                                    <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                                        <a
                                                            href="{{ route('videos.show', $video) }}"
                                                            class="min-w-0 max-w-[min(100%,28rem)] truncate font-semibold text-slate-900 transition hover:text-sky-700 hover:underline decoration-sky-600/55 underline-offset-2 dark:text-slate-50 dark:hover:text-sky-300"
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

                                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[0.65rem]">
                                                        @if ($isCurrent)
                                                            <span class="inline-flex items-center gap-1 rounded-full bg-sky-200 px-2 py-0.5 font-semibold uppercase tracking-wider text-sky-950 ring-1 ring-sky-400/55 dark:bg-sky-500/30 dark:text-sky-100 dark:ring-sky-400/35">
                                                                <span class="h-1.5 w-1.5 rounded-full bg-sky-700 dark:bg-sky-300" aria-hidden="true"></span>
                                                                Current
                                                            </span>
                                                        @endif
                                                        @if ($isNext && ! $isCurrent)
                                                            <span class="rounded-full bg-slate-200 px-2 py-0.5 font-semibold uppercase tracking-wider text-slate-700 ring-1 ring-slate-400/55 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">
                                                                Up next
                                                            </span>
                                                        @endif
                                                        @if ($completed && ! $isCurrent)
                                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 font-semibold uppercase tracking-wider text-emerald-800 ring-1 ring-emerald-300/55 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-700/40">
                                                                Completed
                                                            </span>
                                                        @endif
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
                                                        class="input-field min-h-[40px] min-w-0 flex-1 font-medium sm:max-w-md"
                                                        autocomplete="off"
                                                    />
                                                    <div class="flex shrink-0 flex-wrap gap-2">
                                                        <button type="submit" class="btn-primary py-2 text-xs sm:text-sm">Save</button>
                                                        <button type="button" class="lesson-rename-cancel btn-secondary py-2 text-xs sm:text-sm">Cancel</button>
                                                    </div>
                                                    <p class="basis-full text-[0.65rem] text-slate-600 dark:text-slate-500">
                                                        Press <kbd class="rounded bg-slate-200 px-1 py-0.5 font-mono ring-1 ring-slate-300 dark:bg-slate-800 dark:ring-slate-700">Esc</kbd> to cancel.
                                                    </p>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex min-w-[10rem] shrink-0 items-center gap-3 sm:max-w-xs sm:flex-1">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200 ring-1 ring-slate-300/95 dark:bg-slate-800 dark:ring-slate-900/80">
                                            <div
                                                class="h-full rounded-full
                                                    @if ($completed) bg-gradient-to-r from-emerald-400 to-emerald-500
                                                    @else bg-gradient-to-r from-sky-500 to-blue-500
                                                    @endif transition-[width] duration-300"
                                                style="width: {{ $pct }}%"
                                            ></div>
                                        </div>
                                        <span class="w-9 shrink-0 text-right text-xs tabular-nums text-slate-600 dark:text-slate-500">{{ $pct }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <script>
            document.getElementById('lesson-current')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        </script>
    @endif
@endsection
