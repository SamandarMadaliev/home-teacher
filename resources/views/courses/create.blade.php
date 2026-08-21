@extends('layouts.app')

@section('main_max_class', 'max-w-3xl')

@section('title', 'Add course — '.config('app.name'))

@section('content')
    <a href="{{ route('courses.index') }}" class="back-link">
        <span aria-hidden="true">←</span> Courses
    </a>

    <header class="mt-5 page-header-card">
        <p class="text-accent-eyebrow text-[0.65rem] font-semibold uppercase tracking-[0.2em]">New course</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-50 sm:text-4xl">Add a course</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Point at a folder on <strong class="font-semibold text-slate-900 dark:text-slate-200">this computer</strong> where your lesson videos live. We only store the path and index filenames — nothing is uploaded or moved.
        </p>
        <ol class="mt-5 grid gap-3 text-sm text-slate-700 dark:text-slate-300 sm:grid-cols-3">
            <li class="flex gap-2 rounded-xl bg-white/80 px-3 py-2.5 ring-1 ring-slate-200/95 dark:bg-slate-950/40 dark:ring-slate-800/80">
                <span class="step-badge">1</span>
                <span class="pt-0.5 leading-snug">Name the course <span class="font-normal text-slate-500 dark:text-slate-500">(optional)</span></span>
            </li>
            <li class="flex gap-2 rounded-xl bg-white/80 px-3 py-2.5 ring-1 ring-slate-200/95 dark:bg-slate-950/40 dark:ring-slate-800/80">
                <span class="step-badge">2</span>
                <span class="pt-0.5 leading-snug">Pick the video folder</span>
            </li>
            <li class="flex gap-2 rounded-xl bg-white/80 px-3 py-2.5 ring-1 ring-slate-200/95 dark:bg-slate-950/40 dark:ring-slate-800/80">
                <span class="step-badge">3</span>
                <span class="pt-0.5 leading-snug">We scan for supported videos</span>
            </li>
        </ol>
    </header>

    <form action="{{ route('courses.store') }}" method="post" class="mt-8 rounded-3xl border border-slate-200/95 bg-white/90 p-6 shadow-lg shadow-slate-300/20 ring-1 ring-slate-200/85 dark:border-slate-800/90 dark:bg-slate-900/55 dark:shadow-black/20 dark:ring-slate-800/70 sm:p-8" id="add-course-form">
        @csrf

        <div class="space-y-6">
            <div>
                <label for="title" class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                    <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-100 text-[0.65rem] font-bold text-slate-600 ring-1 ring-slate-200/95 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700/80">1</span>
                    Course title
                    <span class="text-xs font-normal text-slate-500 dark:text-slate-500">(optional)</span>
                </label>
                <input
                    type="text"
                    name="title"
                    id="title"
                    value="{{ old('title') }}"
                    maxlength="255"
                    class="input-field mt-2 font-sans"
                    placeholder="Leave blank to use the folder name"
                    autocomplete="off"
                />
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-500">Shown in your library. If empty, we use the video folder’s name.</p>
            </div>

            <div class="border-t border-slate-200/90 pt-6 dark:border-slate-800/85">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                    <label for="folder_path" class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-100 text-[0.65rem] font-bold text-slate-600 ring-1 ring-slate-200/95 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700/80">2</span>
                        Video folder
                    </label>
                    <button
                        type="button"
                        id="folder-picker-toggle"
                        class="btn-secondary inline-flex items-center gap-2 py-2 pl-3 pr-3.5 text-xs font-semibold sm:text-sm"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-slate-600 dark:text-slate-400" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                        </svg>
                        Browse folders
                    </button>
                </div>
                <input
                    type="text"
                    name="folder_path"
                    id="folder_path"
                    value="{{ old('folder_path') }}"
                    required
                    class="input-field mt-2 font-mono text-sm"
                    placeholder="/Volumes/Courses/… or paste an absolute path"
                    spellcheck="false"
                    autocomplete="off"
                />
                <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-500">
                    Must be a real directory on disk. After you submit, we scan once for supported video files.
                </p>
                <details class="details-plain group mt-3 rounded-xl border border-slate-200/95 bg-slate-50/80 px-3 py-2 ring-1 ring-slate-200/90 dark:border-slate-800/90 dark:bg-slate-950/35 dark:ring-slate-800/70">
                    <summary class="cursor-pointer text-xs font-semibold link-accent">
                        <span class="inline-flex items-center gap-1.5">
                            <span aria-hidden="true" class="text-slate-400 group-open:rotate-90 transition-transform">▸</span>
                            Permissions, browse roots &amp; formats
                        </span>
                    </summary>
                    <p class="mt-2 border-t border-slate-200/90 pt-2 text-xs leading-relaxed text-slate-600 dark:border-slate-800/85 dark:text-slate-500">
                        Browse covers your whole machine unless restricted in <code class="rounded bg-slate-200 px-1 py-0.5 font-mono text-slate-900 dark:bg-slate-900 dark:text-slate-300">.env</code>
                        (<span class="font-mono">COURSE_BROWSE_ROOTS</span>). On macOS, protected folders may need Full Disk Access for PHP.
                        Supported extensions: mp4, webm, mkv, mov, m4v, ogv, avi.
                    </p>
                </details>

                <div
                    id="folder-picker-panel"
                    class="mt-5 hidden panel-accent-soft"
                    data-picker-url="{{ route('folder-picker') }}"
                >
                    <p class="text-accent-eyebrow text-xs font-semibold uppercase tracking-wider">Folder browser</p>
                    <p class="mt-1 text-[0.7rem] text-slate-600 dark:text-slate-500">Click a folder to open it, then <strong class="font-medium text-slate-800 dark:text-slate-300">Use this folder</strong> fills the field above.</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2 border-b border-slate-200/90 pb-3 dark:border-slate-800/85">
                        <button type="button" id="folder-picker-back" class="btn-ghost py-1.5 text-xs" disabled>
                            ← Up
                        </button>
                        <button type="button" id="folder-picker-roots" class="btn-ghost py-1.5 text-xs" disabled>
                            All places
                        </button>
                        <span id="folder-picker-current" class="min-w-0 flex-1 truncate rounded-lg bg-white/90 px-2 py-1.5 font-mono text-[0.7rem] text-accent-strong ring-1 ring-slate-200/95 dark:bg-slate-900/80 dark:ring-slate-700/80"></span>
                    </div>
                    <div
                        id="folder-picker-list"
                        class="mt-3 max-h-60 space-y-1 overflow-y-auto overscroll-contain rounded-xl border border-slate-200/96 bg-slate-50/95 p-2 ring-1 ring-slate-900/8 dark:border-slate-800/90 dark:bg-slate-950/85 dark:ring-black/30"
                        role="list"
                    ></div>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <button type="button" id="folder-picker-select" class="btn-primary text-sm disabled:opacity-45" disabled>
                            Use this folder
                        </button>
                        <p id="folder-picker-hint" class="text-xs text-slate-600 dark:text-slate-500">
                            Open a folder from the list, then confirm.
                        </p>
                    </div>
                    <p id="folder-picker-error" class="mt-4 hidden rounded-xl border border-rose-300/92 bg-rose-50 px-4 py-3 text-xs leading-relaxed text-rose-950 dark:border-rose-900/55 dark:bg-rose-950/45 dark:text-rose-100"></p>
                </div>
            </div>
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-slate-200/90 pt-6 dark:border-slate-800/85">
            <button type="submit" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create course &amp; scan
            </button>
            <a href="{{ route('courses.index') }}" class="btn-secondary px-4 py-2.5 text-sm font-medium">
                Cancel
            </a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            const panel = document.getElementById('folder-picker-panel');
            const toggle = document.getElementById('folder-picker-toggle');
            const listEl = document.getElementById('folder-picker-list');
            const pathInput = document.getElementById('folder_path');
            const currentEl = document.getElementById('folder-picker-current');
            const backBtn = document.getElementById('folder-picker-back');
            const rootsBtn = document.getElementById('folder-picker-roots');
            const selectBtn = document.getElementById('folder-picker-select');
            const hintEl = document.getElementById('folder-picker-hint');
            const errorEl = document.getElementById('folder-picker-error');
            const url = panel?.dataset.pickerUrl;

            if (!panel || !toggle || !listEl || !pathInput || !url) {
                return;
            }

            function showPickerError(text) {
                if (errorEl) {
                    errorEl.textContent = text || '';
                    errorEl.classList.toggle('hidden', !text);
                }
            }

            let state = {
                atRootList: true,
                current: null,
                parent: null,
                items: [],
            };

            function setHint(text) {
                if (hintEl) {
                    hintEl.textContent = text;
                }
            }

            function renderList() {
                listEl.innerHTML = '';
                if (!state.items.length) {
                    const empty = document.createElement('p');
                    empty.className = 'px-3 py-4 text-sm text-slate-600 dark:text-slate-500';
                    empty.textContent = state.atRootList
                        ? 'No starting locations found — check that PHP can read your disks, or set COURSE_BROWSE_ROOTS in .env.'
                        : 'No subfolders here (empty or access denied). You can still confirm this folder.';
                    listEl.appendChild(empty);
                } else {
                    state.items.forEach(function (item) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className =
                            'flex w-full items-center rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-800 transition hover:bg-accent-soft dark:text-slate-200 dark:hover:bg-accent-soft dark:hover:text-white';
                        btn.textContent = item.name;
                        btn.addEventListener('click', function () {
                            load(item.path);
                        });
                        listEl.appendChild(btn);
                    });
                }

                if (currentEl) {
                    currentEl.textContent = state.current ?? 'Choose a place…';
                }

                const canSelect = Boolean(state.current && !state.atRootList);
                if (selectBtn) {
                    selectBtn.disabled = !canSelect;
                }
                if (backBtn) {
                    backBtn.disabled = !state.parent;
                }
                if (rootsBtn) {
                    rootsBtn.disabled = state.atRootList;
                }

                if (canSelect) {
                    setHint('Click “Use this folder” to fill the path above.');
                } else if (state.atRootList) {
                    setHint('Start from Computer, Home, or a drive.');
                } else {
                    setHint('Open a subfolder or confirm this one.');
                }
            }

            async function load(path) {
                setHint('Loading…');
                showPickerError('');
                const qs = path ? '?path=' + encodeURIComponent(path) : '';
                try {
                    const res = await fetch(url + qs, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        const msg = data.error || 'Could not open that folder.';
                        setHint(data.code === 'permission_denied' ? 'Access blocked — see below.' : 'Could not open folder.');
                        showPickerError(msg);
                        return;
                    }
                    showPickerError('');
                    state = {
                        atRootList: Boolean(data.atRootList),
                        current: data.current,
                        parent: data.parent,
                        items: data.items || [],
                    };
                    renderList();
                } catch {
                    setHint('Network error — try again.');
                    showPickerError('');
                }
            }

            if (selectBtn) {
                selectBtn.addEventListener('click', function () {
                    if (state.current) {
                        pathInput.value = state.current;
                        pathInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            }

            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    if (state.parent) {
                        load(state.parent);
                    }
                });
            }

            if (rootsBtn) {
                rootsBtn.addEventListener('click', function () {
                    load(null);
                });
            }

            toggle.addEventListener('click', function () {
                panel.classList.toggle('hidden');
                const closed = panel.classList.contains('hidden');
                if (!closed && listEl.innerHTML === '') {
                    load(null);
                }
            });
        })();
    </script>
@endpush
