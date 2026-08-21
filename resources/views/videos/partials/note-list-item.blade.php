@php
    /** @var \App\Models\Video $video */
    /** @var \App\Models\VideoNote $note */
@endphp
<li
    class="rounded-2xl border border-slate-200/96 bg-white px-4 py-3.5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-900/40 dark:ring-slate-800/60"
    data-note-id="{{ $note->id }}"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            @if ($note->timestamp_seconds !== null)
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="badge-accent px-2.5 py-1"
                        data-note-seek="{{ $note->timestamp_seconds }}"
                        aria-label="Jump to {{ $note->timestampLabel() }} in this video"
                    >
                        {{ $note->timestampLabel() }}
                    </button>
                    <span class="text-[0.65rem] uppercase tracking-wider text-slate-600 dark:text-slate-500">Cue</span>
                </div>
            @else
                <p class="mb-2 text-[0.65rem] font-medium uppercase tracking-wider text-slate-600 dark:text-slate-500">Lesson note</p>
            @endif
            <div class="note-prose text-sm leading-relaxed text-slate-800 dark:text-slate-200">{!! $note->bodyHtml() !!}</div>
        </div>
        <form
            action="{{ route('videos.notes.destroy', [$video, $note]) }}"
            method="post"
            class="shrink-0"
            onsubmit="return confirm('Remove this note?');"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="text-xs font-medium text-slate-600 underline decoration-slate-500/62 underline-offset-2 hover:text-rose-600 hover:decoration-rose-500/55 dark:text-slate-500 dark:decoration-slate-600/60 dark:hover:text-rose-400 dark:hover:decoration-rose-500/50">
                Remove
            </button>
        </form>
    </div>
</li>
