<div
    id="course-preview-modal"
    class="course-preview-modal fixed inset-0 z-[200] flex items-center justify-center p-4 sm:p-6"
    aria-hidden="true"
    aria-labelledby="course-preview-heading"
    role="dialog"
    hidden
>
    <button
        type="button"
        id="course-preview-backdrop"
        class="absolute inset-0 bg-slate-900/55 backdrop-blur-[3px] transition hover:bg-slate-900/62 dark:bg-slate-950/70 dark:hover:bg-slate-950/78"
        tabindex="-1"
        aria-label="Close course preview"
    ></button>

    <div
        class="modal-accent-ring relative z-10 flex w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-300/92 bg-white/96 shadow-2xl shadow-slate-400/30 ring-1 dark:border-slate-700/90 dark:bg-slate-900/95 dark:shadow-black/50"
    >
        <div class="flex items-start justify-between gap-4 border-b border-slate-200/90 px-5 py-4 sm:px-6 dark:border-slate-800/85">
            <div class="min-w-0">
                <p id="course-preview-heading" class="text-accent-eyebrow text-[0.65rem] font-semibold uppercase tracking-[0.18em]">
                    Course preview
                </p>
                <p class="mt-1 line-clamp-2 text-lg font-semibold text-slate-900 dark:text-slate-50">
                    {{ $course->title }}
                </p>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                    Preview does not count toward your progress.
                </p>
            </div>
            <button
                type="button"
                id="course-preview-close"
                class="btn-ghost-accent shrink-0 p-2"
                aria-label="Close preview"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="course-plyr overflow-hidden bg-black">
            <video
                id="course-preview-video"
                class="aspect-video w-full"
                playsinline
                preload="metadata"
            >
                <source src="{{ $previewStreamUrl }}" type="video/mp4" />
                Your browser does not support the video tag.
            </video>
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-200/90 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-slate-800/85">
            <p class="text-xs text-slate-600 dark:text-slate-400">
                Like what you see? Start the first lesson to begin tracking progress.
            </p>
            <div class="flex shrink-0 flex-wrap gap-2">
                <button type="button" id="course-preview-dismiss" class="btn-secondary px-4 py-2.5 text-sm font-semibold">
                    Not now
                </button>
                @if ($currentVideo)
                    <a
                        href="{{ route('videos.show', $currentVideo) }}"
                        class="btn-primary inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4" aria-hidden="true">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                        Start course
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
