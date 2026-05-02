@extends('layouts.app')

@section('title', 'Add course — '.config('app.name'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('courses.index') }}" class="text-sm text-zinc-500 hover:text-white">&larr; Courses</a>
        <h1 class="mt-2 text-2xl font-semibold text-white">Add course</h1>
        <p class="mt-2 max-w-2xl text-sm text-zinc-400">
            Point to a folder on <strong class="text-zinc-200">this computer</strong> (the one running the app) that contains your video files. Nothing is copied — playback reads files in place. Lesson order follows filenames (natural sort), including subfolders.
        </p>
    </div>

    <form action="{{ route('courses.store') }}" method="post" class="max-w-2xl space-y-5" id="add-course-form">
        @csrf
        <div>
            <label for="title" class="mb-1.5 block text-sm font-medium text-zinc-300">Title</label>
            <input
                type="text"
                name="title"
                id="title"
                value="{{ old('title') }}"
                required
                maxlength="255"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-white placeholder-zinc-500 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                placeholder="e.g. Laravel Deep Dive"
            />
        </div>
        <div>
            <div class="mb-1.5 flex flex-wrap items-end justify-between gap-2">
                <label for="folder_path" class="block text-sm font-medium text-zinc-300">Video folder</label>
                <button
                    type="button"
                    id="folder-picker-toggle"
                    class="text-sm text-emerald-400 hover:text-emerald-300 hover:underline"
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
                class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 font-mono text-sm text-white placeholder-zinc-500 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                placeholder="Pick a folder with the button above, or paste a path"
                autocomplete="off"
            />
            <p class="mt-1.5 text-xs text-zinc-500">
                Browse starts from your whole computer (or the drives you allow in .env). If macOS blocks listing (Downloads, Desktop, etc.), enable Full Disk Access for your PHP/terminal app.
                You can also paste an absolute path. Videos: mp4, webm, mkv, mov, m4v, ogv, avi.
            </p>

            <div
                id="folder-picker-panel"
                class="mt-4 hidden rounded-lg border border-zinc-700 bg-zinc-900/80 p-4"
                data-picker-url="{{ route('folder-picker') }}"
            >
                <div class="flex flex-wrap items-center gap-2 border-b border-zinc-800 pb-3">
                    <button
                        type="button"
                        id="folder-picker-back"
                        class="rounded border border-zinc-600 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-40"
                        disabled
                    >
                        Up
                    </button>
                    <button
                        type="button"
                        id="folder-picker-roots"
                        class="rounded border border-zinc-600 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-40"
                        disabled
                    >
                        All places
                    </button>
                    <span id="folder-picker-current" class="min-w-0 flex-1 truncate font-mono text-xs text-zinc-400"></span>
                </div>
                <div
                    id="folder-picker-list"
                    class="mt-3 max-h-56 space-y-1 overflow-y-auto rounded border border-zinc-800 bg-zinc-950/50 p-2"
                    role="list"
                ></div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        id="folder-picker-select"
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                        disabled
                    >
                        Use this folder
                    </button>
                    <p id="folder-picker-hint" class="self-center text-xs text-zinc-500">
                        Open a folder from the list, then confirm.
                    </p>
                </div>
                <p id="folder-picker-error" class="mt-3 hidden text-xs leading-relaxed text-red-300"></p>
            </div>
        </div>
        <div class="flex flex-wrap gap-3 pt-2">
            <button
                type="submit"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
            >
                Create &amp; scan folder
            </button>
            <a href="{{ route('courses.index') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 hover:border-zinc-500 hover:text-white">
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
                    empty.className = 'px-2 py-3 text-sm text-zinc-500';
                    empty.textContent = state.atRootList
                        ? 'No starting locations found — check that PHP can read your disks, or set COURSE_BROWSE_ROOTS in .env.'
                        : 'No subfolders you can open here (empty, or macOS/Windows blocked access). You can still use this folder for the course.';
                    listEl.appendChild(empty);
                } else {
                    state.items.forEach(function (item) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className =
                            'flex w-full items-center rounded px-2 py-2 text-left text-sm text-zinc-200 hover:bg-zinc-800';
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
                    setHint('Start from Computer (all disks), Home, or a drive.');
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
                        setHint(data.code === 'permission_denied' ? 'Access blocked — see message below.' : 'Could not open folder.');
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
