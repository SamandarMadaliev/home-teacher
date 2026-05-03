@extends('layouts.app')

@section('main_max_class', 'max-w-7xl')

@section('title', $video->title.' — '.config('app.name'))

@push('head')
    @vite(['resources/js/player.js'])
@endpush

@section('content')
    <div id="watch-layout" class="watch-layout">
        <div class="watch-player-stack min-w-0">
            <div class="mb-8">
                <a href="{{ route('courses.show', $video->course) }}" class="inline-flex items-center gap-1 text-sm font-medium text-sky-400/95 transition hover:text-sky-300">
                    <span aria-hidden="true">←</span> {{ $video->course->title }}
                </a>
                <div
                    class="lesson-rename mt-5 max-w-3xl {{ $errors->has('title') ? 'lesson-rename--editing' : '' }}"
                    data-lesson-rename
                >
                    <div class="lesson-rename-view flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <h1 class="min-w-0 flex-1 text-2xl font-bold tracking-tight text-slate-50 sm:text-3xl">
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
                            class="input-field w-full text-xl font-bold tracking-tight text-slate-50 sm:text-2xl"
                            autocomplete="off"
                        />
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="btn-primary px-5 py-2 text-sm">Save</button>
                            <button type="button" class="lesson-rename-cancel btn-secondary px-5 py-2 text-sm">Cancel</button>
                        </div>
                        <p class="text-[0.65rem] text-slate-500">
                            <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono ring-1 ring-slate-700">Esc</kbd>
                            cancels without saving.
                        </p>
                    </form>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    Wider player and lesson list below — shortcut <kbd class="rounded bg-slate-800 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-400 ring-1 ring-slate-700">T</kbd>
                </p>
                <button
                    type="button"
                    id="theater-mode-toggle"
                    class="btn-secondary shrink-0 py-2 text-xs font-semibold sm:text-sm"
                    aria-pressed="false"
                    data-label-off="Theater mode"
                    data-label-on="Exit theater"
                >
                    Theater mode
                </button>
            </div>

            <div class="course-plyr overflow-hidden rounded-2xl border border-slate-800/90 bg-black shadow-2xl shadow-blue-950/40 ring-1 ring-sky-950/40">
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

            <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-slate-400">
                <span class="inline-flex items-center gap-2">
                    <kbd class="rounded-md bg-slate-800 px-2 py-1 font-mono text-[0.7rem] text-slate-300 ring-1 ring-slate-700">Space</kbd>
                    play / pause
                </span>
                <span class="inline-flex items-center gap-2">
                    <kbd class="rounded-md bg-slate-800 px-2 py-1 font-mono text-[0.7rem] text-slate-300 ring-1 ring-slate-700">←</kbd>
                    −10s
                </span>
                <span class="inline-flex items-center gap-2">
                    <kbd class="rounded-md bg-slate-800 px-2 py-1 font-mono text-[0.7rem] text-slate-300 ring-1 ring-slate-700">→</kbd>
                    +10s
                </span>
                <span class="text-slate-500">
                    Player:
                    <a href="https://github.com/sampotts/plyr" class="text-sky-400/90 underline decoration-sky-600/40 hover:text-sky-300" target="_blank" rel="noopener noreferrer">Plyr</a>
                </span>
            </div>
        </div>

        <aside class="watch-lessons-sidebar w-full shrink-0 xl:w-auto xl:min-w-[18rem]" aria-label="Course lessons">
            <div class="card-surface overflow-hidden xl:sticky xl:top-28">
                <div class="border-b border-slate-800/90 bg-slate-900/40 px-4 py-4">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-sky-400/90">Lessons</p>
                    <p class="mt-1.5 truncate text-sm font-semibold text-slate-100">{{ $video->course->title }}</p>
                    <a href="{{ route('courses.show', $video->course) }}" class="mt-2 inline-block text-xs font-medium text-sky-400/90 hover:text-sky-300 hover:underline">
                        Course overview →
                    </a>
                </div>
                <ol class="max-h-[min(70vh,38rem)] divide-y divide-slate-800/90 overflow-y-auto overscroll-contain">
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
                                    ? 'bg-sky-950/45 ring-1 ring-inset ring-sky-500/35'
                                    : 'hover:bg-slate-800/55' }} flex gap-3 px-4 py-3 transition"
                            >
                                <span class="mt-0.5 flex h-7 min-w-[1.75rem] items-center justify-center rounded-lg bg-slate-800/90 text-[0.65rem] font-semibold tabular-nums text-sky-300/90 ring-1 ring-slate-700/80">
                                    {{ $lesson->sort_order }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium {{ $active ? 'text-white' : 'text-slate-200' }}">{{ $lesson->title }}</span>
                                    @if ($p?->completed && ! $active)
                                        <span class="mt-0.5 block text-[0.65rem] font-medium text-emerald-400/85">Done</span>
                                    @endif
                                    <span class="mt-2 flex items-center gap-2">
                                        <span class="h-1 flex-1 overflow-hidden rounded-full bg-slate-800 ring-1 ring-slate-900/80">
                                            <span class="block h-full rounded-full bg-gradient-to-r from-sky-500 to-blue-500 transition-[width]" style="width: {{ $pct }}%"></span>
                                        </span>
                                        <span class="shrink-0 text-[0.65rem] tabular-nums text-slate-500">{{ $pct }}%</span>
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
            <nav class="grid grid-cols-1 gap-4 sm:grid-cols-2" aria-label="Lesson navigation">
                @if ($previousVideo)
                    <a
                        href="{{ route('videos.show', $previousVideo) }}"
                        class="group flex min-h-[5.75rem] flex-col justify-center rounded-2xl border border-sky-900/45 bg-gradient-to-br from-slate-900/92 to-sky-950/28 px-5 py-4 ring-1 ring-sky-950/35 transition hover:border-sky-700/55 hover:ring-sky-800/45"
                    >
                        <p class="text-xs font-medium uppercase tracking-wider text-sky-400/85 transition group-hover:text-sky-300/95">
                            ← Previous lesson
                        </p>
                        <p class="mt-2 line-clamp-2 text-lg font-semibold text-slate-100 decoration-sky-600/50 underline-offset-4 transition group-hover:text-white group-hover:underline">
                            {{ $previousVideo->title }}
                        </p>
                    </a>
                @else
                    <div
                        class="pointer-events-none flex min-h-[5.75rem] select-none flex-col justify-center rounded-2xl border border-sky-900/25 bg-gradient-to-br from-slate-900/55 to-sky-950/15 px-5 py-4 opacity-60 ring-1 ring-sky-950/20"
                        aria-disabled="true"
                    >
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500">← Previous lesson</p>
                        <p class="mt-2 text-sm font-medium text-slate-500">First lesson — nothing before this</p>
                    </div>
                @endif

                @if ($nextVideo)
                    <a
                        href="{{ route('videos.show', $nextVideo) }}"
                        class="group flex min-h-[5.75rem] flex-col justify-center rounded-2xl border border-sky-900/45 bg-gradient-to-br from-slate-900/92 to-sky-950/28 px-5 py-4 ring-1 ring-sky-950/35 transition hover:border-sky-700/55 hover:ring-sky-800/45"
                    >
                        <p class="text-xs font-medium uppercase tracking-wider text-sky-400/85 transition group-hover:text-sky-300/95">
                            Next lesson →
                        </p>
                        <p class="mt-2 line-clamp-2 text-lg font-semibold text-slate-100 decoration-sky-600/50 underline-offset-4 transition group-hover:text-white group-hover:underline">
                            {{ $nextVideo->title }}
                        </p>
                    </a>
                @else
                    <div
                        class="pointer-events-none flex min-h-[5.75rem] select-none flex-col justify-center rounded-2xl border border-sky-900/25 bg-gradient-to-br from-slate-900/55 to-sky-950/15 px-5 py-4 opacity-60 ring-1 ring-sky-950/20"
                        aria-disabled="true"
                    >
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Next lesson →</p>
                        <p class="mt-2 text-sm font-medium text-slate-500">Last lesson — nothing after this</p>
                    </div>
                @endif
            </nav>

            <section class="mt-10" aria-labelledby="lesson-notes-heading">
                <h2 id="lesson-notes-heading" class="text-lg font-semibold tracking-tight text-slate-100">
                    Notes
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Add a general note for this lesson, or capture the playhead as a time-stamped note and jump back to it later.
                </p>

                <form
                    action="{{ route('videos.notes.store', $video) }}"
                    method="post"
                    class="mt-5 rounded-2xl border border-slate-800/90 bg-slate-900/35 px-4 py-5 ring-1 ring-slate-800/70 sm:px-5"
                >
                    @csrf
                    <label for="note-body" class="sr-only">Note</label>
                    <textarea
                        id="note-body"
                        name="body"
                        rows="4"
                        required
                        placeholder="Write your note here…"
                        class="w-full rounded-xl border border-slate-700/80 bg-slate-950/50 px-3 py-2.5 text-sm text-slate-100 shadow-inner ring-0 placeholder:text-slate-600 focus:border-sky-600/60 focus:outline-none focus:ring-2 focus:ring-sky-500/30"
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
                    <p id="note-timestamp-label" class="mt-2 hidden text-xs text-slate-500" aria-live="polite"></p>

                    <div class="mt-4">
                        <button type="submit" class="btn-primary text-sm">Save note</button>
                    </div>
                </form>

                @if ($video->notes->isEmpty())
                    <p class="mt-6 text-sm text-slate-500">No notes for this lesson yet.</p>
                @else
                    <ul class="mt-6 space-y-3" role="list">
                        @foreach ($video->notes as $note)
                            <li class="rounded-2xl border border-slate-800/90 bg-slate-900/30 px-4 py-3.5 ring-1 ring-slate-800/60 sm:px-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        @if ($note->timestamp_seconds !== null)
                                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-lg bg-sky-950/55 px-2.5 py-1 font-mono text-xs tabular-nums font-semibold text-sky-300 ring-1 ring-sky-600/35 transition hover:bg-sky-900/55 hover:text-sky-200"
                                                    data-note-seek="{{ $note->timestamp_seconds }}"
                                                    aria-label="Jump to {{ $note->timestampLabel() }} in this video"
                                                >
                                                    {{ $note->timestampLabel() }}
                                                </button>
                                                <span class="text-[0.65rem] uppercase tracking-wider text-slate-500">Cue</span>
                                            </div>
                                        @else
                                            <p class="mb-2 text-[0.65rem] font-medium uppercase tracking-wider text-slate-500">Lesson note</p>
                                        @endif
                                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-200">{{ $note->body }}</p>
                                    </div>
                                    <form
                                        action="{{ route('videos.notes.destroy', [$video, $note]) }}"
                                        method="post"
                                        class="shrink-0"
                                        onsubmit="return confirm('Remove this note?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-slate-500 underline decoration-slate-600/60 underline-offset-2 hover:text-rose-400 hover:decoration-rose-500/50">
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
            class="up-next-overlay fixed inset-0 z-[200] flex flex-col justify-end p-4 sm:p-6"
            aria-hidden="true"
            aria-labelledby="up-next-heading"
            role="dialog"
        >
            <button
                type="button"
                id="up-next-backdrop"
                class="absolute inset-0 bg-slate-950/65 backdrop-blur-[3px] transition hover:bg-slate-950/75"
                tabindex="-1"
                aria-label="Dismiss — stay on this lesson"
            ></button>
            <div
                class="relative z-10 mx-auto mb-2 w-full max-w-lg overflow-hidden rounded-2xl border border-slate-700/90 bg-slate-900/95 shadow-2xl shadow-black/50 ring-1 ring-white/10 sm:mb-4"
            >
                <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6 sm:py-5">
                    <div class="min-w-0 flex-1">
                        <p id="up-next-heading" class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-sky-400/95">
                            Up next
                        </p>
                        <p id="up-next-title" class="mt-2 line-clamp-2 text-base font-semibold leading-snug text-white sm:text-lg">
                            {{ $nextVideo->title }}
                        </p>
                        <p class="mt-3 flex flex-wrap items-baseline gap-x-2 text-sm text-slate-400">
                            <span>Playing in</span>
                            <span
                                id="up-next-seconds"
                                class="inline-flex min-w-[2.25rem] items-center justify-center rounded-lg bg-sky-950/80 px-2 py-1 font-mono text-xl font-bold tabular-nums text-sky-300 ring-1 ring-sky-600/40"
                                aria-live="polite"
                                aria-atomic="true"
                            >{{ 5 }}</span>
                            <span class="text-slate-500">seconds</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2 sm:flex-col sm:items-stretch">
                        <button
                            type="button"
                            id="up-next-cancel"
                            class="btn-secondary flex-1 px-4 py-2.5 text-sm font-semibold sm:flex-none sm:min-w-[8.5rem]"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            id="up-next-now"
                            class="btn-primary flex-1 px-4 py-2.5 text-sm font-semibold sm:flex-none sm:min-w-[8.5rem]"
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
