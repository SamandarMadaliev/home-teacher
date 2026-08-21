/**
 * Lightweight markdown toolbar for the lesson notes textarea.
 *
 * Looks for `[data-note-editor]` (the wrapper) which contains:
 *   - `[data-note-toolbar]` with `<button data-md="...">` toolbar buttons.
 *   - `[data-note-input]` the textarea.
 *
 * Supported actions (set as `data-md`):
 *   bold, italic, strike, code, heading, ul, ol, quote, link
 *
 * Keyboard shortcuts (only fire when focus is inside the textarea):
 *   Cmd/Ctrl+B   → bold
 *   Cmd/Ctrl+I   → italic
 *   Cmd/Ctrl+E   → inline code
 *   Cmd/Ctrl+K   → link
 */

const editors = document.querySelectorAll('[data-note-editor]');
editors.forEach(setupEditor);

function setupEditor(root) {
    const textarea = root.querySelector('[data-note-input]');
    const toolbar = root.querySelector('[data-note-toolbar]');
    const previewPanel = root.querySelector('[data-note-preview]');
    const previewBtn = root.querySelector('[data-note-preview-btn]');
    const previewUrl = root.dataset.notePreviewUrl ?? '';
    const storeUrl = root.dataset.noteStoreUrl ?? '';
    const saveBtn = root.querySelector('[data-note-save-btn]');
    const saveStatus = root.querySelector('[data-note-save-status]');
    const timestampInput = document.getElementById('note-timestamp-input');
    const timestampLabel = document.getElementById('note-timestamp-label');
    const notesPanel = root.closest('[data-watch-tab-panel="notes"]');
    const notesRoot = notesPanel?.querySelector('[data-lesson-notes-root]') ?? null;
    const notesList = notesRoot?.querySelector('[data-lesson-notes-list]') ?? null;
    const notesEmpty = notesRoot?.querySelector('[data-lesson-notes-empty]') ?? null;
    const notesTabBtn = document.getElementById('watch-tab-btn-notes');

    if (!textarea || !toolbar) return;

    /** @type {boolean} */
    let previewMode = false;

    toolbar.querySelectorAll('button[data-md]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            if (previewMode) {
                return;
            }
            apply(textarea, btn.dataset.md);
            textarea.focus();
        });
    });

    textarea.addEventListener('keydown', (e) => {
        if (!(e.metaKey || e.ctrlKey) || e.shiftKey || e.altKey) return;
        const key = e.key.toLowerCase();
        const map = { b: 'bold', i: 'italic', e: 'code', k: 'link' };
        const action = map[key];
        if (!action) return;
        e.preventDefault();
        if (previewMode) {
            return;
        }
        apply(textarea, action);
    });

    if (previewBtn && previewPanel && previewUrl) {
        previewBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (previewMode) {
                exitPreviewMode();
                return;
            }
            await enterPreviewMode();
        });
    }

    function setToolbarFormattingEnabled(enabled) {
        toolbar.querySelectorAll('button[data-md]').forEach((btn) => {
            btn.disabled = !enabled;
            btn.classList.toggle('note-toolbar-btn--disabled', !enabled);
        });
    }

    function exitPreviewMode() {
        previewMode = false;
        previewPanel.hidden = true;
        previewPanel.classList.add('hidden');
        textarea.classList.remove('hidden');
        textarea.hidden = false;
        previewBtn.textContent = 'Preview';
        previewBtn.setAttribute('aria-label', 'Preview note');
        previewBtn.setAttribute('aria-pressed', 'false');
        previewBtn.classList.remove('note-toolbar-btn--active');
        setToolbarFormattingEnabled(true);
        textarea.focus();
    }

    async function enterPreviewMode() {
        const body = textarea.value;

        previewMode = true;
        previewPanel.hidden = false;
        previewPanel.classList.remove('hidden');
        textarea.classList.add('hidden');
        textarea.hidden = true;
        previewBtn.textContent = 'Edit';
        previewBtn.setAttribute('aria-label', 'Back to editing note');
        previewBtn.setAttribute('aria-pressed', 'true');
        previewBtn.classList.add('note-toolbar-btn--active');
        setToolbarFormattingEnabled(false);

        if (body.trim() === '') {
            previewPanel.innerHTML =
                '<p class="note-preview-empty">Nothing to preview yet. Switch back to Edit and add some text.</p>';
            return;
        }

        previewPanel.innerHTML = '<p class="note-preview-loading">Loading preview…</p>';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        try {
            const res = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ body }),
            });

            if (!res.ok) {
                throw new Error('Preview failed');
            }

            const payload = await res.json();
            previewPanel.innerHTML = payload.html?.trim()
                ? payload.html
                : '<p class="note-preview-empty">Nothing to preview yet.</p>';
        } catch {
            previewPanel.innerHTML =
                '<p class="note-preview-error">Could not load preview. Check your connection and try again.</p>';
            previewMode = false;
            previewPanel.hidden = true;
            previewPanel.classList.add('hidden');
            textarea.classList.remove('hidden');
            textarea.hidden = false;
            previewBtn.textContent = 'Preview';
            previewBtn.setAttribute('aria-pressed', 'false');
            previewBtn.classList.remove('note-toolbar-btn--active');
            setToolbarFormattingEnabled(true);
            textarea.focus();
        }
    }

    if (storeUrl && saveBtn) {
        root.addEventListener('submit', (e) => {
            e.preventDefault();
            void saveNote();
        });
    }

    /** @param {string} message */
    function setSaveStatus(message, isError = false) {
        if (!saveStatus) {
            return;
        }
        if (!message) {
            saveStatus.hidden = true;
            saveStatus.textContent = '';
            saveStatus.classList.remove('text-rose-600', 'dark:text-rose-400', 'text-emerald-700', 'dark:text-emerald-400');
            return;
        }
        saveStatus.hidden = false;
        saveStatus.textContent = message;
        saveStatus.classList.toggle('text-rose-600', isError);
        saveStatus.classList.toggle('dark:text-rose-400', isError);
        saveStatus.classList.toggle('text-emerald-700', !isError);
        saveStatus.classList.toggle('dark:text-emerald-400', !isError);
    }

    function resetEditorAfterSave() {
        textarea.value = '';
        if (timestampInput) {
            timestampInput.value = '';
        }
        if (timestampLabel) {
            timestampLabel.classList.add('hidden');
            timestampLabel.textContent = '';
        }
        if (previewMode) {
            exitPreviewMode();
        }
    }

    function updateNotesTabBadge() {
        if (!notesTabBtn || !notesList) {
            return;
        }
        const count = notesList.querySelectorAll('[data-note-id]').length;
        let badge = notesTabBtn.querySelector('.watch-tab-badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'watch-tab-badge tabular-nums';
                notesTabBtn.appendChild(badge);
            }
            badge.textContent = String(count);
        } else if (badge) {
            badge.remove();
        }
    }

    /**
     * @param {string} html
     * @param {number} noteId
     */
    function appendSavedNote(html, noteId) {
        if (!notesList || !notesEmpty) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const item = wrapper.firstElementChild;
        if (!item || item.nodeName !== 'LI') {
            return;
        }

        item.setAttribute('data-note-id', String(noteId));
        notesList.appendChild(item);
        notesList.hidden = false;
        notesEmpty.classList.add('hidden');
        updateNotesTabBadge();
    }

    async function saveNote() {
        const body = textarea.value.trim();
        if (body === '') {
            setSaveStatus('Write something before saving.', true);
            textarea.focus();
            return;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const timestampRaw = timestampInput?.value.trim() ?? '';
        const payload = { body };
        if (timestampRaw !== '') {
            payload.timestamp_seconds = Number(timestampRaw);
        }

        const defaultLabel = saveBtn.textContent;
        saveBtn.disabled = true;
        setSaveStatus('');

        try {
            const res = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                const message =
                    data?.message ??
                    (data?.errors?.body?.[0] ?? 'Could not save the note. Try again.');
                setSaveStatus(message, true);
                return;
            }

            if (data.note?.html && data.note?.id) {
                appendSavedNote(data.note.html, data.note.id);
            }

            resetEditorAfterSave();
            setSaveStatus(data.message ?? 'Note saved.');
            window.setTimeout(() => setSaveStatus(''), 3000);
        } catch {
            setSaveStatus('Could not save the note. Check your connection and try again.', true);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = defaultLabel;
        }
    }
}

/**
 * Apply a markdown action to the current selection in `textarea`.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {string} action
 */
function apply(textarea, action) {
    switch (action) {
        case 'bold':
            wrap(textarea, '**', '**', 'bold text');
            return;
        case 'italic':
            wrap(textarea, '*', '*', 'italic text');
            return;
        case 'strike':
            wrap(textarea, '~~', '~~', 'strikethrough');
            return;
        case 'code':
            wrap(textarea, '`', '`', 'code');
            return;
        case 'heading':
            toggleLinePrefix(textarea, '### ');
            return;
        case 'ul':
            toggleLinePrefix(textarea, '- ');
            return;
        case 'ol':
            renumberAsList(textarea);
            return;
        case 'quote':
            toggleLinePrefix(textarea, '> ');
            return;
        case 'link':
            insertLink(textarea);
            return;
    }
}

/**
 * Wrap (or unwrap) the selection with `before` / `after` delimiters.
 *
 * If the current selection is already wrapped with the same delimiters we
 * strip them so the toolbar button toggles cleanly.
 */
function wrap(textarea, before, after, placeholder) {
    const { value, selectionStart: start, selectionEnd: end } = textarea;
    const selected = value.slice(start, end);

    if (
        selected.startsWith(before)
        && selected.endsWith(after)
        && selected.length >= before.length + after.length
    ) {
        const inner = selected.slice(before.length, selected.length - after.length);
        replaceSelection(textarea, inner, start, start + inner.length);
        return;
    }

    if (selected.length === 0) {
        const insert = before + placeholder + after;
        replaceSelection(
            textarea,
            insert,
            start + before.length,
            start + before.length + placeholder.length,
        );
        return;
    }

    const insert = before + selected + after;
    replaceSelection(textarea, insert, start + before.length, end + before.length);
}

/**
 * Toggle a per-line prefix (e.g. "- ", "> ", "### ").
 *
 * Operates on every line touched by the selection. If every targeted line
 * already has the prefix, all of them are stripped; otherwise the prefix is
 * applied to lines that don't have it yet.
 */
function toggleLinePrefix(textarea, prefix) {
    const { value, selectionStart: start, selectionEnd: end } = textarea;
    const lineStart = value.lastIndexOf('\n', start - 1) + 1;
    const lineEnd = (() => {
        const nl = value.indexOf('\n', end);
        return nl === -1 ? value.length : nl;
    })();

    const block = value.slice(lineStart, lineEnd);
    const lines = block.split('\n');
    const allHavePrefix = lines.every((line) => line.startsWith(prefix));

    const transformed = lines
        .map((line) => {
            if (allHavePrefix) return line.slice(prefix.length);
            if (line.startsWith(prefix)) return line;
            return prefix + line;
        })
        .join('\n');

    replaceSelection(textarea, transformed, lineStart, lineStart + transformed.length);
}

/**
 * Convert touched lines into a numbered list, re-numbering from 1.
 *
 * If every targeted line already begins with `N. `, strip the numbers.
 */
function renumberAsList(textarea) {
    const { value, selectionStart: start, selectionEnd: end } = textarea;
    const lineStart = value.lastIndexOf('\n', start - 1) + 1;
    const lineEnd = (() => {
        const nl = value.indexOf('\n', end);
        return nl === -1 ? value.length : nl;
    })();

    const block = value.slice(lineStart, lineEnd);
    const lines = block.split('\n');
    const numberRe = /^\d+\.\s/;
    const allNumbered = lines.every((line) => numberRe.test(line));

    const transformed = allNumbered
        ? lines.map((line) => line.replace(numberRe, '')).join('\n')
        : lines.map((line, idx) => `${idx + 1}. ${line.replace(numberRe, '')}`).join('\n');

    replaceSelection(textarea, transformed, lineStart, lineStart + transformed.length);
}

function insertLink(textarea) {
    const { value, selectionStart: start, selectionEnd: end } = textarea;
    const selected = value.slice(start, end);

    const url = window.prompt('Link URL', 'https://');
    if (!url) return;

    const label = selected.length > 0 ? selected : window.prompt('Link text', 'link text') || 'link';
    const insert = `[${label}](${url})`;
    replaceSelection(textarea, insert, start + 1, start + 1 + label.length);
}

/**
 * Replace [start..end] with `text` and set the new selection to
 * [selStart..selEnd]. Uses execCommand when available so the change is part of
 * the textarea's undo stack.
 */
function replaceSelection(textarea, text, selStart, selEnd) {
    textarea.focus();
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;

    let inserted = false;
    if (typeof document.execCommand === 'function') {
        textarea.setSelectionRange(start, end);
        try {
            inserted = document.execCommand('insertText', false, text);
        } catch (_err) {
            inserted = false;
        }
    }

    if (!inserted) {
        textarea.setRangeText(text, start, end, 'end');
    }

    textarea.selectionStart = selStart;
    textarea.selectionEnd = selEnd;
}
