@extends('layouts.app')

@section('title', $course->title.' — '.config('app.name'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('courses.index') }}" class="text-sm text-zinc-500 hover:text-white">&larr; All courses</a>
        <h1 class="mt-2 text-2xl font-semibold text-white">{{ $course->title }}</h1>
        @if ($course->folder_path)
            <p class="mt-3 break-all font-mono text-xs text-zinc-500" title="Video folder">
                {{ $course->folder_path }}
            </p>
            <form action="{{ route('courses.rescan', $course) }}" method="post" class="mt-3">
                @csrf
                <button
                    type="submit"
                    class="rounded-lg border border-zinc-600 bg-zinc-900 px-3 py-1.5 text-sm text-zinc-200 hover:border-zinc-500 hover:bg-zinc-800"
                >
                    Rescan folder
                </button>
                <span class="ml-2 text-xs text-zinc-500">Pick up new, moved, or renamed files</span>
            </form>
        @endif
    </div>

    @if ($course->videos->isEmpty())
        <p class="text-zinc-400">This course has no lessons yet.</p>
    @else
        <ol class="space-y-2">
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
                    class="rounded-lg border px-4 py-3 transition
                        @if ($isCurrent)
                            border-emerald-500/60 bg-emerald-950/40 ring-1 ring-emerald-500/30
                        @elseif ($isNext)
                            border-zinc-600 bg-zinc-900/60
                        @else
                            border-zinc-800 bg-zinc-900
                        @endif"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex h-7 min-w-[2rem] items-center justify-center rounded bg-zinc-800 text-xs font-mono text-zinc-400">
                                {{ $video->sort_order }}
                            </span>
                            <div>
                                <a href="{{ route('videos.show', $video) }}" class="font-medium text-white hover:underline">
                                    {{ $video->title }}
                                </a>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                    @if ($isCurrent)
                                        <span class="rounded bg-emerald-900/80 px-2 py-0.5 text-emerald-300">Current</span>
                                    @endif
                                    @if ($isNext && ! $isCurrent)
                                        <span class="rounded bg-zinc-800 px-2 py-0.5 text-zinc-400">Up next</span>
                                    @endif
                                    @if ($p?->completed)
                                        <span class="text-emerald-400">Completed</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="min-w-[140px] flex-1 sm:max-w-xs">
                            <div class="h-2 overflow-hidden rounded-full bg-zinc-800">
                                <div
                                    class="h-full rounded-full bg-emerald-600 transition-[width]"
                                    style="width: {{ $pct }}%"
                                ></div>
                            </div>
                            <p class="mt-1 text-right text-xs text-zinc-500">{{ $pct }}%</p>
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
