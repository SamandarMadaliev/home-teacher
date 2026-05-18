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
                class="watch-hero rounded-3xl border border-slate-200/95 bg-gradient-to-br from-white via-white to-sky-50/55 p-5 shadow-lg shadow-slate-300/25 ring-1 ring-sky-100/45 dark:border-slate-800/90 dark:from-slate-900/88 dark:via-slate-900/75 dark:to-sky-950/30 dark:shadow-black/25 dark:ring-sky-950/30 sm:p-6"
                aria-label="This lesson"
            >
                <a href="{{ route('courses.show', $video->course) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-sky-600 transition hover:text-sky-700 dark:text-sky-400/95 dark:hover:text-sky-300">
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
                    <div class="lesson-rename-view flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <h1 class="min-w-0 flex-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-50 sm:text-3xl">
                            {{ $video->title }}
                        </h1>
                        <button
                            type="button"
                            class="lesson-rename-trigger shrink-0 self-start sm:mt-1"
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
                            <span class="tabular-nums font-semibold text-sky-700 dark:text-sky-300/95">{{ $lessonPct }}%</span>
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
                                class="h-full rounded-full bg-gradient-to-r from-sky-500 to-blue-500 transition-[width] duration-300"
                                style="width: {{ $lessonPct }}%"
                            ></div>
                        </div>
                    </div>
                @endif
            </section>

            <div class="watch-player-toolbar mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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

            <div class="course-plyr mt-4 overflow-hidden rounded-2xl border border-slate-300/95 bg-black shadow-2xl shadow-slate-400/45 ring-1 ring-sky-500/25 dark:border-slate-800/90 dark:shadow-blue-950/40 dark:ring-sky-950/40">
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
                <a href="https://github.com/sampotts/plyr" class="text-sky-600 underline decoration-sky-500/45 hover:text-sky-800 dark:text-sky-400/90 dark:hover:text-sky-300" target="_blank" rel="noopener noreferrer">Plyr</a>
            </p>
        </div>

        <aside class="watch-lessons-sidebar w-full shrink-0 xl:w-auto xl:min-w-[18rem]" aria-label="Course lessons">
            <div class="card-surface overflow-hidden xl:sticky xl:top-28">
                <div class="border-b border-slate-200/95 bg-gradient-to-r from-slate-50 to-sky-50/80 px-4 py-4 dark:border-slate-800/90 dark:from-slate-900/80 dark:to-sky-950/25">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-sky-700 dark:text-sky-400/90">Playlist</p>
                    <p class="mt-1.5 truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $video->course->title }}</p>
                    <p class="mt-1 text-xs tabular-nums text-slate-600 dark:text-slate-400">{{ $totalLessons }} {{ $totalLessons === 1 ? 'lesson' : 'lessons' }}</p>
                    <a href="{{ route('courses.show', $video->course) }}" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-sky-600 transition hover:text-sky-800 hover:underline dark:text-sky-400/90 dark:hover:text-sky-300">
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
                                    ? 'bg-sky-100/98 ring-1 ring-inset ring-sky-500/42 dark:bg-sky-950/45 dark:ring-sky-500/35'
                                    : 'hover:bg-slate-100/96 dark:hover:bg-slate-800/55' }} flex gap-3 px-4 py-3.5 transition"
                            >
                                <span class="mt-0.5 flex h-7 min-w-7 items-center justify-center rounded-lg bg-slate-100 text-[0.65rem] font-semibold tabular-nums text-sky-700 ring-1 ring-slate-300/95 dark:bg-slate-800/90 dark:text-sky-300/90 dark:ring-slate-700/80">
                                    {{ $lesson->sort_order }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium {{ $active ? 'text-slate-900 dark:text-white' : 'text-slate-700 dark:text-slate-200' }}">{{ $lesson->title }}</span>
                                    @if ($p?->completed && ! $active)
                                        <span class="mt-0.5 block text-[0.65rem] font-medium text-emerald-700 dark:text-emerald-400/85">Done</span>
                                    @endif
                                    <span class="mt-2 flex items-center gap-2">
                                        <span class="h-1 flex-1 overflow-hidden rounded-full bg-slate-200 ring-1 ring-slate-300/95 dark:bg-slate-800 dark:ring-slate-900/80">
                                            <span class="block h-full rounded-full bg-gradient-to-r from-sky-500 to-blue-500 transition-[width]" style="width: {{ $pct }}%"></span>
                                        </span>
                                        <span class="shrink-0 text-[0.65rem] tabular-nums text-slate-600 dark:text-slate-500">{{ $pct }}%</span>
                                    </span>
                                </span>
                                @if ($active)
                                    <span class="sr-only">(playing)</span>
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-sky-400 shadow-[0_0_10px_rgba(56,189,248,0.6)]" aria-hidden="true"></span>
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
                        class="watch-nav-card group flex min-h-23 flex-col justify-center rounded-2xl border border-sky-300/90 bg-gradient-to-br from-white to-sky-50/95 px-5 py-4 ring-1 ring-sky-300/45 transition hover:border-sky-500/55 hover:ring-sky-500/40 dark:border-sky-900/45 dark:from-slate-900/92 dark:to-sky-950/28 dark:ring-sky-950/35 dark:hover:border-sky-700/55 dark:hover:ring-sky-800/45"
                    >
                        <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-400/85">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 shrink-0" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                            Previous lesson
                        </span>
                        <p class="mt-2 line-clamp-2 text-base font-semibold text-slate-900 decoration-sky-600/42 underline-offset-4 transition group-hover:text-sky-950 group-hover:underline dark:text-slate-100 dark:decoration-sky-600/50 dark:group-hover:text-white sm:text-lg">
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
                        class="watch-nav-card group flex min-h-23 flex-col justify-center rounded-2xl border border-sky-300/90 bg-gradient-to-br from-white to-sky-50/95 px-5 py-4 ring-1 ring-sky-300/45 transition hover:border-sky-500/55 hover:ring-sky-500/40 dark:border-sky-900/45 dark:from-slate-900/92 dark:to-sky-950/28 dark:ring-sky-950/35 dark:hover:border-sky-700/55 dark:hover:ring-sky-800/45"
                    >
                        <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-400/85">
                            Next lesson
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 shrink-0" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </span>
                        <p class="mt-2 line-clamp-2 text-base font-semibold text-slate-900 decoration-sky-600/42 underline-offset-4 transition group-hover:text-sky-950 group-hover:underline dark:text-slate-100 dark:decoration-sky-600/50 dark:group-hover:text-white sm:text-lg">
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

            <section class="watch-content-panel mt-10 rounded-3xl border border-slate-200/95 bg-white/90 p-5 shadow-lg shadow-slate-300/20 ring-1 ring-slate-200/85 dark:border-slate-800/90 dark:bg-slate-900/55 dark:shadow-black/20 dark:ring-slate-800/70 sm:p-6" aria-labelledby="lesson-resources-heading">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/90 pb-4 dark:border-slate-800/85">
                    <div class="min-w-0">
                        <h2 id="lesson-resources-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                            Resources
                        </h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-500">
                            Point at files already on disk (absolute path or relative to the course folder) or save external links — nothing is copied or uploaded.
                        </p>
                    </div>
                    @if (! $video->attachments->isEmpty())
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-slate-700 ring-1 ring-slate-200/95 dark:bg-slate-800/90 dark:text-slate-300 dark:ring-slate-700/80">
                            {{ $video->attachments->count() }} attached
                        </span>
                    @endif
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <form
                        action="{{ route('videos.attachments.store', $video) }}"
                        method="post"
                        class="rounded-2xl border border-slate-200/95 bg-slate-50/96 px-4 py-5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-950/35 dark:ring-slate-800/70"
                    >
                        @csrf
                        <input type="hidden" name="kind" value="file" />
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-sky-700 dark:text-sky-400/90">File on disk</p>

                        <label for="attachment-file-title" class="mt-3 block text-xs font-medium text-slate-700 dark:text-slate-300">Label <span class="font-normal text-slate-500 dark:text-slate-500">(optional)</span></label>
                        <input
                            id="attachment-file-title"
                            type="text"
                            name="title"
                            maxlength="255"
                            placeholder="e.g. Slides — Chapter 3"
                            value="{{ old('kind') === 'file' ? old('title') : '' }}"
                            class="input-field mt-1.5 text-sm"
                            autocomplete="off"
                        />

                        <label for="attachment-file-path" class="mt-3 block text-xs font-medium text-slate-700 dark:text-slate-300">Path to file</label>
                        <input
                            id="attachment-file-path"
                            type="text"
                            name="file_path"
                            required
                            spellcheck="false"
                            placeholder="e.g. slides/chapter-3.pdf or /Users/me/notes/x.pdf"
                            value="{{ old('kind') === 'file' ? old('file_path') : '' }}"
                            class="input-field mt-1.5 font-mono text-xs"
                            autocomplete="off"
                        />
                        <p class="mt-1.5 text-[0.65rem] leading-relaxed text-slate-600 dark:text-slate-500">
                            Absolute path, or relative to the course folder. We only store the path and read the file when you open it.
                        </p>

                        <div class="mt-4">
                            <button type="submit" class="btn-primary text-sm">Attach file</button>
                        </div>
                    </form>

                    <form
                        action="{{ route('videos.attachments.store', $video) }}"
                        method="post"
                        class="rounded-2xl border border-slate-200/95 bg-slate-50/96 px-4 py-5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-950/35 dark:ring-slate-800/70"
                    >
                        @csrf
                        <input type="hidden" name="kind" value="link" />
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-emerald-800 dark:text-emerald-400/90">External link</p>

                        <label for="attachment-link-title" class="mt-3 block text-xs font-medium text-slate-700 dark:text-slate-300">Label <span class="font-normal text-slate-500 dark:text-slate-500">(optional)</span></label>
                        <input
                            id="attachment-link-title"
                            type="text"
                            name="title"
                            maxlength="255"
                            placeholder="e.g. Official docs"
                            value="{{ old('kind') === 'link' ? old('title') : '' }}"
                            class="input-field mt-1.5 text-sm"
                            autocomplete="off"
                        />

                        <label for="attachment-link-url" class="mt-3 block text-xs font-medium text-slate-700 dark:text-slate-300">URL</label>
                        <input
                            id="attachment-link-url"
                            type="url"
                            name="url"
                            required
                            inputmode="url"
                            placeholder="https://…"
                            value="{{ old('kind') === 'link' ? old('url') : '' }}"
                            class="input-field mt-1.5 text-sm"
                            autocomplete="off"
                        />

                        <div class="mt-4">
                            <button type="submit" class="btn-primary text-sm">Save link</button>
                        </div>
                    </form>
                </div>

                @if ($video->attachments->isEmpty())
                    <p class="mt-6 rounded-xl border border-dashed border-slate-300/90 bg-slate-50/70 px-4 py-6 text-center text-sm text-slate-600 dark:border-slate-700/80 dark:bg-slate-950/30 dark:text-slate-500">
                        No resources for this lesson yet.
                    </p>
                @else
                    <ul class="mt-6 space-y-2.5" role="list">
                        @foreach ($video->attachments as $attachment)
                            <li class="rounded-2xl border border-slate-200/96 bg-white px-4 py-3.5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-900/40 dark:ring-slate-800/60">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        @if ($attachment->isFile())
                                            <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700 ring-1 ring-sky-300/60 dark:bg-sky-950/55 dark:text-sky-300 dark:ring-sky-700/40" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                            </span>
                                        @else
                                            <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 ring-1 ring-emerald-300/60 dark:bg-emerald-950/55 dark:text-emerald-300 dark:ring-emerald-700/40" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                                </svg>
                                            </span>
                                        @endif

                                        <div class="min-w-0 flex-1">
                                            @if ($attachment->isFile())
                                                @php $fileAvailable = $attachment->isAvailable(); @endphp
                                                @if ($fileAvailable)
                                                    <a
                                                        href="{{ route('videos.attachments.download', [$video, $attachment]) }}"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="block break-words text-sm font-semibold text-sky-700 underline decoration-sky-500/40 underline-offset-2 hover:text-sky-800 hover:decoration-sky-600/70 dark:text-sky-400/95 dark:decoration-sky-500/45 dark:hover:text-sky-300"
                                                    >
                                                        {{ $attachment->title }}
                                                    </a>
                                                @else
                                                    <p class="block break-words text-sm font-semibold text-slate-600 line-through decoration-rose-400/70 dark:text-slate-400" title="File not found at the recorded path">
                                                        {{ $attachment->title }}
                                                    </p>
                                                @endif
                                                <p class="mt-1 truncate font-mono text-[0.7rem] text-slate-600 dark:text-slate-500" title="{{ $attachment->file_path }}">
                                                    {{ $attachment->fileBasename() ?? $attachment->file_path }}
                                                    @if ($attachment->sizeLabel())
                                                        <span aria-hidden="true">·</span> <span class="tabular-nums">{{ $attachment->sizeLabel() }}</span>
                                                    @endif
                                                </p>
                                                @if (! $fileAvailable)
                                                    <p class="mt-1 text-[0.65rem] font-medium text-rose-700 dark:text-rose-300">
                                                        File missing — the path no longer points to a readable file.
                                                    </p>
                                                @endif
                                            @else
                                                <a
                                                    href="{{ $attachment->url }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="block break-words text-sm font-semibold text-sky-700 underline decoration-sky-500/40 underline-offset-2 hover:text-sky-800 hover:decoration-sky-600/70 dark:text-sky-400/95 dark:decoration-sky-500/45 dark:hover:text-sky-300"
                                                >
                                                    {{ $attachment->title }}
                                                </a>
                                                <p class="mt-1 truncate text-[0.7rem] text-slate-600 dark:text-slate-500">
                                                    @if ($attachment->linkHost())
                                                        {{ $attachment->linkHost() }} <span aria-hidden="true">·</span>
                                                    @endif
                                                    <span class="break-all text-slate-500 dark:text-slate-500">{{ $attachment->url }}</span>
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    <form
                                        action="{{ route('videos.attachments.destroy', [$video, $attachment]) }}"
                                        method="post"
                                        class="shrink-0"
                                        onsubmit="return confirm('Remove this attachment?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-slate-600 underline decoration-slate-500/62 underline-offset-2 hover:text-rose-600 hover:decoration-rose-500/55 dark:text-slate-500 dark:decoration-slate-600/60 dark:hover:text-rose-400 dark:hover:decoration-rose-500/50">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="watch-content-panel mt-8 rounded-3xl border border-slate-200/95 bg-white/90 p-5 shadow-lg shadow-slate-300/20 ring-1 ring-slate-200/85 dark:border-slate-800/90 dark:bg-slate-900/55 dark:shadow-black/20 dark:ring-slate-800/70 sm:p-6" aria-labelledby="lesson-notes-heading">
                <div class="border-b border-slate-200/90 pb-4 dark:border-slate-800/85">
                    <h2 id="lesson-notes-heading" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                        Notes
                    </h2>
                    <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-500">
                        General notes for this lesson, or time-stamped cues you can jump to from the list below.
                    </p>
                </div>

                <form
                    action="{{ route('videos.notes.store', $video) }}"
                    method="post"
                    class="mt-5 rounded-2xl border border-slate-200/95 bg-slate-50/96 px-4 py-5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-950/35 dark:ring-slate-800/70"
                    data-note-editor
                >
                    @csrf
                    <label for="note-body" class="sr-only">Note</label>

                    <div
                        class="note-toolbar flex flex-wrap items-center gap-0.5 rounded-t-xl border border-b-0 border-slate-300/95 bg-white/95 px-1.5 py-1 dark:border-slate-700/80 dark:bg-slate-900/70"
                        role="toolbar"
                        aria-label="Format note"
                        data-note-toolbar
                    >
                        <button type="button" data-md="bold" class="note-toolbar-btn" title="Bold (⌘/Ctrl+B)" aria-label="Bold">
                            <span class="font-bold">B</span>
                        </button>
                        <button type="button" data-md="italic" class="note-toolbar-btn" title="Italic (⌘/Ctrl+I)" aria-label="Italic">
                            <span class="italic font-serif">I</span>
                        </button>
                        <button type="button" data-md="strike" class="note-toolbar-btn" title="Strikethrough" aria-label="Strikethrough">
                            <span class="line-through">S</span>
                        </button>
                        <button type="button" data-md="code" class="note-toolbar-btn" title="Inline code (⌘/Ctrl+E)" aria-label="Inline code">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5-4.5 4.5 4.5 4.5m10.5-9 4.5 4.5-4.5 4.5m-3.75-13.5-3 16.5" />
                            </svg>
                        </button>
                        <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700/80" aria-hidden="true"></span>
                        <button type="button" data-md="heading" class="note-toolbar-btn" title="Heading" aria-label="Heading">
                            <span class="font-bold">H</span>
                        </button>
                        <button type="button" data-md="ul" class="note-toolbar-btn" title="Bulleted list" aria-label="Bulleted list">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.008v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.008v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </button>
                        <button type="button" data-md="ol" class="note-toolbar-btn" title="Numbered list" aria-label="Numbered list">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.242 5.992h12m-12 6.003H20.24m-12 5.999h12M4.117 7.495v-3.75H2.99m1.125 3.75H2.99m1.125 0H5.24m-1.92 2.577a1.125 1.125 0 1 1 1.591 1.59l-1.83 1.83h2.16M2.99 15.745h1.125a1.125 1.125 0 0 1 0 2.25H3.74m0-.002.384.001h.376a1.125 1.125 0 1 1 0 2.25H2.99" />
                            </svg>
                        </button>
                        <button type="button" data-md="quote" class="note-toolbar-btn" title="Quote" aria-label="Quote">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5" />
                            </svg>
                        </button>
                        <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700/80" aria-hidden="true"></span>
                        <button type="button" data-md="link" class="note-toolbar-btn" title="Link (⌘/Ctrl+K)" aria-label="Link">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                        </button>
                        <span class="ml-auto hidden pr-1 text-[10px] font-mono text-slate-500 dark:text-slate-500 sm:inline">Markdown</span>
                    </div>

                    <textarea
                        id="note-body"
                        name="body"
                        rows="5"
                        required
                        placeholder="Write your note here… Markdown supported: **bold**, *italic*, `code`, lists, links."
                        class="note-textarea block w-full resize-y rounded-b-xl border border-slate-300/95 bg-white px-3 py-2.5 font-sans text-sm text-slate-900 shadow-inner shadow-slate-200/60 transition placeholder:text-slate-500 focus:border-sky-500/80 focus:outline-none focus:ring-2 focus:ring-sky-400/35 dark:border-slate-700/80 dark:bg-slate-950/70 dark:text-slate-100 dark:shadow-black/25 dark:placeholder:text-slate-500 dark:focus:border-sky-600/70 dark:focus:ring-sky-500/35"
                        data-note-input
                    >{{ old('body') }}</textarea>

                    <input type="hidden" name="timestamp_seconds" id="note-timestamp-input" value="{{ old('timestamp_seconds') }}" />

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            id="note-at-current-time"
                            class="btn-secondary py-2 text-xs font-semibold"
                        >
                            Use current play time
                        </button>
                        <button
                            type="button"
                            id="note-clear-timestamp"
                            class="btn-secondary py-2 text-xs font-semibold"
                        >
                            Lesson note (no time)
                        </button>
                    </div>
                    <p id="note-timestamp-label" class="mt-2 hidden text-xs text-slate-600 dark:text-slate-500" aria-live="polite"></p>

                    <div class="mt-4">
                        <button type="submit" class="btn-primary text-sm">Save note</button>
                    </div>
                </form>

                @if ($video->notes->isEmpty())
                    <p class="mt-6 rounded-xl border border-dashed border-slate-300/90 bg-slate-50/70 px-4 py-6 text-center text-sm text-slate-600 dark:border-slate-700/80 dark:bg-slate-950/30 dark:text-slate-500">
                        No notes for this lesson yet.
                    </p>
                @else
                    <ul class="mt-6 space-y-2.5" role="list">
                        @foreach ($video->notes as $note)
                            <li class="rounded-2xl border border-slate-200/96 bg-white px-4 py-3.5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-900/40 dark:ring-slate-800/60">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        @if ($note->timestamp_seconds !== null)
                                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-lg bg-sky-100 px-2.5 py-1 font-mono text-xs tabular-nums font-semibold text-sky-800 ring-1 ring-sky-400/45 transition hover:bg-sky-200/92 hover:text-sky-950 dark:bg-sky-950/55 dark:text-sky-300 dark:ring-sky-600/35 dark:hover:bg-sky-900/55 dark:hover:text-sky-200"
                                                    data-note-seek="{{ $note->timestamp_seconds }}"
                                                    aria-label="Jump to {{ $note->timestampLabel() }} in this video"
                                                >
                                                    {{ $note->timestampLabel() }}
                                                </button>
                                                <span class="text-[0.65rem] uppercase tracking-wider text-slate-600 dark:text-slate-500">Cue</span>
                                            </div>
                                        @else
                                            <p class="mb-2 text-[0.65rem] font-medium uppercase tracking-wider text-slate-600 dark:text-slate-500">Lesson note</p>
                                        @endif
                                        <div class="note-prose text-sm leading-relaxed text-slate-800 dark:text-slate-200">{!! $note->bodyHtml() !!}</div>
                                    </div>
                                    <form
                                        action="{{ route('videos.notes.destroy', [$video, $note]) }}"
                                        method="post"
                                        class="shrink-0"
                                        onsubmit="return confirm('Remove this note?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-slate-600 underline decoration-slate-500/62 underline-offset-2 hover:text-rose-600 hover:decoration-rose-500/55 dark:text-slate-500 dark:decoration-slate-600/60 dark:hover:text-rose-400 dark:hover:decoration-rose-500/50">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
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
                class="relative z-10 mx-auto mb-2 w-full max-w-lg overflow-hidden rounded-2xl border border-slate-300/92 bg-white/96 shadow-2xl shadow-slate-400/30 ring-1 ring-sky-950/12 sm:mb-4 dark:border-slate-700/90 dark:bg-slate-900/95 dark:shadow-black/50 dark:ring-white/10"
            >
                <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6 sm:py-5">
                    <div class="min-w-0 flex-1">
                        <p id="up-next-heading" class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-400/95">
                            Up next
                        </p>
                        <p id="up-next-title" class="mt-2 line-clamp-2 text-base font-semibold leading-snug text-slate-900 dark:text-white sm:text-lg">
                            {{ $nextVideo->title }}
                        </p>
                        <p class="mt-3 flex flex-wrap items-baseline gap-x-2 text-sm text-slate-600 dark:text-slate-400">
                            <span>Playing in</span>
                            <span
                                id="up-next-seconds"
                                class="inline-flex min-w-9 items-center justify-center rounded-lg bg-sky-100 px-2 py-1 font-mono text-xl font-bold tabular-nums text-sky-800 ring-1 ring-sky-500/52 dark:bg-sky-950/80 dark:text-sky-300 dark:ring-sky-600/40"
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

    <script>
        window.__COURSE_PLAYER__ = {
            videoId: {{ $video->id }},
            progressUrl: @json(route('videos.progress', $video)),
            initialPosition: {{ json_encode($initialPosition) }},
            nextUrl: @json($nextVideo ? route('videos.show', $nextVideo) : null),
            nextTitle: @json($nextVideo ? $nextVideo->title : null),
        };
        document.getElementById('lesson-sidebar-active')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    </script>
@endsection
