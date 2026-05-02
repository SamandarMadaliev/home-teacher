@extends('layouts.app')

@section('title', 'Edit roadmap — '.config('app.name'))

@section('content')
    <div class="mb-10">
        <a href="{{ route('roadmaps.show', $roadmap) }}" class="inline-flex items-center gap-1 text-sm font-medium text-sky-400/95 transition hover:text-sky-300">
            <span aria-hidden="true">←</span> {{ $roadmap->title }}
        </a>
        <p class="mt-6 text-sm font-medium uppercase tracking-widest text-sky-500/90">Learning path</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-50 sm:text-4xl">Edit roadmap</h1>
    </div>

    <form action="{{ route('roadmaps.update', $roadmap) }}" method="post" class="card-surface max-w-2xl space-y-8 p-6 sm:p-8">
        @csrf
        @method('patch')
        <div>
            <label for="title" class="mb-2 block text-sm font-medium text-slate-200">Title</label>
            <input
                type="text"
                name="title"
                id="title"
                value="{{ old('title', $roadmap->title) }}"
                required
                maxlength="255"
                class="input-field font-sans"
            />
        </div>
        <div>
            <label for="description" class="mb-2 block text-sm font-medium text-slate-200">Description <span class="font-normal text-slate-500">(optional)</span></label>
            <textarea
                name="description"
                id="description"
                rows="4"
                maxlength="65535"
                class="input-field font-sans resize-y"
            >{{ old('description', $roadmap->description) }}</textarea>
        </div>
        <div class="flex flex-wrap gap-3 border-t border-slate-800/90 pt-8">
            <button type="submit" class="btn-primary">
                Save changes
            </button>
            <a href="{{ route('roadmaps.show', $roadmap) }}" class="btn-secondary">
                Cancel
            </a>
        </div>
    </form>

    <div class="mt-12 max-w-2xl rounded-2xl border border-rose-900/50 bg-rose-950/25 px-6 py-5">
        <h2 class="text-sm font-semibold text-rose-100">Delete roadmap</h2>
        <p class="mt-2 text-sm text-rose-100/85">
            This removes the roadmap only. Your courses and watch progress are not deleted.
        </p>
        <form action="{{ route('roadmaps.destroy', $roadmap) }}" method="post" class="mt-4" onsubmit="return confirm('Delete this roadmap?');">
            @csrf
            @method('delete')
            <button type="submit" class="rounded-xl border border-rose-700/70 bg-rose-950/45 px-4 py-2 text-sm font-medium text-rose-100 transition hover:bg-rose-900/55">
                Delete roadmap
            </button>
        </form>
    </div>
@endsection
