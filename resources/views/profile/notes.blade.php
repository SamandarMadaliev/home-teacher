@extends('profile._layout')

@section('title', 'Notes — '.config('app.name'))

@section('profile_panel')
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">All notes</h2>
        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-500">
            Lesson notes and time cues across your library. Filter by course or lesson.
        </p>
    </div>

    <form
        method="get"
        action="{{ route('profile.notes') }}"
        id="notes-filter-form"
        class="card-surface mb-8 p-5 sm:p-6"
    >
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="course" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">Course</label>
                <select
                    name="course"
                    id="course"
                    class="input-field"
                    onchange="document.getElementById('video').value = ''; this.form.submit();"
                >
                    <option value="">All courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected($selectedCourseId === $course->id)>
                            {{ $course->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="video" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">Lesson</label>
                <select
                    name="video"
                    id="video"
                    class="input-field @if ($videos->isEmpty()) opacity-60 @endif"
                    @disabled($videos->isEmpty())
                    onchange="this.form.submit()"
                >
                    <option value="">All lessons @if ($selectedCourseId) in this course @endif</option>
                    @foreach ($videos as $video)
                        <option value="{{ $video->id }}" @selected($selectedVideoId === $video->id)>
                            {{ $video->title }}
                        </option>
                    @endforeach
                </select>
                @if ($selectedCourseId && $videos->isEmpty())
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-500">No lessons in this course yet.</p>
                @endif
            </div>
        </div>
        @if ($selectedCourseId || $selectedVideoId)
            <p class="mt-4">
                <a href="{{ route('profile.notes') }}" class="link-accent text-sm font-medium">
                    Clear filters
                </a>
            </p>
        @endif
    </form>

    @if ($notes->isEmpty())
        <div class="empty-state-soft px-8 py-14 text-center">
            <p class="text-lg font-medium text-slate-800 dark:text-slate-100">No notes found</p>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-500">
                @if ($selectedCourseId || $selectedVideoId)
                    Try clearing the filters, or add notes while watching a lesson.
                @else
                    Open a lesson and save notes under the player to see them here.
                @endif
            </p>
            <a href="{{ route('courses.index') }}" class="btn-secondary mt-6 inline-flex">Browse courses</a>
        </div>
    @else
        <p class="mb-4 text-xs text-slate-600 dark:text-slate-500">
            {{ $notes->total() }} {{ $notes->total() === 1 ? 'note' : 'notes' }}
            @if ($selectedCourseId || $selectedVideoId)
                matching your filters
            @endif
        </p>
        <ul class="space-y-3" role="list">
            @foreach ($notes as $note)
                @php
                    $lesson = $note->video;
                    $course = $lesson->course;
                @endphp
                <li class="rounded-2xl border border-slate-200/96 bg-white px-4 py-4 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-900/40 dark:ring-slate-800/60">
                    <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                        <a
                            href="{{ route('courses.show', $course) }}"
                            class="link-accent"
                        >
                            {{ $course->title }}
                        </a>
                        <span class="text-slate-400 dark:text-slate-600" aria-hidden="true">·</span>
                        <a
                            href="{{ route('videos.show', $lesson) }}"
                            class="font-medium text-slate-700 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                        >
                            {{ $lesson->title }}
                        </a>
                        @if ($note->timestamp_seconds !== null)
                            <span class="text-slate-400 dark:text-slate-600" aria-hidden="true">·</span>
                            <a
                                href="{{ route('videos.show', $lesson) }}"
                                class="badge-accent text-[0.7rem]"
                            >
                                {{ $note->timestampLabel() }}
                            </a>
                        @endif
                        <span class="ml-auto text-slate-500 dark:text-slate-600">{{ $note->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="note-prose text-sm leading-relaxed text-slate-800 dark:text-slate-200">{!! $note->bodyHtml() !!}</div>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <a href="{{ route('videos.show', $lesson) }}" class="link-accent text-xs">
                            Open lesson →
                        </a>
                        <form
                            action="{{ route('videos.notes.destroy', [$lesson, $note]) }}"
                            method="post"
                            class="inline"
                            onsubmit="return confirm('Remove this note?');"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-slate-600 underline decoration-slate-500/62 underline-offset-2 hover:text-rose-600 dark:text-slate-500 dark:hover:text-rose-400">
                                Remove
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($notes->hasPages())
            <div class="mt-8">
                {{ $notes->links() }}
            </div>
        @endif
    @endif
@endsection
