@extends('layouts.app')

@section('main_max_class', 'max-w-7xl')

@section('title', $video->title.' — '.config('app.name'))

@push('head')
    @vite(['resources/js/player.js'])
@endpush

@section('content')
    <div id="watch-layout" class="watch-layout flex flex-col gap-10 xl:flex-row xl:items-start xl:gap-10">
        <div class="watch-video-column min-w-0 flex-1">
            <div class="mb-8">
                <a href="{{ route('courses.show', $video->course) }}" class="inline-flex items-center gap-1 text-sm font-medium text-sky-400/95 transition hover:text-sky-300">
                    <span aria-hidden="true">←</span> {{ $video->course->title }}
                </a>
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-50 sm:text-3xl">{{ $video->title }}</h1>
            </div>

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    Wider layout without fullscreen — shortcut <kbd class="rounded bg-slate-800 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-400 ring-1 ring-slate-700">T</kbd>
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

            @if ($nextVideo)
                <div class="mt-8 rounded-2xl border border-sky-900/45 bg-gradient-to-r from-slate-900/90 to-sky-950/35 px-5 py-4 ring-1 ring-sky-950/35">
                    <p class="text-xs font-medium uppercase tracking-wider text-sky-400/85">Next lesson</p>
                    <p class="mt-2">
                        <a href="{{ route('videos.show', $nextVideo) }}" class="text-lg font-semibold text-slate-100 transition hover:text-white hover:underline decoration-sky-600/60 underline-offset-4">
                            {{ $nextVideo->title }}
                        </a>
                    </p>
                </div>
            @endif
        </div>

        <aside class="watch-lessons-sidebar w-full shrink-0 xl:w-80 xl:min-w-[18rem]" aria-label="Course lessons">
            <div class="card-surface sticky top-24 overflow-hidden xl:top-28">
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
    </div>

    <script>
        window.__COURSE_PLAYER__ = {
            videoId: {{ $video->id }},
            progressUrl: @json(route('videos.progress', $video)),
            initialPosition: {{ json_encode($initialPosition) }},
            nextUrl: @json($nextVideo ? route('videos.show', $nextVideo) : null),
        };
        document.getElementById('lesson-sidebar-active')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    </script>
@endsection
