/**
 * Authoring form: tags, markdown editor + live preview, test-case manager
 * (reorder, duplicate, paste many, undoable delete), reference-solution
 * checker/filler, hints, readiness checklist and unsaved-changes guard.
 */
import Sortable from 'sortablejs';
import { createEditor } from './editor.js';
import {
    FUNCTION_LANGUAGES, TYPE_GROUPS, checkValue, looksLikeLeetcode, minifyJson, normalizeValue,
    parseLeetcodeExamples, parseSignature, splitArgs, starterFor,
} from './signature.js';
import { debounce, el, postJson, postNdjson, store, toast } from './util.js';

const form = document.querySelector('[data-problem-form]');
const $ = (sel, scope = form) => scope.querySelector(sel);
const $$ = (sel, scope = form) => [...scope.querySelectorAll(sel)];

const MAX_TESTS = 50;

/* Function signature state (see the "Input format" section further down). */
const sigCfg = JSON.parse(document.querySelector('[data-sig-config]').textContent);
let uidSeq = 0;
const sig = {
    mode: sigCfg.mode === 'function' ? 'function' : 'stdin',
    name: sigCfg.signature?.name ?? '',
    returns: sigCfg.signature?.returns ?? 'int',
    params: (sigCfg.signature?.params ?? []).map((p) => ({ uid: ++uidSeq, name: p.name ?? '', type: p.type ?? 'int' })),
};
const fnMode = () => sig.mode === 'function';
const plainSig = () => ({ name: sig.name, params: sig.params.map(({ name, type }) => ({ name, type })), returns: sig.returns });
const IDENT = /^[A-Za-z_][A-Za-z0-9_]{0,39}$/;
const RESERVED = new Set(sigCfg.reserved);
const rowArgs = new WeakMap(); // test row → { [param uid]: raw text as typed }
let refEditor = null;
let verified = false; // every test passed against the current reference since the last edit
const refStatus = $('[data-ref-status]');
let dirty = false;
const markDirty = () => {
    if (dirty) return;
    dirty = true;
    $('[data-dirty-label]').textContent = '● Unsaved changes';
};
form.addEventListener('input', markDirty);
form.addEventListener('change', markDirty);
form.addEventListener('submit', () => { dirty = false; });
window.addEventListener('beforeunload', (e) => {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
});
document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); form.requestSubmit(); }
});

/* ───────── Tags ───────── */
(function initTags() {
    const list = $('[data-tag-list]');
    const input = $('[data-tag-input]');
    const have = () => $$('[data-tag] input', list).map((i) => i.value);
    const add = (raw) => {
        const tag = raw.trim().toLowerCase().replace(/,+$/, '');
        if (!tag || have().includes(tag) || have().length >= 10) return;
        const chip = el('span', 'problem-tag');
        chip.dataset.tag = '';
        const hidden = el('input');
        hidden.type = 'hidden';
        hidden.name = 'tags[]';
        hidden.value = tag;
        const rm = el('button', '', '✕');
        rm.type = 'button';
        rm.dataset.removeTag = '';
        rm.setAttribute('aria-label', `Remove tag ${tag}`);
        chip.append(tag, hidden, rm);
        list.append(chip);
        markDirty();
    };
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(input.value); input.value = ''; }
        else if (e.key === 'Backspace' && input.value === '') list.lastElementChild?.remove();
    });
    input.addEventListener('blur', () => { add(input.value); input.value = ''; });
    list.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove-tag]')) { e.target.closest('[data-tag]').remove(); markDirty(); }
    });
})();

/* ───────── Statement: toolbar, scaffolding, live preview ───────── */
(function initStatement() {
    const ta = $('[data-statement]');
    const wrap = $('[data-statement-wrap]');
    const preview = $('[data-statement-preview]');
    const body = $('[data-preview-body]');
    let mode = 'write';

    const wrapSelection = (before, after = before, placeholder = '') => {
        const { selectionStart: s, selectionEnd: e, value } = ta;
        const chosen = value.slice(s, e) || placeholder;
        ta.setRangeText(before + chosen + after, s, e, 'end');
        ta.setSelectionRange(s + before.length, s + before.length + chosen.length);
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const prefixLines = (fn) => {
        const { selectionStart: s, selectionEnd: e, value } = ta;
        const start = value.lastIndexOf('\n', s - 1) + 1;
        const block = value.slice(start, e) || 'item';
        ta.setRangeText(block.split('\n').map(fn).join('\n'), start, e, 'end');
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const actions = {
        bold: () => wrapSelection('**', '**', 'bold'),
        italic: () => wrapSelection('*', '*', 'italic'),
        code: () => wrapSelection('`', '`', 'code'),
        codeblock: () => wrapSelection('\n```\n', '\n```\n', 'code'),
        ul: () => prefixLines((l) => `- ${l}`),
        ol: () => prefixLines((l, i) => `${i + 1}. ${l}`),
        link: () => wrapSelection('[', '](https://)', 'text'),
    };
    $$('[data-md]').forEach((b) => b.addEventListener('click', () => actions[b.dataset.md]?.()));

    const scaffolds = {
        Input: '\n### Input\n\nDescribe the input format.\n',
        Output: '\n### Output\n\nDescribe what to print.\n',
        Constraints: '\n### Constraints\n\n- 1 ≤ n ≤ 10^5\n',
        Example: '\n### Example\n\nExplain what happens in the example.\n',
    };
    $$('[data-scaffold]').forEach((b) => b.addEventListener('click', () => {
        const pos = ta.value.length;
        ta.setRangeText(scaffolds[b.dataset.scaffold], pos, pos, 'end');
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }));

    ta.addEventListener('keydown', (e) => {
        if (!(e.metaKey || e.ctrlKey)) return;
        const k = e.key.toLowerCase();
        if (k === 'b') { e.preventDefault(); actions.bold(); }
        else if (k === 'i') { e.preventDefault(); actions.italic(); }
        else if (k === 'e') { e.preventDefault(); actions.code(); }
    });

    const refresh = debounce(async () => {
        if (mode === 'write') return;
        try {
            const { html } = await postJson(form.dataset.previewUrl, { body: ta.value });
            body.innerHTML = html || '<p class="text-slate-500">Nothing to preview yet.</p>';
        } catch (err) {
            body.textContent = err.message;
        }
    }, 300);
    ta.addEventListener('input', refresh);

    $$('[data-statement-mode]').forEach((r) => r.addEventListener('change', () => {
        mode = r.value;
        ta.classList.toggle('hidden', mode === 'preview');
        preview.classList.toggle('hidden', mode === 'write');
        wrap.classList.toggle('lg:grid-cols-2', mode === 'split');
        refresh();
    }));
})();

/* ───────── Test cases ───────── */
const list = $('[data-tests]');
const template = $('[data-test-template]');
let nextIndex = list.children.length;

const rows = () => $$('[data-test-row]', list);
const field = (row, name) => row.querySelector(`[data-field="${name}"]`);
const kb = (s) => (new Blob([s]).size / 1024).toFixed(1);

function refreshRow(row) {
    const input = field(row, 'input').value;
    const expected = field(row, 'expected').value;
    row.querySelector('[data-count="input"]').textContent = `${kb(input)} KB`;
    row.querySelector('[data-count="expected"]').textContent = `${kb(expected)} KB`;
    const one = (s) => (s.split('\n')[0] || '∅').slice(0, 40);
    if (fnMode()) {
        const vals = splitArgs(input, sig.params.length);
        const args = sig.params.map((p, i) => `${p.name}=${(vals[i] || '?').slice(0, 24)}`).join(', ');
        row.querySelector('[data-test-summary]').textContent = `(${args})  →  ${one(expected)}`;
        return;
    }
    row.querySelector('[data-test-summary]').textContent = `${one(input)}  →  ${one(expected)}`;
}

function relabel() {
    rows().forEach((row, i) => {
        row.querySelector('[data-test-label]').textContent = `Test ${i + 1}`;
    });
    $('[data-test-count]').textContent = `(${rows().length}/${MAX_TESTS})`;
    $('[data-add-test]').disabled = rows().length >= MAX_TESTS;
    updateReadiness();
}

function addRow(values = {}, { collapsed = false, after = null } = {}) {
    if (rows().length >= MAX_TESTS) { toast(`A problem can have at most ${MAX_TESTS} tests.`); return null; }
    const idx = nextIndex++;
    const holder = document.createElement('div');
    holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(idx)).trim();
    const row = holder.firstElementChild;
    field(row, 'input').value = values.input ?? '';
    field(row, 'expected').value = values.expected_output ?? '';
    row.querySelector('input[name$="[label]"]').value = values.label ?? '';
    row.querySelector(`input[name$="[is_hidden]"][value="${values.is_hidden ? 1 : 0}"]`).checked = true;
    row.classList.toggle('is-collapsed', collapsed);
    if (after) after.after(row); else list.append(row);
    if (fnMode()) prepareFunctionRow(row);
    refreshRow(row);
    relabel();
    markDirty();
    return row;
}

rows().forEach(refreshRow);
// Long lists start collapsed so the page stays scannable; rows with errors stay open.
if (rows().length > 4) rows().forEach((r) => { if (!r.classList.contains('is-error')) r.classList.add('is-collapsed'); });
relabel();

Sortable.create(list, { handle: '[data-drag-handle]', animation: 150, ghostClass: 'sortable-ghost', onEnd: () => { relabel(); markDirty(); } });

$('[data-add-test]').addEventListener('click', () => {
    const row = addRow();
    row?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    row && field(row, 'input').focus();
});
$('[data-collapse-all]').addEventListener('click', (e) => {
    const collapse = rows().some((r) => !r.classList.contains('is-collapsed'));
    rows().forEach((r) => r.classList.toggle('is-collapsed', collapse));
    e.currentTarget.textContent = collapse ? 'Expand all' : 'Collapse all';
});

list.addEventListener('input', (e) => {
    const row = e.target.closest('[data-test-row]');
    if (!row) return;
    if (fnMode()) {
        if (e.target.matches('[data-arg]')) {
            rowArgs.get(row)[e.target.dataset.arg] = e.target.value;
            syncInput(row);
        }
        validateRow(row);
    }
    refreshRow(row);
    clearCheck(row);
    invalidateReference();
    updateReadiness();
});
list.addEventListener('change', updateReadinessSoon);
// String values may be typed bare; quote them when the field loses focus.
list.addEventListener('focusout', (e) => {
    if (!fnMode()) return;
    const row = e.target.closest('[data-test-row]');
    const isArg = e.target.matches('[data-arg]');
    if (!row || !(isArg || e.target.matches('[data-field="expected"]'))) return;
    const type = isArg ? sig.params.find((p) => String(p.uid) === e.target.dataset.arg)?.type : sig.returns;
    if (type !== 'string' || e.target.value.trim() === '' || e.target.value.trim().startsWith('"')) return;
    e.target.value = normalizeValue(e.target.value, 'string');
    e.target.dispatchEvent(new Event('input', { bubbles: true }));
});
list.addEventListener('click', (e) => {
    const row = e.target.closest('[data-test-row]');
    if (!row) return;
    if (e.target.closest('[data-toggle-test]')) row.classList.toggle('is-collapsed');
    else if (e.target.closest('[data-duplicate-test]')) {
        addRow({
            input: field(row, 'input').value,
            expected_output: field(row, 'expected').value,
            label: row.querySelector('input[name$="[label]"]').value,
            is_hidden: row.querySelector('input[name$="[is_hidden]"][value="1"]').checked,
        }, { after: row });
    } else if (e.target.closest('[data-remove-test]')) removeRow(row);
});

function removeRow(row) {
    if (rows().length <= 1) { toast('A problem needs at least one test case.'); return; }
    const next = row.nextElementSibling;
    row.remove();
    relabel();
    markDirty();
    toast('Test removed.', {
        label: 'Undo',
        run: () => { next && next.isConnected ? list.insertBefore(row, next) : list.append(row); relabel(); },
    });
}

/* Paste many */
(function initPaste() {
    const dialog = $('[data-paste-dialog]');
    const text = $('[data-paste-text]');
    const count = $('[data-paste-count]');
    const parse = () => (fnMode() && looksLikeLeetcode(text.value) ? parseLeetcodeExamples(text.value, plainSig()) : null) ?? text.value.split(/^===\s*$/m)
        .map((chunk) => chunk.replace(/^\n+|\n+$/g, ''))
        .filter((chunk) => chunk.trim() !== '')
        .map((chunk) => {
            const [input, ...rest] = chunk.split(/^---\s*$/m);
            return { input: input.replace(/\n+$/, ''), expected_output: rest.join('---').replace(/^\n+|\n+$/g, '') };
        });
    text.addEventListener('input', () => {
        const n = parse().length;
        count.textContent = `${n} ${n === 1 ? 'case' : 'cases'} parsed`;
    });
    $('[data-paste-open]').addEventListener('click', () => dialog.showModal());
    $('[data-paste-cancel]').addEventListener('click', () => dialog.close());
    $('[data-paste-import]').addEventListener('click', () => {
        const hidden = $('[data-paste-hidden]').checked;
        const cases = parse();
        // A single untouched blank starter row is replaced rather than kept.
        const only = rows();
        if (only.length === 1 && field(only[0], 'input').value === '' && field(only[0], 'expected').value === '') only[0].remove();
        cases.forEach((c) => addRow({ ...c, is_hidden: hidden }, { collapsed: cases.length > 4 }));
        text.value = '';
        count.textContent = '0 cases parsed';
        dialog.close();
        toast(`${cases.length} ${cases.length === 1 ? 'test' : 'tests'} added.`);
    });
})();

/* ───────── Hints ───────── */
(function initHints() {
    const wrap = $('[data-hints]');
    const tpl = $('[data-hint-template]');
    $('[data-add-hint]').addEventListener('click', () => {
        if (wrap.children.length >= 10) return;
        wrap.insertAdjacentHTML('beforeend', tpl.innerHTML);
        wrap.lastElementChild.querySelector('textarea').focus();
        markDirty();
    });
    wrap.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove-hint]')) { e.target.closest('[data-hint-row]').remove(); markDirty(); }
    });
})();

/* ───────── Settings ───────── */
const limit = $('[data-limit]');
limit.addEventListener('input', () => { $('[data-limit-label]').textContent = `${(limit.value / 1000).toFixed(1)}s`; });

/* ───────── Reference solution ───────── */

function invalidateReference() {
    if (!verified) return;
    verified = false;
    refStatus && (refStatus.textContent = 'Tests changed since the last check — run Check all again.');
    updateReadiness();
}

function clearCheck(row) {
    row.classList.remove('is-check-pass', 'is-check-fail');
    row.querySelector('[data-test-status]').textContent = '';
}

if ($('[data-ref-editor]')) {
    const codeField = $('[data-ref-code]');
    const langSelect = $('[data-ref-language]');
    if (form.dataset.hasReference !== '1') {
        // New reference: default to the language you last solved in, else Python.
        const options = [...langSelect.options].map((o) => o.value);
        const last = store.get('home-teacher-problem-last-lang');
        const pick = [last, 'python'].find((l) => l && options.includes(l));
        if (pick) langSelect.value = pick;
    }
    refEditor = createEditor({
        parent: $('[data-ref-editor]'),
        language: langSelect.value,
        doc: codeField.value,
        onChange: (text) => {
            codeField.value = text;
            markDirty();
            if (verified) { verified = false; refStatus.textContent = 'Solution changed — run Check all again.'; }
            updateReadiness();
        },
    });
    langSelect.addEventListener('change', () => { refEditor.setLanguage(langSelect.value); verified = false; updateReadiness(); });

    $('[data-ref-stub]')?.addEventListener('click', () => {
        const hadCode = refEditor.getDoc().trim() !== '';
        refEditor.setDoc(starterFor(langSelect.value, plainSig()));
        refEditor.focus();
        if (hadCode) toast('Replaced with the function stub.', { label: 'Undo', run: () => refEditor.undo() });
    });

    let controller = null;
    const buttons = $$('[data-ref-run]');
    const setBusy = (busy) => buttons.forEach((b) => (b.disabled = busy));

    buttons.forEach((btn) => btn.addEventListener('click', async () => {
        const mode = btn.dataset.refRun;
        const code = refEditor.getDoc();
        if (code.trim() === '') { toast('Write a reference solution first.'); refEditor.focus(); return; }

        const trs = rows();
        const tests = trs.map((r) => ({ input: field(r, 'input').value, expected_output: field(r, 'expected').value }));
        trs.forEach((r) => { clearCheck(r); r.querySelector('[data-test-status]').textContent = '…'; });
        refStatus.textContent = 'Running your reference solution…';
        setBusy(true);
        controller = new AbortController();

        const results = [];
        try {
            await postNdjson(form.dataset.referenceUrl, {
                language: langSelect.value,
                code,
                compare_mode: $('[data-compare]:checked:not(:disabled)')?.value,
                time_limit_ms: Number(limit.value),
                tests,
                io_mode: sig.mode,
                signature: fnMode() ? plainSig() : null,
            }, (ev) => {
                if (ev.type !== 'case') return;
                results[ev.index] = ev;
                const row = trs[ev.index];
                if (!row) return;
                const ok = ev.passed;
                row.classList.toggle('is-check-pass', ok);
                row.classList.toggle('is-check-fail', !ok);
                const status = row.querySelector('[data-test-status]');
                status.textContent = ok ? '✓ matches' : ev.status === 'wrong_answer' ? '✗ differs' : ev.status === 'invalid' ? '✗ invalid arguments' : ev.status === 'compile_error' ? '✗ compile error' : `✗ ${ev.status.replace('_', ' ')}`;
                status.className = `text-xs font-semibold ${ok ? 'text-emerald-600' : 'text-rose-600'}`;
            }, controller.signal);
        } catch (err) {
            refStatus.textContent = err.message;
            trs.forEach(clearCheck);
            setBusy(false);
            return;
        }
        setBusy(false);

        const crashed = results.filter((r) => r && ['runtime_error', 'time_limit', 'compile_error'].includes(r.status));
        const invalid = results.filter((r) => r?.status === 'invalid');
        const broken = crashed.length + invalid.length;
        const firstError = crashed.map((r) => { const l = (r.stderr || '').trim().split('\n'); return r.status === 'compile_error' ? l[0] : l.pop(); }).find(Boolean)
            ?? (crashed.some((r) => r.status === 'time_limit') ? 'time limit exceeded' : '');
        const crashNote = (crashed.length
            ? ` ${crashed.length} crashed or timed out${firstError ? ` (${firstError.slice(0, 160)})` : ''} — is the language selector right?`
            : '') + (invalid.length ? ` ${invalid.length} ${invalid.length === 1 ? 'test has' : 'tests have'} invalid arguments (${invalid[0].stderr}).` : '');
        if (mode === 'check') {
            const passed = results.filter((r) => r?.passed).length;
            verified = passed === trs.length;
            refStatus.textContent = verified
                ? `✓ All ${trs.length} tests match your reference solution.`
                : `${passed}/${trs.length} tests match.${crashNote} Expand a ✗ row to compare.`;
        } else {
            const targets = trs.map((r, i) => ({ r, i, res: results[i] }))
                .filter(({ r, res }) => res && res.actual != null && !['runtime_error', 'time_limit', 'invalid', 'compile_error'].includes(res.status)
                    && (mode === 'regenerate' || field(r, 'expected').value.trim() === ''));
            const changing = targets.filter(({ r, res }) => field(r, 'expected').value !== res.actual.replace(/\n$/, ''));
            if (changing.length === 0) {
                refStatus.textContent = broken ? `Nothing filled.${crashNote}` : 'Nothing to change — outputs already match.';
                return;
            }
            if (mode === 'regenerate' && !confirm(`Replace ${changing.length} expected ${changing.length === 1 ? 'output' : 'outputs'} with your solution’s output?`)) {
                refStatus.textContent = 'Cancelled — nothing changed.';
                return;
            }
            const before = changing.map(({ r }) => [r, field(r, 'expected').value]);
            changing.forEach(({ r, res }) => {
                field(r, 'expected').value = res.actual.replace(/\n$/, '');
                refreshRow(r);
                r.classList.remove('is-check-fail');
                r.classList.add('is-check-pass');
            });
            verified = broken === 0 && targets.length === trs.length;
            refStatus.textContent = `Filled ${changing.length} expected ${changing.length === 1 ? 'output' : 'outputs'}.${broken ? ` Skipped:${crashNote}` : ''}`;
            markDirty();
            toast(`${changing.length} outputs filled.`, {
                label: 'Undo',
                run: () => { before.forEach(([r, v]) => { field(r, 'expected').value = v; refreshRow(r); clearCheck(r); }); verified = false; updateReadiness(); },
            });
        }
        updateReadiness();
    }));
}

/* ───────── Input format: function signature builder ───────── */

function identifierError(name, what) {
    if (name.trim() === '') return `${what} is required`;
    if (!IDENT.test(name)) return `${what} must start with a letter or _ and use only letters, digits and _`;
    if (RESERVED.has(name) || name.startsWith('_ht')) return `“${name}” is reserved in one of the languages`;
    return null;
}
function functionNameError() { return identifierError(sig.name, 'Function name'); }
function paramNameError(p) {
    const err = identifierError(p.name, 'Name');
    if (err) return err;
    return sig.params.filter((q) => q.name === p.name).length > 1 ? 'Duplicate name' : null;
}

/** One line of the stored input: minified JSON when valid, else the raw text on one line. */
function lineOf(raw) {
    const text = (raw ?? '').trim();
    try { JSON.parse(text); return minifyJson(text); } catch { return text.replace(/\s*\n\s*/g, ' '); }
}

function loadArgs(row) {
    const vals = splitArgs(field(row, 'input').value, sig.params.length);
    rowArgs.set(row, Object.fromEntries(sig.params.map((p, i) => [p.uid, vals[i]])));
}

function syncInput(row) {
    const vals = rowArgs.get(row) ?? {};
    field(row, 'input').value = sig.params.map((p) => lineOf(vals[p.uid])).join('\n');
}

function renderArgs(row) {
    const wrap = row.querySelector('[data-args]');
    const vals = rowArgs.get(row) ?? {};
    if (!sig.params.length) {
        wrap.replaceChildren(el('p', 'rounded-lg border border-dashed border-slate-300 p-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-400', 'No arguments — the function is called with none.'));
        return;
    }
    wrap.replaceChildren(...sig.params.map((p) => {
        const box = el('div');
        const head = el('div', 'mb-1 flex items-center justify-between gap-2 text-xs');
        const label = el('label', 'flex items-center gap-1.5 text-slate-700 dark:text-slate-300');
        const id = `arg-${row.querySelector('[data-field="input"]').id}-${p.uid}`;
        label.htmlFor = id;
        label.append(el('span', 'font-mono font-semibold', p.name || '?'), el('span', 'problem-type', p.type));
        head.append(label, el('span', 'truncate text-rose-600', ''));
        head.lastChild.dataset.argError = '';
        const ta = el('textarea', 'input-field resize-y font-mono text-sm');
        ta.id = id;
        ta.rows = 1;
        ta.spellcheck = false;
        ta.dataset.arg = String(p.uid);
        ta.placeholder = { int: '42', float: '3.14', string: '"text"', bool: 'true' }[p.type] ?? (p.type.endsWith('[][]') ? '[[1,2],[3,4]]' : '[1,2,3]');
        ta.value = vals[p.uid] ?? '';
        box.append(head, ta);
        return box;
    }));
}

/** Flag argument / expected values that don't fit the signature. */
function validateRow(row) {
    let bad = 0;
    row.querySelectorAll('[data-arg]').forEach((ta) => {
        const p = sig.params.find((q) => String(q.uid) === ta.dataset.arg);
        const err = p ? checkValue(ta.value, p.type) : null;
        ta.classList.toggle('is-invalid', !!err);
        const note = ta.parentElement.querySelector('[data-arg-error]');
        if (note) note.textContent = err ?? '';
        if (err) bad++;
    });
    const exp = field(row, 'expected');
    const note = row.querySelector('[data-expected-note]');
    const expErr = exp.value.trim() === '' ? null : checkValue(exp.value, sig.returns);
    exp.classList.toggle('is-invalid', !!expErr);
    note.textContent = expErr ?? '';
    note.classList.toggle('hidden', !expErr);
    if (expErr) bad++;
    row.dataset.invalidArgs = bad ? '1' : '0';
}

function prepareFunctionRow(row) {
    if (!rowArgs.has(row)) loadArgs(row);
    row.querySelector('[data-stdin-wrap]').classList.add('hidden');
    row.querySelector('[data-args]').classList.remove('hidden');
    row.querySelector('[data-expected-label]').textContent = `Expected return (${sig.returns})`;
    field(row, 'expected').rows = 2;
    renderArgs(row);
    syncInput(row);
    validateRow(row);
}

function refreshAllRows() {
    rows().forEach((r) => {
        if (fnMode()) prepareFunctionRow(r);
        refreshRow(r);
    });
    renderPreview();
    updateReadiness();
}

const paramsWrap = $('[data-params]');

function typeSelect(value, name) {
    const select = el('select', 'input-field w-auto py-1.5 font-mono text-sm');
    if (name) select.name = name;
    TYPE_GROUPS.forEach(([group, types]) => {
        const og = document.createElement('optgroup');
        og.label = group;
        types.forEach((t) => { const o = el('option', '', t); o.value = t; og.append(o); });
        select.append(og);
    });
    select.value = value;
    return select;
}

function renderParams() {
    paramsWrap.replaceChildren(...sig.params.map((p, i) => {
        const row = el('div', 'problem-param-row');
        row.dataset.param = String(p.uid);
        const handle = el('span', 'cursor-grab select-none px-1 text-slate-400', '⠿');
        handle.dataset.paramHandle = '';
        handle.title = 'Drag to reorder';
        const name = el('input', 'input-field font-mono text-sm');
        name.type = 'text';
        name.name = `signature[params][${i}][name]`;
        name.value = p.name;
        name.maxLength = 40;
        name.spellcheck = false;
        name.autocomplete = 'off';
        name.placeholder = 'name';
        name.setAttribute('aria-label', `Parameter ${i + 1} name`);
        name.dataset.paramName = '';
        const type = typeSelect(p.type, `signature[params][${i}][type]`);
        type.setAttribute('aria-label', `Parameter ${i + 1} type`);
        type.dataset.paramType = '';
        const rm = el('button', 'btn-ghost px-2 py-1 text-rose-600', '✕');
        rm.type = 'button';
        rm.dataset.removeParam = '';
        rm.setAttribute('aria-label', `Remove parameter ${p.name}`);
        const err = el('p', 'col-span-full text-xs text-rose-600', '');
        err.dataset.paramError = '';
        row.append(handle, name, type, rm, err);
        return row;
    }));
    $('[data-params-empty]').classList.toggle('hidden', sig.params.length > 0);
    $('[data-param-count]').textContent = `(${sig.params.length}/${8})`;
    $('[data-add-param]').disabled = sig.params.length >= 8;
    paintParamErrors();
}

function paintParamErrors() {
    $$('[data-param]', paramsWrap).forEach((row) => {
        const p = sig.params.find((q) => String(q.uid) === row.dataset.param);
        const err = p && p.name !== '' ? paramNameError(p) : null;
        row.querySelector('[data-param-error]').textContent = err ?? '';
        row.querySelector('[data-param-name]').classList.toggle('is-invalid', !!err);
    });
    const nameErr = sig.name === '' ? null : functionNameError();
    $('[data-sig-name-error]').textContent = nameErr ?? '';
    $('[data-sig-name]').classList.toggle('is-invalid', !!nameErr);
}

function renderPreview() {
    const lang = $('[data-sig-preview-lang]:checked')?.value ?? 'python';
    $('[data-sig-preview]').textContent = starterFor(lang, plainSig());
    // Placeholders for the per-language starter overrides follow the mode.
    $$('[data-starter-override]').forEach((ta) => {
        const l = ta.dataset.starterOverride;
        ta.placeholder = !fnMode() ? ta.dataset.stdinPlaceholder
            : FUNCTION_LANGUAGES.includes(l) ? starterFor(l, plainSig()) : 'Not available for function problems yet.';
    });
}

function snapshot() {
    return { params: sig.params.map((p) => ({ ...p })), values: rows().map((r) => [r, { ...(rowArgs.get(r) ?? {}) }]) };
}
function restore(snap) {
    sig.params = snap.params;
    snap.values.forEach(([r, v]) => rowArgs.set(r, v));
    renderParams();
    refreshAllRows();
    markDirty();
}

function nextParamName() {
    const pool = ['nums', 'target', 'k', 's', 'n', 'grid', 'words', 'x', 'y', 'm'];
    return pool.find((n) => !sig.params.some((p) => p.name === n)) ?? `arg${sig.params.length + 1}`;
}

$('[data-add-param]').addEventListener('click', () => {
    if (sig.params.length >= 8) return;
    const p = { uid: ++uidSeq, name: nextParamName(), type: 'int' };
    sig.params.push(p);
    renderParams();
    refreshAllRows();
    markDirty();
    const input = paramsWrap.lastElementChild.querySelector('[data-param-name]');
    input.focus();
    input.select();
    const filled = rows().filter((r) => field(r, 'expected').value.trim() !== '').length;
    if (filled) {
        toast(`Every test now needs a value for “${p.name}”.`, {
            label: 'Set for all…',
            run: () => {
                const v = prompt(`Value of ${p.name} for every test that has none (JSON):`);
                if (v == null || v.trim() === '') return;
                rows().forEach((r) => {
                    const vals = rowArgs.get(r);
                    if (vals && (vals[p.uid] ?? '').trim() === '') vals[p.uid] = normalizeValue(v, p.type);
                });
                refreshAllRows();
                markDirty();
            },
        });
    }
});

paramsWrap.addEventListener('input', (e) => {
    const row = e.target.closest('[data-param]');
    const p = row && sig.params.find((q) => String(q.uid) === row.dataset.param);
    if (!p || !e.target.matches('[data-param-name]')) return;
    p.name = e.target.value.trim();
    paintParamErrors();
    refreshAllRows();
});
paramsWrap.addEventListener('change', (e) => {
    const row = e.target.closest('[data-param]');
    const p = row && sig.params.find((q) => String(q.uid) === row.dataset.param);
    if (!p || !e.target.matches('[data-param-type]')) return;
    p.type = e.target.value;
    refreshAllRows();
    invalidateReference();
});
paramsWrap.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-remove-param]');
    if (!btn) return;
    const uid = btn.closest('[data-param]').dataset.param;
    const p = sig.params.find((q) => String(q.uid) === uid);
    const used = rows().filter((r) => (rowArgs.get(r)?.[uid] ?? '').trim() !== '').length;
    const snap = snapshot();
    sig.params = sig.params.filter((q) => q !== p);
    renderParams();
    refreshAllRows();
    markDirty();
    invalidateReference();
    if (used) toast(`Removed “${p.name}” from ${used} ${used === 1 ? 'test' : 'tests'}.`, { label: 'Undo', run: () => restore(snap) });
});
Sortable.create(paramsWrap, {
    handle: '[data-param-handle]',
    animation: 150,
    ghostClass: 'sortable-ghost',
    onEnd: () => {
        const order = $$('[data-param]', paramsWrap).map((r) => r.dataset.param);
        sig.params.sort((a, b) => order.indexOf(String(a.uid)) - order.indexOf(String(b.uid)));
        renderParams();
        refreshAllRows();
        markDirty();
        invalidateReference();
    },
});

const nameInput = $('[data-sig-name]');
nameInput.value = sig.name;
nameInput.addEventListener('input', () => { sig.name = nameInput.value.trim(); paintParamErrors(); renderPreview(); updateReadiness(); });

const returnsSlot = $('[data-sig-returns]');
const returnsSelect = typeSelect(sig.returns, 'signature[returns]');
returnsSelect.id = returnsSlot.id;
returnsSelect.className = returnsSlot.className;
returnsSelect.dataset.sigReturns = '';
returnsSlot.replaceWith(returnsSelect);
returnsSelect.addEventListener('change', () => {
    sig.returns = returnsSelect.value;
    $$('[data-expected-label]').forEach((l) => (l.textContent = `Expected return (${sig.returns})`));
    refreshAllRows();
    invalidateReference();
});

$$('[data-sig-preview-lang]').forEach((r) => r.addEventListener('change', renderPreview));

$('[data-sig-paste-apply]').addEventListener('click', () => {
    const status = $('[data-sig-paste-status]');
    const parsed = parseSignature($('[data-sig-paste-text]').value);
    if (typeof parsed === 'string') { status.textContent = parsed; status.className = 'text-xs text-rose-600'; return; }
    const snap = snapshot();
    const prevName = sig.name;
    const prevReturns = sig.returns;
    // Keep each position's uid so values already typed into tests stay put.
    sig.params = parsed.params.map((p, i) => ({ uid: sig.params[i]?.uid ?? ++uidSeq, name: p.name, type: p.type }));
    sig.name = parsed.name;
    sig.returns = parsed.returns;
    nameInput.value = sig.name;
    returnsSelect.value = sig.returns;
    renderParams();
    refreshAllRows();
    markDirty();
    status.textContent = '';
    $('[data-sig-paste]').open = false;
    toast(`Signature set to ${parsed.name}(${parsed.params.map((p) => p.name).join(', ')}).`, {
        label: 'Undo',
        run: () => {
            sig.name = prevName; sig.returns = prevReturns;
            nameInput.value = prevName; returnsSelect.value = prevReturns;
            restore(snap);
        },
    });
});

/** Show/hide everything that depends on stdin vs function mode. */
function applyMode() {
    const fn = fnMode();
    $$('[data-mode-panel]').forEach((n) => n.classList.toggle('hidden', n.dataset.modePanel !== sig.mode));
    $$('[data-mode-text]').forEach((n) => n.classList.toggle('hidden', n.dataset.modeText !== sig.mode));
    $$('[data-stdin-wrap]').forEach((n) => n.classList.toggle('hidden', fn));
    $$('[data-args]').forEach((n) => n.classList.toggle('hidden', !fn));
    $$('[data-expected-label]').forEach((l) => (l.textContent = fn ? `Expected return (${sig.returns})` : 'Expected output'));
    $$('[data-field="expected"]').forEach((t) => { t.rows = fn ? 2 : 4; });
    if (!fn) {
        rows().forEach((r) => { field(r, 'expected').classList.remove('is-invalid'); r.querySelector('[data-expected-note]').classList.add('hidden'); delete r.dataset.invalidArgs; });
    }

    // Only the visible set of compare modes is submitted.
    $$('[data-mode-panel] [data-compare]').forEach((r) => { r.disabled = r.closest('[data-mode-panel]').dataset.modePanel !== sig.mode; });
    const visible = $$('[data-compare]:not(:disabled)');
    if (!visible.some((r) => r.checked)) visible[0].checked = true;

    // Reference languages: only those with a harness.
    const refLang = $('[data-ref-language]');
    if (refLang) {
        [...refLang.options].forEach((o) => { o.disabled = fn && !FUNCTION_LANGUAGES.includes(o.value); });
        if (refLang.selectedOptions[0]?.disabled) {
            refLang.value = [...refLang.options].find((o) => !o.disabled)?.value ?? refLang.value;
            refLang.dispatchEvent(new Event('change'));
        }
    }
    const statement = $('[data-statement]');
    statement.placeholder = fn
        ? 'Given an array of integers nums and an integer target, return the indices of the two numbers that add up to target.'
        : 'Read two integers from stdin and print their sum.';
    refreshAllRows();
}

$$('[data-io-mode]').forEach((radio) => radio.addEventListener('change', () => {
    const next = radio.value;
    if (next === sig.mode) return;
    const hasInputs = rows().some((r) => field(r, 'input').value.trim() !== '');
    if (next === 'function' && hasInputs
        && !confirm('Switch to function mode? Each line of a test’s input becomes one argument, in parameter order.')) {
        $(`[data-io-mode][value="${sig.mode}"]`).checked = true;
        return;
    }
    sig.mode = next;
    if (next === 'function') rows().forEach(loadArgs);
    invalidateReference();
    applyMode();
}));

renderParams();
applyMode();

/* ───────── Readiness checklist + nav badges ───────── */
function updateReadiness() {
    const trs = rows();
    const hasTitle = $('[data-title]').value.trim() !== '';
    const hasStatement = $('[data-statement]').value.trim() !== '';
    const emptyExpected = trs.filter((r) => field(r, 'expected').value.trim() === '').length;
    const examples = trs.filter((r) => r.querySelector('input[name$="[is_hidden]"][value="0"]').checked).length;
    const hidden = trs.length - examples;
    const inputs = trs.map((r) => field(r, 'input').value);
    const dupes = inputs.length - new Set(inputs).size;
    const hasRef = refEditor ? refEditor.getDoc().trim() !== '' : false;

    const fnItems = [];
    if (fnMode()) {
        const nameErr = functionNameError();
        const badParams = sig.params.filter((p) => paramNameError(p)).length;
        const badRows = trs.filter((r) => r.dataset.invalidArgs === '1').length;
        fnItems.push(
            { ok: !nameErr, block: true, text: nameErr ?? `Function ${sig.name}(${sig.params.length ? '…' : ''})` },
            ...(badParams ? [{ ok: false, block: true, text: `${badParams} parameter name${badParams === 1 ? '' : 's'} to fix` }] : []),
            { ok: badRows === 0, block: true, text: badRows ? `${badRows} test${badRows === 1 ? '' : 's'} don’t match the signature` : 'All tests match the signature' },
        );
    }
    const items = [
        { ok: hasTitle, block: true, text: 'Title' },
        { ok: hasStatement, block: true, text: 'Statement' },
        ...fnItems,
        { ok: emptyExpected === 0, block: true, text: emptyExpected ? `${emptyExpected} test${emptyExpected === 1 ? '' : 's'} missing expected output` : `${trs.length} test${trs.length === 1 ? '' : 's'} with expected output` },
        { ok: examples > 0, text: examples ? `${examples} example${examples === 1 ? '' : 's'} shown to solvers` : 'No example tests — solvers see none' },
        { ok: hidden > 0, text: hidden ? `${hidden} hidden test${hidden === 1 ? '' : 's'}` : 'No hidden tests' },
        { ok: dupes === 0, text: dupes ? `${dupes} duplicate input${dupes === 1 ? '' : 's'}` : 'No duplicate inputs' },
        { ok: verified, text: verified ? 'Verified by reference solution' : hasRef ? 'Reference not checked yet' : 'No reference solution' },
    ];
    $('[data-readiness]').replaceChildren(...items.map((it) => {
        const li = el('li', 'flex items-start gap-2');
        const mark = it.ok ? '✓' : it.block ? '✗' : '!';
        const tone = it.ok ? 'text-emerald-600' : it.block ? 'text-rose-600' : 'text-amber-600';
        li.append(el('span', `w-3 shrink-0 font-bold ${tone}`, mark), el('span', 'text-slate-700 dark:text-slate-300', it.text));
        return li;
    }));

    const badge = (name, text, tone = '') => {
        const b = form.querySelector(`[data-nav-badge="${name}"]`);
        if (b) { b.textContent = text; b.className = `text-xs ${tone}`; }
    };
    badge('tests', String(trs.length), emptyExpected || trs.some((r) => fnMode() && r.dataset.invalidArgs === '1') ? 'text-rose-600' : 'text-slate-500');
    badge('format', fnMode() ? 'ƒ' : '', 'text-slate-500');
    badge('solution', verified ? '✓' : hasRef ? '!' : '', verified ? 'text-emerald-600' : 'text-amber-600');
    badge('hints', $$('[data-hint-row]').length ? String($$('[data-hint-row]').length) : '', 'text-slate-500');
}
let readinessTimer;
function updateReadinessSoon() { clearTimeout(readinessTimer); readinessTimer = setTimeout(updateReadiness, 50); }
form.addEventListener('input', updateReadinessSoon);
form.addEventListener('click', (e) => { if (e.target.closest('[data-remove-hint],[data-add-hint]')) updateReadinessSoon(); });
updateReadiness();

/* Highlight the nav entry for the section in view. */
(function initScrollSpy() {
    const links = new Map($$('[data-nav]').map((a) => [a.dataset.nav, a]));
    if (!links.size || !('IntersectionObserver' in window)) return;
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) {
                links.forEach((a) => a.classList.remove('is-active'));
                links.get(e.target.id)?.classList.add('is-active');
            }
        });
    }, { rootMargin: '-20% 0px -70% 0px' });
    links.forEach((_, id) => { const s = document.getElementById(id); s && io.observe(s); });
})();

// Jump to the first validation error after a failed save.
document.querySelector('.is-error')?.scrollIntoView({ block: 'center' });
