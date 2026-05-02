@extends('layouts.app')

@section('title', 'Courses — '.config('app.name'))

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-white">Courses</h1>
        <a
            href="{{ route('courses.create') }}"
            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
        >
            Add course
        </a>
    </div>

    @if ($courses->isEmpty())
        <p class="text-zinc-400">
            No courses yet.
            <a href="{{ route('courses.create') }}" class="text-emerald-400 underline hover:text-emerald-300">Add a course</a>
            and choose the folder where your videos live.
        </p>
    @else
        <ul class="space-y-3">
            @foreach ($courses as $course)
                <li>
                    <a
                        href="{{ route('courses.show', $course) }}"
                        class="flex items-center justify-between rounded-lg border border-zinc-800 bg-zinc-900 px-4 py-3 transition hover:border-zinc-600 hover:bg-zinc-800/80"
                    >
                        <span class="font-medium text-white">{{ $course->title }}</span>
                        <span class="text-sm text-zinc-500">{{ $course->videos_count }} lesson{{ $course->videos_count === 1 ? '' : 's' }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
