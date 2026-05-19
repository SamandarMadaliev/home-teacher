@extends('layouts.app')

@section('main_max_class', 'max-w-7xl')

@section('title', 'Playground — '.config('app.name'))

@push('head')
    @vite('resources/js/playground.js')
@endpush

@php
    $hasAny = ! empty($available);

    $fileExtMap = [
        'javascript' => 'js',
        'python' => 'py',
        'php' => 'php',
        'go' => 'go',
    ];

    $langIcons = [
        'javascript' => 'JS',
        'python' => 'PY',
        'php' => 'PHP',
        'go' => 'GO',
    ];

    $langAccent = [
        'javascript' => 'text-amber-500',
        'python' => 'text-accent',
        'php' => 'text-indigo-500',
        'go' => 'text-cyan-500',
    ];
@endphp

@section('content')
    {{-- Header card --}}
    <section
        class="page-header-card-sm"
        aria-label="Playground overview"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
            <div class="min-w-0">
                <p class="text-accent-eyebrow text-[0.65rem] font-semibold uppercase tracking-[0.2em]">Playground</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-50 sm:text-3xl">Run code, locally</h1>
                <p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                    Sandboxed in a fresh temp folder per run · capped at {{ $limits['timeout_seconds'] }}s · {{ $limits['max_output_kb'] }} KB output per stream.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <ul class="flex flex-wrap items-center gap-1.5" role="list" aria-label="Detected runtimes">
                    @foreach ($languages as $lang)
                        <li
                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold ring-1 transition
                                @if ($lang['available'])
                                    border-emerald-200/85 bg-emerald-50/80 text-emerald-800 ring-emerald-200/60 dark:border-emerald-900/40 dark:bg-emerald-950/35 dark:text-emerald-200 dark:ring-emerald-900/40
                                @else
                                    border-slate-200/90 bg-slate-50/70 text-slate-500 ring-slate-200/85 opacity-80 dark:border-slate-800/80 dark:bg-slate-950/30 dark:text-slate-400 dark:ring-slate-800/70
                                @endif"
                            title="{{ $lang['available'] ? ($lang['version'] ?: 'Installed').' — '.$lang['binary'] : 'Not installed' }}"
                        >
                            @if ($lang['available'])
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            @else
                                <span class="size-1.5 rounded-full bg-slate-400"></span>
                            @endif
                            {{ $lang['label'] }}
                        </li>
                    @endforeach
                </ul>
                <form action="{{ route('playground.refresh') }}" method="post" class="shrink-0">
                    @csrf
                    <button
                        type="submit"
                        class="btn-secondary inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold"
                        title="Re-detect installed languages"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Re-check
                    </button>
                </form>
            </div>
        </div>
    </section>

    @if (! $hasAny)
        <section class="mt-8 rounded-3xl border border-amber-300/70 bg-amber-50/80 p-6 ring-1 ring-amber-200/55 dark:border-amber-900/50 dark:bg-amber-950/25 dark:ring-amber-900/30 sm:p-7" aria-label="No runtimes available">
            <h2 class="text-lg font-semibold text-amber-900 dark:text-amber-200">No language runtimes detected</h2>
            <p class="mt-2 text-sm text-amber-900/85 dark:text-amber-200/85">
                The playground couldn't find <code class="font-mono">node</code>, <code class="font-mono">python3</code>, <code class="font-mono">php</code>, or <code class="font-mono">go</code> on this machine's PATH. Install at least one to get started — then click <strong class="font-semibold">Re-check</strong>.
            </p>
            <div class="mt-5 grid gap-4 text-sm text-amber-950 dark:text-amber-100 sm:grid-cols-2">
                <div>
                    <p class="font-semibold">macOS (Homebrew)</p>
                    <pre class="mt-1 overflow-x-auto rounded-xl bg-white/85 px-3 py-2 font-mono text-xs text-slate-800 ring-1 ring-amber-200/55 dark:bg-slate-950/55 dark:text-slate-200 dark:ring-amber-900/40">brew install node python php go</pre>
                </div>
                <div>
                    <p class="font-semibold">Debian / Ubuntu</p>
                    <pre class="mt-1 overflow-x-auto rounded-xl bg-white/85 px-3 py-2 font-mono text-xs text-slate-800 ring-1 ring-amber-200/55 dark:bg-slate-950/55 dark:text-slate-200 dark:ring-amber-900/40">sudo apt install nodejs python3 php-cli golang-go</pre>
                </div>
            </div>
        </section>
    @else
        {{-- IDE workspace --}}
        <section
            class="playground-shell mt-6 overflow-hidden rounded-3xl border border-slate-200/95 bg-white shadow-xl shadow-slate-300/30 ring-1 ring-slate-200/85 dark:border-slate-800/85 dark:bg-slate-950/85 dark:shadow-black/35 dark:ring-slate-800/70"
            id="playground"
            data-run-url="{{ route('playground.run') }}"
        >
            {{-- Title strip --}}
            <div class="playground-titlebar flex items-center justify-between gap-3 border-b border-slate-200/85 bg-slate-50/80 px-4 py-2 dark:border-slate-800/80 dark:bg-slate-900/60">
                <div class="flex items-center gap-2">
                    <span class="flex gap-1.5" aria-hidden="true">
                        <span class="size-2.5 rounded-full bg-rose-400/85"></span>
                        <span class="size-2.5 rounded-full bg-amber-400/85"></span>
                        <span class="size-2.5 rounded-full bg-emerald-400/85"></span>
                    </span>
                    <span class="ml-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                        scratch
                    </span>
                </div>
                <p class="hidden text-[11px] text-slate-500 dark:text-slate-500 sm:flex sm:items-center sm:gap-1.5">
                    <kbd class="rounded bg-slate-200/85 px-1 py-0.5 font-mono ring-1 ring-slate-300/85 dark:bg-slate-800 dark:ring-slate-700">⌘ / Ctrl</kbd>
                    <span>+</span>
                    <kbd class="rounded bg-slate-200/85 px-1 py-0.5 font-mono ring-1 ring-slate-300/85 dark:bg-slate-800 dark:ring-slate-700">↵</kbd>
                    <span>to run</span>
                    <span class="mx-1.5 opacity-50">·</span>
                    <kbd class="rounded bg-slate-200/85 px-1 py-0.5 font-mono ring-1 ring-slate-300/85 dark:bg-slate-800 dark:ring-slate-700">⌘ / Ctrl</kbd>
                    <span>+</span>
                    <kbd class="rounded bg-slate-200/85 px-1 py-0.5 font-mono ring-1 ring-slate-300/85 dark:bg-slate-800 dark:ring-slate-700">F</kbd>
                    <span>to find</span>
                </p>
            </div>

            {{-- File-tab style language picker --}}
            <div class="playground-tabbar flex flex-wrap items-end gap-1 border-b border-slate-200/80 bg-slate-100/70 px-3 pt-2 dark:border-slate-800/80 dark:bg-slate-900/55" role="tablist" aria-label="Choose language">
                @foreach ($available as $lang)
                    @php
                        $key = $lang['key'];
                        $ext = $fileExtMap[$key] ?? $key;
                        $icon = $langIcons[$key] ?? strtoupper(substr($key, 0, 2));
                        $accent = $langAccent[$key] ?? 'text-accent';
                    @endphp
                    <button
                        type="button"
                        role="tab"
                        data-lang-tab="{{ $key }}"
                        data-label="{{ $lang['label'] }}"
                        data-version="{{ $lang['version'] }}"
                        data-binary="{{ $lang['binary'] }}"
                        data-sample="{{ rawurlencode($lang['sample']) }}"
                        aria-selected="{{ $key === $default ? 'true' : 'false' }}"
                        class="playground-tab group flex items-center gap-2 rounded-t-lg border border-b-0 border-transparent px-3 py-1.5 text-[12px] font-medium text-slate-600 transition hover:text-slate-900 aria-selected:border-slate-200/95 aria-selected:bg-white aria-selected:text-slate-900 aria-selected:shadow-sm dark:text-slate-400 dark:hover:text-slate-200 dark:aria-selected:border-slate-800/85 dark:aria-selected:bg-slate-950 dark:aria-selected:text-slate-100"
                        title="{{ $lang['label'] }}{{ $lang['version'] ? ' · '.$lang['version'] : '' }}"
                    >
                        <span class="rounded bg-slate-200/85 px-1 py-0.5 font-mono text-[9px] font-bold uppercase {{ $accent }} dark:bg-slate-800/85" aria-hidden="true">{{ $icon }}</span>
                        <span class="font-mono">main.{{ $ext }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Editor toolbar --}}
            <div class="playground-toolbar flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 bg-white/95 px-3 py-2 dark:border-slate-800/80 dark:bg-slate-950/80">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        data-run
                        class="inline-flex items-center gap-1.5 rounded-md bg-gradient-to-r from-emerald-500 to-emerald-600 px-3 py-1.5 text-[12px] font-semibold text-white shadow-sm shadow-emerald-500/30 ring-1 ring-emerald-400/40 transition hover:from-emerald-400 hover:to-emerald-500 disabled:cursor-not-allowed disabled:opacity-60 dark:shadow-emerald-950/40"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-3.5" aria-hidden="true">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                        <span data-run-label>Run</span>
                    </button>
                    <button type="button" data-reset class="playground-iconbtn" title="Reset to sample code">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                        </svg>
                        <span>Reset</span>
                    </button>
                    <button type="button" data-copy data-label="Copy" class="playground-iconbtn" title="Copy code to clipboard">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75A1.125 1.125 0 0 1 3.75 20.625V8.625c0-.621.504-1.125 1.125-1.125h3.375M9 12h9.75M16.5 6.75h2.625c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-9.75A1.125 1.125 0 0 1 8.25 17.625V8.625c0-.621.504-1.125 1.125-1.125Z" />
                        </svg>
                        <span data-copy-label>Copy</span>
                    </button>
                    <button type="button" data-wrap aria-pressed="false" class="playground-iconbtn" title="Toggle line wrapping">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6.75h18M3 12h13.5a3.75 3.75 0 0 1 0 7.5H12m0 0 2.25-2.25M12 19.5l2.25 2.25" />
                        </svg>
                        <span>Wrap</span>
                    </button>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" data-clear class="playground-iconbtn" title="Clear output">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-3.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        <span>Clear</span>
                    </button>
                </div>
            </div>

            {{-- Editor + Console split --}}
            <div class="playground-grid grid gap-0 lg:grid-cols-[minmax(0,1fr)_minmax(0,28rem)] xl:grid-cols-[minmax(0,1fr)_minmax(0,30rem)]">
                {{-- Editor pane --}}
                <div class="playground-pane border-b border-slate-200/85 dark:border-slate-800/80 lg:border-b-0 lg:border-r">
                    <div class="playground-editor relative" data-editor aria-label="Code editor"></div>

                    {{-- Status bar --}}
                    <div class="playground-statusbar flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/85 bg-slate-50/85 px-3 py-1.5 text-[11px] text-slate-600 dark:border-slate-800/80 dark:bg-slate-900/55 dark:text-slate-400">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300">
                                <span class="dot-accent-glow size-1.5"></span>
                                <span data-status-lang>—</span>
                            </span>
                            <span class="hidden font-mono text-slate-500 dark:text-slate-500 sm:inline" data-info-binary></span>
                        </div>
                        <div class="flex items-center gap-3 font-mono text-slate-500 dark:text-slate-500">
                            <span data-status-pos>Ln 1, Col 1</span>
                            <span class="opacity-60">·</span>
                            <span data-status-meta>0 lines · 0 chars</span>
                        </div>
                    </div>

                    {{-- Stdin footer --}}
                    <details class="playground-stdin border-t border-slate-200/85 bg-white/85 dark:border-slate-800/80 dark:bg-slate-950/65">
                        <summary class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">
                            <span class="inline-flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-3.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                                Stdin
                            </span>
                            <span class="font-mono text-[10px] font-normal normal-case opacity-60">optional</span>
                        </summary>
                        <textarea
                            data-stdin
                            class="block h-24 w-full resize-y border-t border-slate-200/85 bg-white px-3 py-2 font-mono text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset focus-accent dark:border-slate-800/80 dark:bg-slate-950/70 dark:text-slate-100"
                            placeholder="Text piped into the program's stdin"
                        ></textarea>
                    </details>
                </div>

                {{-- Console pane --}}
                <div class="playground-pane flex min-h-[20rem] flex-col bg-slate-50/60 dark:bg-slate-950/55 lg:min-h-0">
                    {{-- Console tabs --}}
                    <div class="playground-consoletabs flex items-center justify-between gap-2 border-b border-slate-200/85 bg-slate-100/70 px-3 dark:border-slate-800/80 dark:bg-slate-900/55" role="tablist" aria-label="Console">
                        <div class="flex items-center gap-1 pt-2">
                            <button
                                type="button"
                                role="tab"
                                data-console-tab="stdout"
                                aria-selected="true"
                                class="playground-console-tab"
                            >
                                Output
                            </button>
                            <button
                                type="button"
                                role="tab"
                                data-console-tab="stderr"
                                aria-selected="false"
                                class="playground-console-tab"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    Stderr
                                    <span data-stderr-dot class="hidden size-1.5 rounded-full bg-rose-500"></span>
                                </span>
                            </button>
                            <button
                                type="button"
                                role="tab"
                                data-console-tab="info"
                                aria-selected="false"
                                class="playground-console-tab"
                            >
                                Info
                            </button>
                        </div>
                        <p class="hidden text-[10px] font-mono uppercase tracking-wider text-slate-500 dark:text-slate-500 sm:block">console</p>
                    </div>

                    {{-- Console panels --}}
                    <div class="playground-console relative grow overflow-hidden">
                        <div class="absolute inset-0 overflow-y-auto px-4 py-3" data-console-panel="stdout">
                            <pre data-stdout class="whitespace-pre-wrap break-words font-mono text-[12.5px] leading-relaxed text-slate-800 dark:text-emerald-200"></pre>
                            <p data-console-empty class="font-mono text-[12px] text-slate-500 dark:text-slate-500">Run your code to see output here.</p>
                        </div>
                        <div class="absolute inset-0 hidden overflow-y-auto px-4 py-3" data-console-panel="stderr">
                            <pre data-stderr class="whitespace-pre-wrap break-words font-mono text-[12.5px] leading-relaxed text-rose-700 dark:text-rose-300"></pre>
                        </div>
                        <div class="absolute inset-0 hidden overflow-y-auto px-4 py-3" data-console-panel="info">
                            <dl class="grid grid-cols-1 gap-2 text-[12px] sm:grid-cols-2">
                                <div class="rounded-lg border border-slate-200/85 bg-white px-3 py-2 ring-1 ring-slate-200/70 dark:border-slate-800/80 dark:bg-slate-900/55 dark:ring-slate-800/70">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Exit code</dt>
                                    <dd class="mt-1 font-mono text-slate-900 dark:text-slate-100"><span data-info-exit class="hidden inline-flex items-center gap-1 rounded-full px-2 py-0.5">—</span><span class="text-slate-500" data-info-exit-fallback>—</span></dd>
                                </div>
                                <div class="rounded-lg border border-slate-200/85 bg-white px-3 py-2 ring-1 ring-slate-200/70 dark:border-slate-800/80 dark:bg-slate-900/55 dark:ring-slate-800/70">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Duration</dt>
                                    <dd class="mt-1 font-mono text-slate-900 dark:text-slate-100"><span data-info-duration class="hidden">—</span><span class="text-slate-500" data-info-duration-fallback>—</span></dd>
                                </div>
                            </dl>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span data-info-timed-out class="hidden inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-800 ring-1 ring-amber-300/55 dark:bg-amber-950/55 dark:text-amber-300 dark:ring-amber-700/40">
                                    Timed out
                                </span>
                                <span data-info-truncated class="hidden chip-accent">
                                    Output truncated
                                </span>
                            </div>
                            <p class="mt-4 text-[11px] text-slate-500 dark:text-slate-500">
                                Limits: {{ $limits['timeout_seconds'] }}s wall clock · {{ $limits['max_code_kb'] }} KB code · {{ $limits['max_output_kb'] }} KB output per stream.
                            </p>
                        </div>
                    </div>

                    {{-- Console status bar --}}
                    <div class="playground-statusbar flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/85 bg-slate-50/85 px-3 py-1.5 text-[11px] text-slate-600 dark:border-slate-800/80 dark:bg-slate-900/55 dark:text-slate-400">
                        <span class="font-mono text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-500" data-status-run></span>
                    </div>
                </div>
            </div>
        </section>
    @endif
@endsection
