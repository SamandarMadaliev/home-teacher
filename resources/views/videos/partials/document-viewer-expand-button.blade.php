{{-- In-page fullscreen toggle for the PDF/HTML document viewer (see initDocumentViewerExpand() in player.js). --}}
<button
    type="button"
    id="document-viewer-expand"
    class="absolute right-3 top-3 z-10 inline-flex items-center justify-center rounded-lg bg-white/90 p-2 text-slate-700 shadow-md ring-1 ring-slate-300/80 backdrop-blur transition hover:bg-white dark:bg-slate-800/90 dark:text-slate-200 dark:ring-slate-700/80 dark:hover:bg-slate-800"
    aria-pressed="false"
    data-label-off="Expand"
    data-label-on="Exit expanded view"
    title="Expand"
>
    <svg data-expand-icon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15" />
    </svg>
    <svg data-collapse-icon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden size-4" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9V4.5M15 9h4.5M15 9l5.25-5.25M15 15v4.5M15 15h4.5M15 15l5.25 5.25" />
    </svg>
    <span class="sr-only">Expand view</span>
</button>
