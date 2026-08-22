@extends('layouts.app')

@section('main_max_class', 'max-w-7xl')

@section('title', $video->title.' — '.config('app.name'))

@push('head')
    @vite(['resources/js/player.js', 'resources/js/note-formatter.js'])
@endpush

@php
    $lessonIdx = $courseVideos->search(fn ($l) => $l->is($video));
    $lessonNum = $lessonIdx !== false ? (int) $lessonIdx + 1 : null;
    $totalLessons = $courseVideos->count();
    $vp = $video->progress;
    $lessonPct = $vp ? $vp->progressPercent() : 0;
    $lessonDone = (bool) $vp?->completed;
@endphp

@section('content')
    <div id="watch-layout" class="watch-layout">
        <div class="watch-player-stack min-w-0">
            <section
                class="watch-hero page-header-card-sm"
                aria-label="This lesson"
            >
                <a href="{{ route('courses.show', $video->course) }}" class="back-link">
                    <span aria-hidden="true">←</span> {{ $video->course->title }}
                </a>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-slate-600 dark:text-slate-400">
                    @if ($lessonNum !== null)
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 ring-1 ring-slate-200/95 dark:bg-slate-800/90 dark:ring-slate-700/80">
                            Lesson {{ $lessonNum }} of {{ $totalLessons }}
                        </span>
                    @endif
                    @if ($lessonDone)
                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 ring-1 ring-emerald-300/60 dark:bg-emerald-950/50 dark:text-emerald-300 dark:ring-emerald-700/40">
                            Completed
                        </span>
                    @endif
                </div>

                <div
                    class="lesson-rename mt-3 max-w-4xl {{ $errors->has('title') ? 'lesson-rename--editing' : '' }}"
                    data-lesson-rename
                >
                    <div class="lesson-rename-view lesson-rename-view--hero">
                        <h1
                            class="lesson-rename-title text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-50 sm:text-3xl"
                            title="{{ $video->title }}"
                        >
                            {{ $video->title }}
                        </h1>
                        <button
                            type="button"
                            class="lesson-rename-trigger"
                            aria-label="Rename this lesson"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                        </button>
                    </div>
                    <form
                        action="{{ route('videos.update', $video) }}"
                        method="post"
                        class="lesson-rename-edit flex flex-col gap-3"
                        data-original-title="{{ $video->title }}"
                        aria-label="Rename lesson"
                    >
                        @csrf
                        @method('PATCH')
                        <label for="watch-lesson-title-input" class="sr-only">Lesson name</label>
                        <input
                            id="watch-lesson-title-input"
                            type="text"
                            name="title"
                            value="{{ old('title', $video->title) }}"
                            required
                            maxlength="255"
                            class="input-field w-full text-xl font-bold tracking-tight sm:text-2xl"
                            autocomplete="off"
                        />
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="btn-primary px-5 py-2 text-sm">Save</button>
                            <button type="button" class="lesson-rename-cancel btn-secondary px-5 py-2 text-sm">Cancel</button>
                        </div>
                        <p class="text-[0.65rem] text-slate-600 dark:text-slate-500">
                            <kbd class="rounded bg-slate-200 px-1 py-0.5 font-mono ring-1 ring-slate-300 dark:bg-slate-800 dark:ring-slate-700">Esc</kbd>
                            cancels without saving.
                        </p>
                    </form>
                </div>

                @if ($totalLessons > 0)
                    <div class="watch-hero-progress mt-5 max-w-md">
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="font-medium text-slate-700 dark:text-slate-300">Progress on this lesson</span>
                            <span class="text-accent-stat">{{ $lessonPct }}%</span>
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 ring-1 ring-slate-300/95 dark:bg-slate-800 dark:ring-slate-900/80"
                            role="progressbar"
                            aria-valuenow="{{ $lessonPct }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="This lesson watch progress"
                        >
                            <div
                                class="progress-bar-fill"
                                style="width: {{ $lessonPct }}%"
                            ></div>
                        </div>
                    </div>
                @endif
            </section>

            <div class="watch-player-toolbar mt-5 hidden gap-3 xl:flex xl:items-center xl:justify-between">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[0.7rem] text-slate-600 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5">
                        <kbd class="rounded-md bg-slate-200/95 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-800 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">Space</kbd>
                        play
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <kbd class="rounded-md bg-slate-200/95 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-800 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">←</kbd>
                        <kbd class="rounded-md bg-slate-200/95 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-800 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">→</kbd>
                        ±10s
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <kbd class="rounded-md bg-slate-200/95 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-800 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">↑</kbd>
                        <kbd class="rounded-md bg-slate-200/95 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-800 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">↓</kbd>
                        volume
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <kbd class="rounded-md bg-slate-200/95 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-800 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">F</kbd>
                        fullscreen
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <kbd class="rounded-md bg-slate-200/95 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-800 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">T</kbd>
                        wider layout
                    </span>
                </div>
                <button
                    type="button"
                    id="theater-mode-toggle"
                    class="btn-secondary inline-flex shrink-0 items-center justify-center gap-2 py-2.5 text-xs font-semibold sm:text-sm"
                    aria-pressed="false"
                    data-label-off="Theater mode"
                    data-label-on="Exit theater"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-slate-600 dark:text-slate-400" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5v15h-16.5v-15Zm0 0L9 12m0 0 6.75 7.5M9 12 2.25 4.5M9 12l6.75-7.5M9 12l6.75 7.5" />
                    </svg>
                    Theater mode
                </button>
            </div>

            <div class="course-plyr player-chrome-ring mt-4 overflow-hidden rounded-2xl border border-slate-300/95 bg-black shadow-2xl shadow-slate-400/45 dark:border-slate-800/90">
                <video
                    id="course-video"
                    class="aspect-video w-full"
                    playsinline
                    preload="metadata"
                >
                    <source src="{{ route('videos.stream', $video) }}" type="video/mp4" />
                    Your browser does not support the video tag.
                </video>
            </div>

            <p class="mt-3 text-center text-[0.65rem] text-slate-500 dark:text-slate-500">
                Player:
                <a href="https://github.com/sampotts/plyr" class="link-accent underline decoration-accent" target="_blank" rel="noopener noreferrer">Plyr</a>
            </p>
        </div>

        <aside class="watch-lessons-sidebar w-full shrink-0 xl:w-auto xl:min-w-[18rem]" aria-label="Course lessons">
            <div class="card-surface overflow-hidden xl:sticky xl:top-28">
                <div class="playlist-panel-header">
                    <p class="text-accent-eyebrow text-[0.65rem] font-semibold uppercase tracking-[0.2em]">Playlist</p>
                    <p class="mt-1.5 truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $video->course->title }}</p>
                    <p class="mt-1 text-xs tabular-nums text-slate-600 dark:text-slate-400">{{ $totalLessons }} {{ $totalLessons === 1 ? 'lesson' : 'lessons' }}</p>
                    <a href="{{ route('courses.show', $video->course) }}" class="mt-3 inline-flex items-center gap-1 text-xs link-accent hover:underline">
                        Course overview
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
                <ol class="max-h-[min(70vh,38rem)] divide-y divide-slate-200/95 overflow-y-auto overscroll-contain dark:divide-slate-800/90">
                    @foreach ($courseVideos as $lesson)
                        @php
                            $p = $lesson->progress;
                            $pct = $p ? $p->progressPercent() : 0;
                            $active = $lesson->is($video);
                        @endphp
                        <li @if ($active) id="lesson-sidebar-active" @endif>
                            <a
                                href="{{ route('videos.show', $lesson) }}"
                                class="{{ $active
                                    ? 'lesson-row-active ring-1 ring-inset'
                                    : 'hover:bg-slate-100/96 dark:hover:bg-slate-800/55' }} flex gap-3 px-4 py-3.5 transition"
                            >
                                <span class="lesson-index-badge">
                                    {{ $lesson->sort_order }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium {{ $active ? 'text-slate-900 dark:text-white' : 'text-slate-700 dark:text-slate-200' }}">{{ $lesson->title }}</span>
                                    @if ($p?->completed && ! $active)
                                        <span class="mt-0.5 block text-[0.65rem] font-medium text-emerald-700 dark:text-emerald-400/85">Done</span>
                                    @endif
                                    <span class="mt-2 flex items-center gap-2">
                                        <span class="h-1 flex-1 overflow-hidden rounded-full bg-slate-200 ring-1 ring-slate-300/95 dark:bg-slate-800 dark:ring-slate-900/80">
                                            <span class="progress-bar-fill block h-full" style="width: {{ $pct }}%"></span>
                                        </span>
                                        <span class="shrink-0 text-[0.65rem] tabular-nums text-slate-600 dark:text-slate-500">{{ $pct }}%</span>
                                    </span>
                                </span>
                                @if ($active)
                                    <span class="sr-only">(playing)</span>
                                    <span class="dot-accent-glow" aria-hidden="true"></span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>

        <div class="watch-after-player min-w-0">
            <nav class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4" aria-label="Lesson navigation">
                @if ($previousVideo)
                    <a
                        href="{{ route('videos.show', $previousVideo) }}"
                        class="watch-nav-card-accent"
                    >
                        <span class="text-accent-eyebrow inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 shrink-0" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                            Previous lesson
                        </span>
                        <p class="watch-nav-title">
                            {{ $previousVideo->title }}
                        </p>
                    </a>
                @else
                    <div
                        class="watch-nav-card pointer-events-none flex min-h-23 select-none flex-col justify-center rounded-2xl border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-4 opacity-80 ring-1 ring-slate-200/90 dark:border-slate-700/80 dark:bg-slate-900/40 dark:opacity-70 dark:ring-slate-800/70"
                        aria-disabled="true"
                    >
                        <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 shrink-0 opacity-60" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                            Previous lesson
                        </span>
                        <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-500">First lesson — nothing before this</p>
                    </div>
                @endif

                @if ($nextVideo)
                    <a
                        href="{{ route('videos.show', $nextVideo) }}"
                        class="watch-nav-card-accent"
                    >
                        <span class="text-accent-eyebrow inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider">
                            Next lesson
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 shrink-0" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </span>
                        <p class="watch-nav-title">
                            {{ $nextVideo->title }}
                        </p>
                    </a>
                @else
                    <div
                        class="watch-nav-card pointer-events-none flex min-h-23 select-none flex-col justify-center rounded-2xl border border-dashed border-slate-300/90 bg-slate-50/80 px-5 py-4 opacity-80 ring-1 ring-slate-200/90 dark:border-slate-700/80 dark:bg-slate-900/40 dark:opacity-70 dark:ring-slate-800/70"
                        aria-disabled="true"
                    >
                        <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">
                            Next lesson
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 shrink-0 opacity-60" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </span>
                        <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-500">Last lesson — nothing after this</p>
                    </div>
                @endif
            </nav>

            @include('videos.partials.lesson-notes-resources')

        </div>
    </div>

    @if ($nextVideo)
        <div
            id="up-next-overlay"
            class="up-next-overlay fixed inset-0 z-200 flex flex-col justify-end p-4 sm:p-6"
            aria-hidden="true"
            aria-labelledby="up-next-heading"
            role="dialog"
        >
            <button
                type="button"
                id="up-next-backdrop"
                class="absolute inset-0 bg-slate-900/42 backdrop-blur-[3px] transition hover:bg-slate-900/50 dark:bg-slate-950/65 dark:hover:bg-slate-950/75"
                tabindex="-1"
                aria-label="Dismiss — stay on this lesson"
            ></button>
            <div
                class="modal-accent-ring relative z-10 mx-auto mb-2 w-full max-w-lg overflow-hidden rounded-2xl border border-slate-300/92 bg-white/96 shadow-2xl shadow-slate-400/30 ring-1 sm:mb-4 dark:border-slate-700/90 dark:bg-slate-900/95 dark:shadow-black/50"
            >
                <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6 sm:py-5">
                    <div class="min-w-0 flex-1">
                        <p id="up-next-heading" class="text-accent-eyebrow text-[0.65rem] font-semibold uppercase tracking-[0.18em]">
                            Up next
                        </p>
                        <p id="up-next-title" class="mt-2 line-clamp-2 text-base font-semibold leading-snug text-slate-900 dark:text-white sm:text-lg">
                            {{ $nextVideo->title }}
                        </p>
                        <p class="mt-3 flex flex-wrap items-baseline gap-x-2 text-sm text-slate-600 dark:text-slate-400">
                            <span>Playing in</span>
                            <span
                                id="up-next-seconds"
                                class="badge-accent-lg"
                                aria-live="polite"
                                aria-atomic="true"
                            >{{ 5 }}</span>
                            <span class="text-slate-600 dark:text-slate-500">seconds</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2 sm:flex-col sm:items-stretch">
                        <button
                            type="button"
                            id="up-next-cancel"
                            class="btn-secondary flex-1 px-4 py-2.5 text-sm font-semibold sm:flex-none sm:min-w-34"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            id="up-next-now"
                            class="btn-primary flex-1 px-4 py-2.5 text-sm font-semibold sm:flex-none sm:min-w-34"
                        >
                            Play now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div
        id="course-complete-toast"
        class="course-complete-toast hidden"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >
        <p class="course-complete-toast__eyebrow">Course complete</p>
        <p id="course-complete-toast-title" class="course-complete-toast__title"></p>
        <a
            id="course-complete-toast-link"
            href="{{ route('courses.show', $video->course) }}"
            class="course-complete-toast__link"
        >
            View course overview →
        </a>
    </div>

    <script>
        window.__COURSE_PLAYER__ = {
            videoId: {{ $video->id }},
            progressUrl: @json(route('videos.progress', $video)),
            initialPosition: {{ json_encode($initialPosition) }},
            nextUrl: @json($nextVideo ? route('videos.show', $nextVideo) : null),
            nextTitle: @json($nextVideo ? $nextVideo->title : null),
            courseTitle: @json($video->course->title),
            courseUrl: @json(route('courses.show', $video->course)),
        };
        document.getElementById('lesson-sidebar-active')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    </script>
@endsection
