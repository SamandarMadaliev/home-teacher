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
    if (!textarea || !toolbar) return;

    toolbar.querySelectorAll('button[data-md]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
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
        apply(textarea, action);
    });
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
