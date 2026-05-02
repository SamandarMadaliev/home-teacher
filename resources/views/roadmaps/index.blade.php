@extends('layouts.app')

@section('main_max_class', 'max-w-7xl')

@section('title', 'Roadmaps — '.config('app.name'))

@section('content')
    <div class="mb-10 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="section-eyebrow text-violet-400/95">Learning paths</p>
            <h1 class="home-page-title mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">Roadmaps</h1>
            <p class="mt-2 max-w-lg text-sm leading-relaxed text-slate-500">
                Order courses so you always know what to start next — independent of when you last watched something.
            </p>
        </div>
        <a href="{{ route('roadmaps.create') }}" class="btn-primary shrink-0">
            New roadmap
        </a>
    </div>

    @if ($roadmaps->isEmpty())
        <div class="card-surface px-8 py-14 text-center">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500/20 to-blue-600/10 ring-1 ring-violet-500/25" aria-hidden="true">
                <svg class="h-8 w-8 text-violet-400/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75H15.75M9 12H15.75M9 17.25H15.75M4.5 6.75h.008v.008H4.5V6.75zm0 5.25h.008v.008H4.5v-.008zm0 5.25h.008v.008H4.5v-.008zM19.5 6.75h.008v.008H19.5V6.75zm0 5.25h.008v.008H19.5v-.008zm0 5.25h.008v.008H19.5v-.008z" />
                </svg>
            </div>
            <p class="text-lg font-medium text-slate-100">No roadmaps yet</p>
            <p class="mt-2 text-sm text-slate-400">
                <a href="{{ route('roadmaps.create') }}" class="font-semibold text-sky-400 underline decoration-sky-500/40 underline-offset-4 hover:text-sky-300">
                    Create a roadmap
                </a>
                <span class="text-slate-500"> and add your courses in the right sequence.</span>
            </p>
        </div>
    @else
        <ul class="space-y-4" role="list">
            @foreach ($roadmaps as $roadmap)
                <li>
                    <a
                        href="{{ route('roadmaps.show', $roadmap) }}"
                        class="card-surface group flex flex-col gap-4 p-6 transition hover:border-sky-700/45 hover:shadow-lg hover:shadow-sky-950/20 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-slate-50 transition group-hover:text-white">
                                {{ $roadmap->title }}
                            </h2>
                            @if ($roadmap->description)
                                <p class="mt-2 line-clamp-2 text-sm text-slate-400">
                                    {{ $roadmap->description }}
                                </p>
                            @endif
                            <p class="mt-3 text-xs text-slate-500">
                                {{ $roadmap->courses_count }} {{ $roadmap->courses_count === 1 ? 'course' : 'courses' }}
                            </p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-2 text-sm font-medium text-sky-400/95 transition group-hover:text-sky-300">
                            Open
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-800/95 text-sky-400 ring-1 ring-slate-600/80 transition group-hover:bg-sky-500/15" aria-hidden="true">→</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
