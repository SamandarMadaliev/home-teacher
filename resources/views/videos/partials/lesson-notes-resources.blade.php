@php
    $watchLessonTab = in_array(old('kind'), ['file', 'link'], true) ? 'resources' : 'notes';
    if (filled(old('body')) || filled(old('timestamp_seconds'))) {
        $watchLessonTab = 'notes';
    }
    $lessonNotesCount = $video->notes->count();
    $lessonResourcesCount = $video->attachments->count();
@endphp

<section
    id="watch-lesson-tabs"
    class="watch-content-panel mt-10 rounded-3xl border border-slate-200/95 bg-white/90 p-5 shadow-lg shadow-slate-300/20 ring-1 ring-slate-200/85 dark:border-slate-800/90 dark:bg-slate-900/55 dark:shadow-black/20 dark:ring-slate-800/70 sm:p-6"
>
    <nav class="watch-tabs" role="tablist" aria-label="Lesson notes and resources">
        <button
            type="button"
            role="tab"
            id="watch-tab-btn-notes"
            class="watch-tab{{ $watchLessonTab === 'notes' ? ' watch-tab--active' : '' }}"
            data-watch-tab="notes"
            aria-controls="watch-tab-panel-notes"
            aria-selected="{{ $watchLessonTab === 'notes' ? 'true' : 'false' }}"
            tabindex="{{ $watchLessonTab === 'notes' ? '0' : '-1' }}"
        >
            Notes
            @if ($lessonNotesCount > 0)
                <span class="watch-tab-badge tabular-nums">{{ $lessonNotesCount }}</span>
            @endif
        </button>
        <button
            type="button"
            role="tab"
            id="watch-tab-btn-resources"
            class="watch-tab{{ $watchLessonTab === 'resources' ? ' watch-tab--active' : '' }}"
            data-watch-tab="resources"
            aria-controls="watch-tab-panel-resources"
            aria-selected="{{ $watchLessonTab === 'resources' ? 'true' : 'false' }}"
            tabindex="{{ $watchLessonTab === 'resources' ? '0' : '-1' }}"
        >
            Resources
            @if ($lessonResourcesCount > 0)
                <span class="watch-tab-badge tabular-nums">{{ $lessonResourcesCount }}</span>
            @endif
        </button>
    </nav>

    <div
        id="watch-tab-panel-notes"
        role="tabpanel"
        aria-labelledby="watch-tab-btn-notes"
        data-watch-tab-panel="notes"
        @class(['watch-tab-panel', 'hidden' => $watchLessonTab !== 'notes'])
    >
        <p class="max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-500">
            General notes for this lesson, or time-stamped cues you can jump to from the list below.
        </p>

                <form
                    class="mt-5 rounded-2xl border border-slate-200/95 bg-slate-50/96 px-4 py-5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-950/35 dark:ring-slate-800/70"
                    data-note-editor
                    data-note-store-url="{{ route('videos.notes.store', $video) }}"
                    data-note-preview-url="{{ route('videos.notes.preview', $video) }}"
                >
                    <label for="note-body" class="sr-only">Note</label>

                    <div
                        class="note-toolbar flex flex-wrap items-center gap-0.5 rounded-t-xl border border-b-0 border-slate-300/95 bg-white/95 px-1.5 py-1 dark:border-slate-700/80 dark:bg-slate-900/70"
                        role="toolbar"
                        aria-label="Format note"
                        data-note-toolbar
                    >
                        <button type="button" data-md="bold" class="note-toolbar-btn" title="Bold (⌘/Ctrl+B)" aria-label="Bold">
                            <span class="font-bold">B</span>
                        </button>
                        <button type="button" data-md="italic" class="note-toolbar-btn" title="Italic (⌘/Ctrl+I)" aria-label="Italic">
                            <span class="italic font-serif">I</span>
                        </button>
                        <button type="button" data-md="strike" class="note-toolbar-btn" title="Strikethrough" aria-label="Strikethrough">
                            <span class="line-through">S</span>
                        </button>
                        <button type="button" data-md="code" class="note-toolbar-btn" title="Inline code (⌘/Ctrl+E)" aria-label="Inline code">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5-4.5 4.5 4.5 4.5m10.5-9 4.5 4.5-4.5 4.5m-3.75-13.5-3 16.5" />
                            </svg>
                        </button>
                        <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700/80" aria-hidden="true"></span>
                        <button type="button" data-md="heading" class="note-toolbar-btn" title="Heading" aria-label="Heading">
                            <span class="font-bold">H</span>
                        </button>
                        <button type="button" data-md="ul" class="note-toolbar-btn" title="Bulleted list" aria-label="Bulleted list">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.008v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.008v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </button>
                        <button type="button" data-md="ol" class="note-toolbar-btn" title="Numbered list" aria-label="Numbered list">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.242 5.992h12m-12 6.003H20.24m-12 5.999h12M4.117 7.495v-3.75H2.99m1.125 3.75H2.99m1.125 0H5.24m-1.92 2.577a1.125 1.125 0 1 1 1.591 1.59l-1.83 1.83h2.16M2.99 15.745h1.125a1.125 1.125 0 0 1 0 2.25H3.74m0-.002.384.001h.376a1.125 1.125 0 1 1 0 2.25H2.99" />
                            </svg>
                        </button>
                        <button type="button" data-md="quote" class="note-toolbar-btn" title="Quote" aria-label="Quote">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5" />
                            </svg>
                        </button>
                        <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700/80" aria-hidden="true"></span>
                        <button type="button" data-md="link" class="note-toolbar-btn" title="Link (⌘/Ctrl+K)" aria-label="Link">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                        </button>
                        <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700/80" aria-hidden="true"></span>
                        <button
                            type="button"
                            class="note-toolbar-btn note-toolbar-btn--preview"
                            data-note-preview-btn
                            title="Preview formatted note"
                            aria-label="Preview note"
                            aria-pressed="false"
                        >
                            Preview
                        </button>
                        <span class="ml-auto hidden pr-1 text-[10px] font-mono text-slate-500 dark:text-slate-500 sm:inline">Markdown</span>
                    </div>

                    <textarea
                        id="note-body"
                        name="body"
                        rows="5"
                        required
                        placeholder="Write your note here… Markdown supported: **bold**, *italic*, `code`, lists, links."
                        class="note-textarea input-field block w-full resize-y rounded-b-xl rounded-t-none border-x-0 border-b-0 shadow-inner"
                        data-note-input
                    >{{ old('body') }}</textarea>

                    <div
                        id="note-body-preview"
                        class="note-preview note-prose hidden"
                        data-note-preview
                        hidden
                        aria-live="polite"
                    ></div>

                    <input type="hidden" name="timestamp_seconds" id="note-timestamp-input" value="{{ old('timestamp_seconds') }}" />

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            id="note-at-current-time"
                            class="btn-secondary py-2 text-xs font-semibold"
                        >
                            Use current play time
                        </button>
                        <button
                            type="button"
                            id="note-clear-timestamp"
                            class="btn-secondary py-2 text-xs font-semibold"
                        >
                            Lesson note (no time)
                        </button>
                    </div>
                    <p id="note-timestamp-label" class="mt-2 hidden text-xs text-slate-600 dark:text-slate-500" aria-live="polite"></p>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button type="submit" class="btn-primary text-sm" data-note-save-btn>Save note</button>
                        <p class="text-sm text-slate-600 dark:text-slate-500" data-note-save-status hidden aria-live="polite"></p>
                    </div>
                </form>

                <div class="mt-6" data-lesson-notes-root>
                    <p
                        data-lesson-notes-empty
                        @class([
                            'rounded-xl border border-dashed border-slate-300/90 bg-slate-50/70 px-4 py-6 text-center text-sm text-slate-600 dark:border-slate-700/80 dark:bg-slate-950/30 dark:text-slate-500',
                            'hidden' => $video->notes->isNotEmpty(),
                        ])
                    >
                        No notes for this lesson yet.
                    </p>
                    <ul
                        data-lesson-notes-list
                        class="space-y-2.5"
                        role="list"
                        @if ($video->notes->isEmpty()) hidden @endif
                    >
                        @foreach ($video->notes as $note)
                            @include('videos.partials.note-list-item', ['video' => $video, 'note' => $note])
                        @endforeach
                    </ul>
                </div>

    </div>

    <div
        id="watch-tab-panel-resources"
        role="tabpanel"
        aria-labelledby="watch-tab-btn-resources"
        data-watch-tab-panel="resources"
        @class(['watch-tab-panel', 'hidden' => $watchLessonTab !== 'resources'])
    >
        <p class="max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-500">
            Point at files already on disk (absolute path or relative to the course folder) or save external links — nothing is copied or uploaded.
        </p>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <form
                        action="{{ route('videos.attachments.store', $video) }}"
                        method="post"
                        class="rounded-2xl border border-slate-200/95 bg-slate-50/96 px-4 py-5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-950/35 dark:ring-slate-800/70"
                    >
                        @csrf
                        <input type="hidden" name="kind" value="file" />
                        <p class="text-accent-eyebrow text-[0.65rem] font-semibold uppercase tracking-[0.2em]">File on disk</p>

                        <label for="attachment-file-title" class="mt-3 block text-xs font-medium text-slate-700 dark:text-slate-300">Label <span class="font-normal text-slate-500 dark:text-slate-500">(optional)</span></label>
                        <input
                            id="attachment-file-title"
                            type="text"
                            name="title"
                            maxlength="255"
                            placeholder="e.g. Slides — Chapter 3"
                            value="{{ old('kind') === 'file' ? old('title') : '' }}"
                            class="input-field mt-1.5 text-sm"
                            autocomplete="off"
                        />

                        <label for="attachment-file-path" class="mt-3 block text-xs font-medium text-slate-700 dark:text-slate-300">Path to file</label>
                        <input
                            id="attachment-file-path"
                            type="text"
                            name="file_path"
                            required
                            spellcheck="false"
                            placeholder="e.g. slides/chapter-3.pdf or /Users/me/notes/x.pdf"
                            value="{{ old('kind') === 'file' ? old('file_path') : '' }}"
                            class="input-field mt-1.5 font-mono text-xs"
                            autocomplete="off"
                        />
                        <p class="mt-1.5 text-[0.65rem] leading-relaxed text-slate-600 dark:text-slate-500">
                            Absolute path, or relative to the course folder. We only store the path and read the file when you open it.
                        </p>

                        <div class="mt-4">
                            <button type="submit" class="btn-primary text-sm">Attach file</button>
                        </div>
                    </form>

                    <form
                        action="{{ route('videos.attachments.store', $video) }}"
                        method="post"
                        class="rounded-2xl border border-slate-200/95 bg-slate-50/96 px-4 py-5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-950/35 dark:ring-slate-800/70"
                    >
                        @csrf
                        <input type="hidden" name="kind" value="link" />
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-emerald-800 dark:text-emerald-400/90">External link</p>

                        <label for="attachment-link-title" class="mt-3 block text-xs font-medium text-slate-700 dark:text-slate-300">Label <span class="font-normal text-slate-500 dark:text-slate-500">(optional)</span></label>
                        <input
                            id="attachment-link-title"
                            type="text"
                            name="title"
                            maxlength="255"
                            placeholder="e.g. Official docs"
                            value="{{ old('kind') === 'link' ? old('title') : '' }}"
                            class="input-field mt-1.5 text-sm"
                            autocomplete="off"
                        />

                        <label for="attachment-link-url" class="mt-3 block text-xs font-medium text-slate-700 dark:text-slate-300">URL</label>
                        <input
                            id="attachment-link-url"
                            type="url"
                            name="url"
                            required
                            inputmode="url"
                            placeholder="https://…"
                            value="{{ old('kind') === 'link' ? old('url') : '' }}"
                            class="input-field mt-1.5 text-sm"
                            autocomplete="off"
                        />

                        <div class="mt-4">
                            <button type="submit" class="btn-primary text-sm">Save link</button>
                        </div>
                    </form>
                </div>

                @if ($video->attachments->isEmpty())
                    <p class="mt-6 rounded-xl border border-dashed border-slate-300/90 bg-slate-50/70 px-4 py-6 text-center text-sm text-slate-600 dark:border-slate-700/80 dark:bg-slate-950/30 dark:text-slate-500">
                        No resources for this lesson yet.
                    </p>
                @else
                    <ul class="mt-6 space-y-2.5" role="list">
                        @foreach ($video->attachments as $attachment)
                            <li class="rounded-2xl border border-slate-200/96 bg-white px-4 py-3.5 ring-1 ring-slate-200/92 sm:px-5 dark:border-slate-800/90 dark:bg-slate-900/40 dark:ring-slate-800/60">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        @if ($attachment->isFile())
                                            <span class="resource-icon-accent" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                            </span>
                                        @else
                                            <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 ring-1 ring-emerald-300/60 dark:bg-emerald-950/55 dark:text-emerald-300 dark:ring-emerald-700/40" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                                </svg>
                                            </span>
                                        @endif

                                        <div class="min-w-0 flex-1">
                                            @if ($attachment->isFile())
                                                @php $fileAvailable = $attachment->isAvailable(); @endphp
                                                @if ($fileAvailable)
                                                    <a
                                                        href="{{ route('videos.attachments.download', [$video, $attachment]) }}"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="resource-link-accent"
                                                    >
                                                        {{ $attachment->title }}
                                                    </a>
                                                @else
                                                    <p class="block break-words text-sm font-semibold text-slate-600 line-through decoration-rose-400/70 dark:text-slate-400" title="File not found at the recorded path">
                                                        {{ $attachment->title }}
                                                    </p>
                                                @endif
                                                <p class="mt-1 truncate font-mono text-[0.7rem] text-slate-600 dark:text-slate-500" title="{{ $attachment->file_path }}">
                                                    {{ $attachment->fileBasename() ?? $attachment->file_path }}
                                                    @if ($attachment->sizeLabel())
                                                        <span aria-hidden="true">·</span> <span class="tabular-nums">{{ $attachment->sizeLabel() }}</span>
                                                    @endif
                                                </p>
                                                @if (! $fileAvailable)
                                                    <p class="mt-1 text-[0.65rem] font-medium text-rose-700 dark:text-rose-300">
                                                        File missing — the path no longer points to a readable file.
                                                    </p>
                                                @endif
                                            @else
                                                <a
                                                    href="{{ $attachment->url }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="resource-link-accent"
                                                >
                                                    {{ $attachment->title }}
                                                </a>
                                                <p class="mt-1 truncate text-[0.7rem] text-slate-600 dark:text-slate-500">
                                                    @if ($attachment->linkHost())
                                                        {{ $attachment->linkHost() }} <span aria-hidden="true">·</span>
                                                    @endif
                                                    <span class="break-all text-slate-500 dark:text-slate-500">{{ $attachment->url }}</span>
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    <form
                                        action="{{ route('videos.attachments.destroy', [$video, $attachment]) }}"
                                        method="post"
                                        class="shrink-0"
                                        onsubmit="return confirm('Remove this attachment?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-slate-600 underline decoration-slate-500/62 underline-offset-2 hover:text-rose-600 hover:decoration-rose-500/55 dark:text-slate-500 dark:decoration-slate-600/60 dark:hover:text-rose-400 dark:hover:decoration-rose-500/50">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
    </div>
</section>
