@extends('layouts.app')

@section('title', 'Courses — '.config('app.name'))

@section('content')
    <div class="mb-10 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-widest text-sky-500/90">Your library</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-50 sm:text-4xl">Courses</h1>
            <p class="mt-2 max-w-lg text-sm text-slate-400">Watch locally. Progress saves automatically.</p>
        </div>
        <a href="{{ route('courses.create') }}" class="btn-primary shrink-0">
            Add course
        </a>
    </div>

    @if ($courses->isEmpty())
        <div class="card-surface px-8 py-14 text-center">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500/20 to-blue-600/10 ring-1 ring-sky-500/25" aria-hidden="true">
                <svg class="h-8 w-8 text-sky-400/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                </svg>
            </div>
            <p class="text-lg font-medium text-slate-100">No courses yet</p>
            <p class="mt-2 text-sm text-slate-400">
                <a href="{{ route('courses.create') }}" class="font-semibold text-sky-400 underline decoration-sky-500/40 underline-offset-4 hover:text-sky-300">
                    Add a course
                </a>
                <span class="text-slate-500"> and choose the folder where your videos live.</span>
            </p>
        </div>
    @else
        <ul class="space-y-4">
            @foreach ($courses as $course)
                <li>
                    <a
                        href="{{ route('courses.show', $course) }}"
                        class="group card-surface flex items-center justify-between gap-4 px-6 py-5 transition hover:border-sky-800/60 hover:bg-slate-900/75 hover:shadow-sky-950/20"
                    >
                        <div class="min-w-0">
                            <span class="block truncate font-semibold text-slate-50 transition group-hover:text-white">
                                {{ $course->title }}
                            </span>
                            <span class="mt-1 block text-sm text-slate-500">
                                {{ $course->videos_count }} lesson{{ $course->videos_count === 1 ? '' : 's' }}
                            </span>
                        </div>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-800/90 text-sky-400/85 ring-1 ring-slate-700/80 transition group-hover:bg-gradient-to-br group-hover:from-sky-500/15 group-hover:to-blue-600/10 group-hover:text-sky-300 group-hover:ring-sky-600/35" aria-hidden="true">
                            →
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
