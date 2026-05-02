@extends('layouts.app')

@section('title', 'Add course — '.config('app.name'))

@section('content')
    <div class="mb-10">
        <a href="{{ route('courses.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-sky-400/95 transition hover:text-sky-300">
            <span aria-hidden="true">←</span> Courses
        </a>
        <p class="mt-6 text-sm font-medium uppercase tracking-widest text-sky-500/90">New course</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-50 sm:text-4xl">Add course</h1>
        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-400">
            Choose a folder on <strong class="font-semibold text-slate-200">this computer</strong> where your videos live. Files stay there — we only index names for playback and progress.
        </p>
    </div>

    <form action="{{ route('courses.store') }}" method="post" class="card-surface max-w-2xl space-y-8 p-6 sm:p-8" id="add-course-form">
        @csrf
        <div>
            <label for="title" class="mb-2 block text-sm font-medium text-slate-200">Title</label>
            <input
                type="text"
                name="title"
                id="title"
                value="{{ old('title') }}"
                required
                maxlength="255"
                class="input-field font-sans"
                placeholder="e.g. Laravel Deep Dive"
            />
        </div>
        <div>
            <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
                <label for="folder_path" class="block text-sm font-medium text-slate-200">Video folder</label>
                <button
                    type="button"
                    id="folder-picker-toggle"
                    class="text-sm font-semibold text-sky-400 transition hover:text-sky-300 hover:underline underline-offset-4"
                >
                    Browse folders
                </button>
            </div>
            <input
                type="text"
                name="folder_path"
                id="folder_path"
                value="{{ old('folder_path') }}"
                required
                class="input-field font-mono text-sm"
                placeholder="Browse below, or paste an absolute path"
                autocomplete="off"
            />
            <p class="mt-2 text-xs leading-relaxed text-slate-500">
                Browse covers your whole machine unless restricted in <code class="rounded bg-slate-900 px-1 py-0.5 font-mono text-slate-300">.env</code>.
                On macOS, protected folders may need Full Disk Access for PHP.
                Formats: mp4, webm, mkv, mov, m4v, ogv, avi.
            </p>

            <div
                id="folder-picker-panel"
                class="mt-5 hidden rounded-2xl border border-slate-800/90 bg-slate-950/55 p-5 ring-1 ring-slate-800/80"
                data-picker-url="{{ route('folder-picker') }}"
            >
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-800/90 pb-4">
                    <button type="button" id="folder-picker-back" class="btn-ghost" disabled>
                        Up
                    </button>
                    <button type="button" id="folder-picker-roots" class="btn-ghost" disabled>
                        All places
                    </button>
                    <span id="folder-picker-current" class="min-w-0 flex-1 truncate font-mono text-xs text-sky-300/75"></span>
                </div>
                <div
                    id="folder-picker-list"
                    class="mt-4 max-h-60 space-y-1 overflow-y-auto rounded-xl border border-slate-800/90 bg-slate-950/85 p-2 ring-1 ring-black/30"
                    role="list"
                ></div>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="button" id="folder-picker-select" class="btn-primary text-sm disabled:opacity-45" disabled>
                        Use this folder
                    </button>
                    <p id="folder-picker-hint" class="text-xs text-slate-500">
                        Open a folder from the list, then confirm.
                    </p>
                </div>
                <p id="folder-picker-error" class="mt-4 hidden rounded-xl border border-rose-900/55 bg-rose-950/45 px-4 py-3 text-xs leading-relaxed text-rose-100"></p>
            </div>
        </div>
        <div class="flex flex-wrap gap-3 border-t border-slate-800/90 pt-8">
            <button type="submit" class="btn-primary">
                Create &amp; scan folder
            </button>
            <a href="{{ route('courses.index') }}" class="btn-secondary">
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
                    empty.className = 'px-3 py-4 text-sm text-slate-500';
                    empty.textContent = state.atRootList
                        ? 'No starting locations found — check that PHP can read your disks, or set COURSE_BROWSE_ROOTS in .env.'
                        : 'No subfolders here (empty or access denied). You can still confirm this folder.';
                    listEl.appendChild(empty);
                } else {
                    state.items.forEach(function (item) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className =
                            'flex w-full items-center rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-200 transition hover:bg-sky-950/40 hover:text-white';
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
