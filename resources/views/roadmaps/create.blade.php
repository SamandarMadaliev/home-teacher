@extends('layouts.app')

@section('title', 'New roadmap — '.config('app.name'))

@section('content')
    <div class="mb-10">
        <a href="{{ route('roadmaps.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-sky-600 transition hover:text-sky-800 dark:text-sky-400/95 dark:hover:text-sky-300">
            <span aria-hidden="true">←</span> Roadmaps
        </a>
        <p class="mt-6 text-sm font-medium uppercase tracking-widest text-sky-600 dark:text-sky-500/90">Learning path</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50 sm:text-4xl">New roadmap</h1>
        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Give it a name and optional notes. On the next screen you can add courses and drag them into order.
        </p>
    </div>

    <form action="{{ route('roadmaps.store') }}" method="post" class="card-surface max-w-2xl space-y-8 p-6 sm:p-8">
        @csrf
        <div>
            <label for="title" class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-200">Title</label>
            <input
                type="text"
                name="title"
                id="title"
                value="{{ old('title') }}"
                required
                maxlength="255"
                class="input-field font-sans"
                placeholder="e.g. Full stack web — beginner to job-ready"
            />
        </div>
        <div>
            <label for="description" class="mb-2 block text-sm font-medium text-slate-800 dark:text-slate-200">Description <span class="font-normal text-slate-600 dark:text-slate-500">(optional)</span></label>
            <textarea
                name="description"
                id="description"
                rows="4"
                maxlength="65535"
                class="input-field font-sans resize-y"
                placeholder="Why this order? What are you working toward?"
            >{{ old('description') }}</textarea>
        </div>
        <div class="flex flex-wrap gap-3 border-t border-slate-200/95 pt-8 dark:border-slate-800/90">
            <button type="submit" class="btn-primary">
                Create roadmap
            </button>
            <a href="{{ route('roadmaps.index') }}" class="btn-secondary">
                Cancel
            </a>
        </div>
    </form>
@endsection
