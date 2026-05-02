@extends('layouts.app')

@section('title', $video->title.' — '.config('app.name'))

@push('head')
    @vite(['resources/js/player.js'])
@endpush

@section('content')
    <div class="mb-4">
        <a href="{{ route('courses.show', $video->course) }}" class="text-sm text-zinc-500 hover:text-white">
            &larr; {{ $video->course->title }}
        </a>
        <h1 class="mt-2 text-xl font-semibold text-white sm:text-2xl">{{ $video->title }}</h1>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-black shadow-xl">
        <video
            id="course-video"
            class="aspect-video w-full"
            controls
            playsinline
            preload="metadata"
            src="{{ route('videos.stream', $video) }}"
        >
            Your browser does not support the video tag.
        </video>
    </div>

    <p class="mt-3 text-xs text-zinc-500">
        Space: play/pause · Right arrow: skip +10s
    </p>

    @if ($nextVideo)
        <p class="mt-4 text-sm text-zinc-400">
            Next:
            <a href="{{ route('videos.show', $nextVideo) }}" class="text-emerald-400 hover:underline">{{ $nextVideo->title }}</a>
        </p>
    @endif

    <script>
        window.__COURSE_PLAYER__ = {
            videoId: {{ $video->id }},
            progressUrl: @json(route('videos.progress', $video)),
            initialPosition: {{ json_encode($initialPosition) }},
            nextUrl: @json($nextVideo ? route('videos.show', $nextVideo) : null),
        };
    </script>
@endsection
