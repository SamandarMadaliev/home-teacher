/**
 * Solve page: resizable panes, CodeMirror editor, streamed Run / Submit,
 * live per-test results with diffs, submission history, hints, shortcuts.
 */
import { createEditor } from './editor.js';
import { hintFor, renderDiff } from './diff.js';
import { initModeration, initShare } from './share.js';
import { checkValue, formatArgs, minifyJson, normalizeValue, splitArgs, starterFor } from './signature.js';
import { initSolutions } from './solutions.js';
import {
    STATUS_LABELS, STATUS_TEXT, debounce, el, errorLineFromStderr, postJson, postNdjson, relativeTime, requestJson, store, toast,
} from './util.js';

const root = document.querySelector('[data-problem-solve]');
const cfg = JSON.parse(root.querySelector('[data-config]').textContent);
const $ = (sel, scope = root) => scope.querySelector(sel);
const $$ = (sel, scope = root) => [...scope.querySelectorAll(sel)];

const LAST_LANG_KEY = 'home-teacher-problem-last-lang';
const codeKey = (lang) => `${cfg.storageKey}-code-${lang}`;
const hasRuntimes = cfg.languages.length > 0;
const sig = cfg.signature; // null for stdin problems
const starterOf = (lang) => cfg.starter[lang] ?? (sig ? starterFor(lang, sig) : '');

/* ───────── Tabs ───────── */
function initTabs(buttonAttr, panelAttr, onSelect) {
    const buttons = $$(`[${buttonAttr}]`);
    const select = (name) => {
        buttons.forEach((b) => b.setAttribute('aria-selected', String(b.getAttribute(buttonAttr) === name)));
        $$(`[${panelAttr}]`).forEach((p) => p.classList.toggle('hidden', p.getAttribute(panelAttr) !== name));
        onSelect?.(name);
    };
    buttons.forEach((b) => b.addEventListener('click', () => select(b.getAttribute(buttonAttr))));
    return select;
}

const selectStatement = initTabs('data-statement-tab', 'data-statement-panel');
let activeConsole = 'cases';
const selectConsole = initTabs('data-console-tab', 'data-console-panel', (n) => { activeConsole = n; syncRunLabel(); });

/* Mobile pane tabs */
function setMobileTab(name) {
    root.dataset.mobileTab = name;
    $$('[data-mobile-tab-btn]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.mobileTabBtn === name)));
}
$$('[data-mobile-tab-btn]').forEach((b) => b.addEventListener('click', () => setMobileTab(b.dataset.mobileTabBtn)));
setMobileTab('problem');
const isMobile = () => window.matchMedia('(max-width: 1023px)').matches;

/* ───────── Resizable panes ───────── */
function initSplitters() {
    const layoutKey = 'home-teacher-problem-layout';
    const panes = $('[data-panes]');
    const right = $('[data-right]');
    let layout = { left: 42, editor: 58 };
    try { layout = { ...layout, ...JSON.parse(store.get(layoutKey) ?? '{}') }; } catch { /* ignore */ }

    const apply = () => {
        panes.style.setProperty('--pane-left', `${layout.left}%`);
        right.style.setProperty('--pane-editor', `${layout.editor}%`);
    };
    const save = () => store.set(layoutKey, JSON.stringify(layout));
    apply();

    const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));

    $$('[data-splitter]').forEach((bar) => {
        const axis = bar.dataset.splitter;
        const move = (clientX, clientY) => {
            if (axis === 'x') {
                const r = panes.getBoundingClientRect();
                layout.left = clamp(((clientX - r.left) / r.width) * 100, 20, 70);
            } else {
                const r = right.getBoundingClientRect();
                layout.editor = clamp(((clientY - r.top) / r.height) * 100, 25, 80);
            }
            apply();
        };
        bar.addEventListener('pointerdown', (e) => {
            bar.setPointerCapture(e.pointerId);
            bar.classList.add('is-dragging');
            const onMove = (ev) => move(ev.clientX, ev.clientY);
            const onUp = () => {
                bar.classList.remove('is-dragging');
                bar.removeEventListener('pointermove', onMove);
                bar.removeEventListener('pointerup', onUp);
                save();
            };
            bar.addEventListener('pointermove', onMove);
            bar.addEventListener('pointerup', onUp);
        });
        bar.addEventListener('keydown', (e) => {
            const step = e.shiftKey ? 10 : 2;
            const dir = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 }[e.key];
            if (!dir) return;
            e.preventDefault();
            if (axis === 'x') layout.left = clamp(layout.left + dir * step, 20, 70);
            else layout.editor = clamp(layout.editor + dir * step, 25, 80);
            apply();
            save();
        });
    });
}
initSplitters();

/* ───────── Focus mode + shortcuts dialog ───────── */
function toggleFocus(force) {
    const on = force ?? !root.classList.contains('problem-focus');
    root.classList.toggle('problem-focus', on);
    document.body.classList.toggle('overflow-hidden', on);
    $('[data-focus]')?.setAttribute('aria-pressed', String(on));
}
$('[data-focus]')?.addEventListener('click', () => toggleFocus());
const shortcuts = $('[data-shortcuts-dialog]');
$$('[data-shortcuts-open]').forEach((b) => b.addEventListener('click', () => shortcuts.showModal()));
$('[data-shortcuts-close]')?.addEventListener('click', () => shortcuts.close());

document.addEventListener('keydown', (e) => {
    const t = e.target;
    const typing = t instanceof HTMLElement && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName) || t.closest('.cm-editor'));
    if (e.key === 'Escape' && root.classList.contains('problem-focus') && !shortcuts.open) toggleFocus(false);
    if (typing || e.metaKey || e.ctrlKey || e.altKey) return;
    if (e.key === '?') { e.preventDefault(); shortcuts.showModal(); }
    else if (e.key.toLowerCase() === 'f') { e.preventDefault(); toggleFocus(); }
});

/* ───────── Case board: chips + detail for a set of test cases ───────── */
function createBoard(container, { naming, getLanguage, onGoto }) {
    const chips = el('div', 'flex flex-wrap gap-2');
    chips.setAttribute('role', 'tablist');
    const detail = el('div', 'mt-4');
    container.replaceChildren(chips, detail);

    let showWs = false;
    const cases = new Map();
    let selected = null;

    const nameOf = (info, idx) => (naming === 'example' ? `Example ${idx + 1}` : `Test ${info.number}`);

    function chipState(entry) {
        if (entry.running) return { cls: 'is-running', icon: '◌', word: 'running' };
        if (!entry.row) return { cls: '', icon: '○', word: 'not run' };
        if (entry.row.passed) return { cls: 'is-pass', icon: '✓', word: 'passed' };
        return { cls: entry.row.status === 'wrong_answer' ? 'is-fail' : 'is-warn', icon: '✗', word: STATUS_LABELS[entry.row.status] ?? 'failed' };
    }

    function paintChip(number) {
        const entry = cases.get(number);
        const s = chipState(entry);
        entry.chip.className = `problem-chip ${s.cls}`;
        entry.chip.setAttribute('aria-label', `${entry.name}: ${s.word}`);
        entry.chip.replaceChildren(el('span', '', s.icon), el('span', '', entry.name + (entry.info.hidden ? ' 🔒' : '')));
    }

    function renderDetail() {
        detail.replaceChildren();
        const entry = cases.get(selected);
        if (!entry) return;
        const { info, row } = entry;
        const wrap = el('div', 'space-y-3');
        const title = el('div', 'flex flex-wrap items-center justify-between gap-2');
        const heading = el('p', 'text-sm font-semibold text-slate-900 dark:text-slate-100', entry.name + (info.label ? ` · ${info.label}` : ''));
        title.append(heading);
        if (row) {
            title.append(el('span', `text-xs font-semibold ${STATUS_TEXT[row.status] ?? ''}`,
                `${row.passed ? 'Passed' : STATUS_LABELS[row.status] ?? row.status} · ${row.duration_ms} ms`));
        }
        wrap.append(title);

        if (info.hidden) {
            wrap.append(el('p', 'rounded-lg border border-dashed border-slate-300 p-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-400',
                '🔒 Hidden test — its input and expected output aren’t shown.'));
            detail.append(wrap);
            return;
        }

        const input = row?.input ?? info.input ?? '';
        wrap.append(labelled(sig ? 'Arguments' : 'Input', sig ? formatArgs(sig, input) : input, true));
        const expectedLabel = sig ? 'Expected return' : 'Expected output';
        const actualLabel = sig ? 'Your return' : 'Your output';

        if (!row) {
            wrap.append(labelled(expectedLabel, info.expected ?? ''));
        } else if (row.status === 'wrong_answer') {
            const tools = el('label', 'flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400');
            const cb = el('input');
            cb.type = 'checkbox';
            cb.checked = showWs;
            cb.addEventListener('change', () => { showWs = cb.checked; renderDetail(); });
            tools.append(cb, document.createTextNode('Show whitespace'));
            wrap.append(tools, renderDiff(row.expected ?? '', row.actual ?? '', showWs, actualLabel));
            const hint = sig ? row.hint : hintFor(row);
            if (hint) wrap.append(el('p', 'rounded-lg bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-300', `💡 ${hint}`));
        } else if (row.passed) {
            wrap.append(labelled(actualLabel, row.actual ?? ''));
        } else {
            wrap.append(labelled(expectedLabel, row.expected ?? ''));
            if (!sig || row.actual != null) wrap.append(labelled(actualLabel, row.actual ?? ''));
        }

        if (sig && row?.stdout) wrap.append(labelled('Stdout (your prints)', row.stdout));

        if (row?.stderr) {
            wrap.append(labelled('stderr', row.stderr));
            const line = errorLineFromStderr(row.stderr, getLanguage(), !!sig);
            if (line) {
                const btn = el('button', 'btn-secondary text-xs', `Go to line ${line}`);
                btn.type = 'button';
                btn.addEventListener('click', () => onGoto(line));
                wrap.append(btn);
            }
        }
        detail.append(wrap);
    }

    function labelled(label, text, copy = false) {
        const box = el('div');
        const head = el('div', 'mb-1 flex items-center justify-between');
        head.append(el('p', 'text-xs text-slate-600 dark:text-slate-500', label));
        if (copy && text) {
            const b = el('button', 'text-xs text-slate-500 hover:text-slate-900 dark:hover:text-slate-100', 'Copy');
            b.type = 'button';
            b.addEventListener('click', async () => {
                try { await navigator.clipboard.writeText(text); b.textContent = 'Copied'; setTimeout(() => (b.textContent = 'Copy'), 1200); } catch { /* ignore */ }
            });
            head.append(b);
        }
        box.append(head, el('pre', 'problem-pre', text === '' ? '(empty)' : text));
        return box;
    }

    return {
        setCases(list) {
            cases.clear();
            chips.replaceChildren();
            selected = list[0]?.number ?? null;
            list.forEach((info, idx) => {
                const chip = el('button');
                chip.type = 'button';
                chip.setAttribute('role', 'tab');
                chip.addEventListener('click', () => this.select(info.number));
                cases.set(info.number, { info, row: null, running: false, chip, name: nameOf(info, idx) });
                chips.append(chip);
                paintChip(info.number);
            });
            this.select(selected);
        },
        markRunning() {
            cases.forEach((e, n) => { e.running = true; e.row = null; paintChip(n); });
            renderDetail();
        },
        setRow(number, row) {
            const entry = cases.get(number);
            if (!entry) return;
            entry.row = row;
            entry.running = false;
            paintChip(number);
            if (selected === number) renderDetail();
        },
        stopRunning() {
            cases.forEach((e, n) => { if (e.running) { e.running = false; paintChip(n); } });
        },
        /** Jump to the first failing visible case, if any. */
        focusFirstFailure() {
            for (const [n, e] of cases) {
                if (e.row && !e.row.passed && !e.info.hidden) return this.select(n);
            }
            for (const [n, e] of cases) {
                if (e.row && !e.row.passed) return this.select(n);
            }
        },
        select(number) {
            selected = number;
            cases.forEach((e) => e.chip.setAttribute('aria-selected', String(e.info.number === number)));
            renderDetail();
        },
    };
}

/* ───────── Editor ───────── */
const langSelect = $('[data-language]');
const savedEl = $('[data-saved]');
let editor = null;
let language = null;

if (hasRuntimes) {
    const stored = store.get(LAST_LANG_KEY);
    const has = (k) => cfg.languages.some((l) => l.key === k);
    language = has(stored) ? stored : has(cfg.defaultLanguage) ? cfg.defaultLanguage : cfg.languages[0].key;
    langSelect.value = language;

    const loadDoc = (lang) => store.get(codeKey(lang)) ?? starterOf(lang);
    const markSaved = debounce(() => { savedEl.textContent = '● Saved'; }, 400);

    editor = createEditor({
        parent: $('[data-editor]'),
        language,
        doc: loadDoc(language),
        keys: [
            { key: 'Mod-Enter', run: () => submit() },
            { key: "Mod-'", run: () => run() },
        ],
        onChange: (text) => {
            store.set(codeKey(language), text);
            savedEl.textContent = 'Saving…';
            markSaved();
            editor.clearErrorLine();
        },
    });

    langSelect.addEventListener('change', () => {
        language = langSelect.value;
        store.set(LAST_LANG_KEY, language);
        editor.setLanguage(language);
        editor.setDoc(loadDoc(language));
    });

    $('[data-reset]')?.addEventListener('click', () => {
        editor.setDoc(starterOf(language));
        toast('Code reset to the starter template.', { label: 'Undo', run: () => editor.undo() });
    });
}

const gotoLine = (n) => { setMobileTab('code'); editor?.highlightLine(n); editor?.focus(); };

/* ───────── Boards ───────── */
const casesBoard = createBoard($('[data-cases-board]'), { naming: 'example', getLanguage: () => language, onGoto: gotoLine });
casesBoard.setCases(cfg.examples.map((e, i) => ({ number: e.number, hidden: false, label: e.label, input: e.input, expected: e.expected, idx: i })));
const resultBoard = createBoard($('[data-result-board]'), { naming: 'test', getLanguage: () => language, onGoto: gotoLine });

/* ───────── Run / Submit ───────── */
const runBtn = $('[data-run]');
const submitBtn = $('[data-submit]');
const resultSummary = $('[data-result-summary]');
const customOut = $('[data-custom-output]');
let controller = null;

function syncRunLabel() {
    if (!runBtn || controller) return;
    runBtn.querySelector('[data-run-label]').textContent = activeConsole === 'custom' ? 'Run custom' : 'Run examples';
}

function setBusy(kind) {
    const busy = kind !== null;
    runBtn.disabled = busy;
    langSelect.disabled = busy;
    if (kind === 'submit') {
        submitBtn.querySelector('[data-submit-label]').textContent = 'Cancel';
        submitBtn.dataset.cancel = '1';
    } else {
        submitBtn.querySelector('[data-submit-label]').textContent = 'Submit';
        delete submitBtn.dataset.cancel;
    }
    submitBtn.disabled = kind === 'run';
}

/**
 * Go builds once before the tests: show "Compiling…" while it runs and the
 * compiler's verdict (friendly message + raw output + "Go to line") after.
 */
function showCompile(where, ev) {
    const box = $(`[data-compile-out="${where}"]`);
    if (!box) return;
    if (ev === 'pending') {
        box.replaceChildren(el('p', 'mb-3 text-xs text-slate-600 dark:text-slate-400', 'Compiling…'));
        return;
    }
    if (!ev || ev.ok) { box.replaceChildren(); return; }
    const wrap = el('div', 'mb-4 space-y-2 rounded-lg border border-rose-300/60 p-3 dark:border-rose-900/50');
    wrap.append(el('p', 'text-sm font-semibold text-rose-600 dark:text-rose-400', 'Compile error — no tests were run.'));
    if (ev.message) wrap.append(el('p', 'rounded-lg bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-300', `💡 ${ev.message}`));
    if (ev.output) wrap.append(el('pre', 'problem-pre', ev.output));
    const line = errorLineFromStderr(ev.output, 'go', true);
    if (line) {
        const b = el('button', 'btn-secondary text-xs', `Go to line ${line}`);
        b.type = 'button';
        b.addEventListener('click', () => gotoLine(line));
        wrap.append(b);
    }
    box.replaceChildren(wrap);
}

function showMessage(target, text, tone = 'text-rose-600 dark:text-rose-400') {
    target.replaceChildren(el('p', `text-sm ${tone}`, text));
}

async function run() {
    if (!hasRuntimes || controller) return;
    const code = editor.getDoc();
    controller = new AbortController();
    setBusy('run');
    if (isMobile()) setMobileTab('result');
    editor.clearErrorLine();

    try {
        if (activeConsole === 'custom') {
            customOut.replaceChildren(el('p', 'text-sm text-slate-600 dark:text-slate-400', 'Running…'));
            const stdin = sig ? customArgs() : $('[data-custom-stdin]').value;
            if (stdin === null) return;
            const r = await postJson(cfg.urls.run, { language, code, stdin }, controller.signal);
            renderCustom(r);
        } else {
            selectConsole('cases');
            casesBoard.markRunning();
            showCompile('cases', language === 'go' && sig ? 'pending' : null);
            let failures = 0;
            let compileFailed = false;
            await postNdjson(cfg.urls.runExamples, { language, code }, (ev) => {
                if (ev.type === 'compile') { showCompile('cases', ev); compileFailed = !ev.ok; }
                if (ev.type === 'case') { casesBoard.setRow(ev.number, ev); if (!ev.passed) failures++; }
                if (ev.type === 'error') toast(ev.message);
            }, controller.signal);
            if (compileFailed) { casesBoard.stopRunning(); if (isMobile()) setMobileTab('result'); }
            else if (failures) casesBoard.focusFirstFailure();
            else toast('All examples passed — submit to run every test.');
        }
    } catch (err) {
        if (err.name !== 'AbortError') {
            if (activeConsole === 'custom') showMessage(customOut, err.message);
            else toast(err.message);
        }
    } finally {
        casesBoard.stopRunning();
        controller = null;
        setBusy(null);
        syncRunLabel();
    }
}

/* Function problems: one field per parameter, prefilled from the first example. */
const customArgsWrap = $('[data-custom-args]');
if (sig && customArgsWrap) {
    const first = splitArgs(cfg.examples[0]?.input ?? '', sig.params.length);
    if (!sig.params.length) customArgsWrap.append(el('p', 'text-xs text-slate-600 dark:text-slate-400', 'This function takes no arguments.'));
    sig.params.forEach((p, i) => {
        const box = el('div');
        const label = el('label', 'mb-1 flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300');
        label.htmlFor = `custom-arg-${i}`;
        label.append(el('span', 'font-mono font-semibold', p.name), el('span', 'problem-type', p.type), el('span', 'ml-auto text-rose-600', ''));
        const ta = el('textarea', 'input-field resize-y font-mono text-sm');
        ta.id = `custom-arg-${i}`;
        ta.rows = 1;
        ta.spellcheck = false;
        ta.dataset.customArg = String(i);
        ta.value = first[i] ?? '';
        box.append(label, ta);
        customArgsWrap.append(box);
    });
}

/** Serialise the custom argument fields; null (with errors shown) when one is invalid. */
function customArgs() {
    let ok = true;
    const lines = sig.params.map((p, i) => {
        const ta = $(`[data-custom-arg="${i}"]`);
        const value = normalizeValue(ta.value, p.type);
        const err = checkValue(value, p.type);
        ta.classList.toggle('is-invalid', !!err);
        ta.previousElementSibling.lastChild.textContent = err ?? '';
        if (err) ok = false;
        return minifyJson(value);
    });
    if (!ok) { customOut.replaceChildren(el('p', 'text-sm text-rose-600', 'Fix the highlighted arguments first.')); return null; }
    return lines.join('\n');
}

function renderCustom(r) {
    const out = el('div', 'space-y-3');
    if (r.timed_out) out.append(el('p', 'text-sm font-semibold text-amber-600', 'Time limit exceeded'));
    const block = (label, text) => {
        const w = el('div');
        w.append(el('p', 'mb-1 text-xs text-slate-600 dark:text-slate-500', label), el('pre', 'problem-pre', text === '' ? '(empty)' : text));
        return w;
    };
    if (sig) {
        if (r.returned != null) out.append(block('Returned', r.returned));
        if (r.prints) out.append(block('Stdout (your prints)', r.prints));
    } else {
        out.append(block('stdout', r.stdout));
    }
    if (r.stderr) {
        out.append(block('stderr', r.stderr));
        const line = errorLineFromStderr(r.stderr, language, !!sig);
        if (line) {
            const b = el('button', 'btn-secondary text-xs', `Go to line ${line}`);
            b.type = 'button';
            b.addEventListener('click', () => gotoLine(line));
            out.append(b);
        }
    }
    if (r.stdout_truncated) out.append(el('p', 'text-xs text-amber-600', 'Output was truncated at 256 KB.'));
    out.append(el('p', 'text-xs text-slate-600 dark:text-slate-500', `exit ${r.exit_code ?? '—'} · ${r.duration_ms} ms`));
    customOut.replaceChildren(out);
}

async function submit() {
    if (!hasRuntimes) return;
    if (controller) {
        if (submitBtn.dataset.cancel) controller.abort(); // second press while judging = cancel
        return;
    }
    controller = new AbortController();
    setBusy('submit');
    selectConsole('result');
    if (isMobile()) setMobileTab('result');
    editor.clearErrorLine();

    let total = 0;
    let finished = 0;
    let verdict = null;
    resultBoard.setCases([]);
    showCompile('result', null);
    resultSummary.replaceChildren(el('p', 'text-sm text-slate-600 dark:text-slate-400', 'Starting…'));
    resultBoard.markRunning();

    const bar = el('span');
    const progressText = el('p', 'text-sm font-semibold text-slate-900 dark:text-slate-100');
    const paintProgress = () => {
        progressText.textContent = `Judging… ${finished}/${total}`;
        bar.style.width = total ? `${(finished / total) * 100}%` : '0';
    };

    try {
        await postNdjson(cfg.urls.submit, { language, code: editor.getDoc() }, (ev) => {
            if (ev.type === 'start') {
                total = ev.total;
                resultBoard.setCases(ev.cases);
                resultBoard.markRunning();
                const track = el('div', 'problem-progress');
                track.append(bar);
                resultSummary.replaceChildren(progressText, track);
                paintProgress();
                if (language === 'go' && sig) progressText.textContent = 'Compiling…';
            } else if (ev.type === 'compile') {
                showCompile('result', ev);
                paintProgress();
            } else if (ev.type === 'case') {
                finished++;
                resultBoard.setRow(ev.number, ev);
                paintProgress();
            } else if (ev.type === 'done') {
                verdict = ev;
            } else if (ev.type === 'error') {
                showMessage(resultSummary, ev.message);
            }
        }, controller.signal);
    } catch (err) {
        if (err.name === 'AbortError') showMessage(resultSummary, 'Cancelled — nothing was recorded.', 'text-slate-600 dark:text-slate-400');
        else showMessage(resultSummary, err.message);
    } finally {
        resultBoard.stopRunning();
        controller = null;
        setBusy(null);
        syncRunLabel();
    }

    if (verdict) onVerdict(verdict);
}

function onVerdict(v) {
    const ok = v.status === 'accepted';
    const box = el('div', 'space-y-1');
    box.append(el('p', `text-lg font-bold ${STATUS_TEXT[v.status] ?? ''}`, `${STATUS_LABELS[v.status] ?? v.status} · ${v.passed}/${v.total} passed`));
    box.append(el('p', 'text-xs text-slate-600 dark:text-slate-400',
        `${v.duration_ms} ms total · ${language} · attempt ${v.submission.attempt}${v.first_solve ? ' · first solve 🎉' : ''}`));
    if (ok && cfg.nextUrl) {
        const a = el('a', 'btn-primary mt-2 inline-flex', `Next unsolved: ${cfg.nextTitle} →`);
        a.href = cfg.nextUrl;
        box.append(a);
    }
    const bar = el('div', 'problem-progress mt-2');
    const fill = el('span');
    fill.style.width = '100%';
    fill.style.background = ok ? '#10b981' : '#f43f5e';
    bar.append(fill);
    resultSummary.replaceChildren(box, bar);

    if (!ok) resultBoard.focusFirstFailure();
    addSubmission(v.submission);
    if (ok) markSolved();
    if (v.first_solve) celebrate();
}

function markSolved() {
    $$('[data-solved-badge]').forEach((b) => b.classList.remove('hidden'));
    solutions.unlock();
}

async function celebrate() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const { default: confetti } = await import('canvas-confetti');
    confetti({ particleCount: 120, spread: 70, origin: { y: 0.7 } });
}

runBtn?.addEventListener('click', run);
submitBtn?.addEventListener('click', submit);
document.addEventListener('keydown', (e) => {
    if (e.defaultPrevented || !(e.metaKey || e.ctrlKey)) return;
    if (e.key === 'Enter') { e.preventDefault(); submit(); }
    else if (e.key === "'") { e.preventDefault(); run(); }
});
syncRunLabel();

/* ───────── Submissions tab ───────── */
const subList = $('[data-submissions-list]');
const subEmpty = $('[data-submissions-empty]');
const subViewer = $('[data-submission-viewer]');
const subCount = $('[data-submissions-count]');
let subTotal = cfg.attempts;

function submissionRow(s) {
    const li = el('li');
    const btn = el('button', 'flex w-full items-center justify-between gap-3 py-2.5 text-left text-xs hover:bg-slate-500/5');
    btn.type = 'button';
    const ok = s.status === 'accepted';
    btn.append(
        el('span', `font-semibold ${STATUS_TEXT[s.status] ?? ''}`, `${ok ? '✓' : '✗'} ${STATUS_LABELS[s.status] ?? s.status} · ${s.passed_count}/${s.total_count}`),
        el('span', 'text-slate-600 dark:text-slate-500', `${s.language} · ${s.duration_ms} ms · ${relativeTime(s.created_at)}`),
    );
    btn.addEventListener('click', () => openSubmission(s.id));
    li.append(btn);
    return li;
}

function addSubmission(s) {
    subEmpty.classList.add('hidden');
    subList.prepend(submissionRow(s));
    subTotal++;
    subCount.textContent = String(subTotal);
    $$('[data-attempts]').forEach((n) => (n.textContent = `${subTotal} ${subTotal === 1 ? 'attempt' : 'attempts'}`));
}

cfg.submissions.forEach((s) => subList.append(submissionRow(s)));
subEmpty.classList.toggle('hidden', cfg.submissions.length > 0);

async function openSubmission(id) {
    try {
        const res = await fetch(cfg.urls.submission.replace(':id', id), { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('Could not load that submission.');
        const s = await res.json();
        const box = el('div', 'space-y-3');
        const head = el('div', 'flex items-center justify-between gap-2');
        const back = el('button', 'text-xs font-semibold text-slate-600 hover:underline dark:text-slate-400', '← All submissions');
        back.type = 'button';
        back.addEventListener('click', () => { subViewer.classList.add('hidden'); subList.parentElement.classList.remove('hidden'); });
        head.append(back, el('span', `text-xs font-semibold ${STATUS_TEXT[s.status] ?? ''}`, `${STATUS_LABELS[s.status] ?? s.status} · ${s.passed}/${s.total} · ${s.language}`));
        const notes = [];
        if (s.outdated) notes.push(el('p', 'text-xs text-amber-700 dark:text-amber-300', 'Judged against an older version of this problem.'));
        const load = el('button', 'btn-secondary text-xs', 'Load into editor');
        load.type = 'button';
        load.disabled = !editor || !cfg.languages.some((l) => l.key === s.language);
        load.addEventListener('click', () => {
            if (langSelect.value !== s.language) {
                langSelect.value = s.language;
                langSelect.dispatchEvent(new Event('change'));
            }
            editor.setDoc(s.code);
            setMobileTab('code');
            toast(`Loaded submission #${s.id}.`, { label: 'Undo', run: () => editor.undo() });
        });
        const actions = el('div', 'flex flex-wrap items-center gap-2');
        actions.append(load);
        if (s.status === 'accepted') {
            // Accepted code is shown to people who solved the problem, unless hidden.
            let hidden = s.hidden;
            const toggle = el('button', 'btn-ghost text-xs');
            toggle.type = 'button';
            const paint = () => {
                toggle.textContent = hidden ? 'Show in Solutions' : 'Hide from Solutions';
                toggle.title = hidden ? 'Only you can see this submission.' : 'People who solved this problem can see this code.';
            };
            paint();
            toggle.addEventListener('click', async () => {
                const r = await requestJson('POST', cfg.urls.hideSubmission.replace(':id', s.id), { hidden: !hidden });
                toast(r.data.message);
                if (r.ok) { hidden = r.data.hidden; paint(); }
            });
            actions.append(toggle);
        }
        box.append(head, ...notes, el('pre', 'problem-pre max-h-[22rem]', s.code), actions);
        subViewer.replaceChildren(box);
        subViewer.classList.remove('hidden');
        subList.parentElement.classList.add('hidden');
    } catch (err) {
        toast(err.message);
    }
}

/* ───────── Hints + solution ───────── */
(function initHints() {
    const list = $('[data-hints-list]');
    const nextBtn = $('[data-hint-next]');
    if (!list || !nextBtn) return;
    const key = `${cfg.storageKey}-hints`;
    let shown = Math.min(Number(store.get(key) ?? 0), cfg.hints.length);
    const paint = () => {
        list.replaceChildren(...cfg.hints.slice(0, shown).map((h, i) => {
            const card = el('div', 'rounded-lg border border-slate-200/95 p-3 text-sm dark:border-slate-800/90');
            card.append(el('p', 'mb-1 text-xs font-semibold text-slate-600 dark:text-slate-400', `Hint ${i + 1}`), el('p', 'whitespace-pre-wrap', h));
            return card;
        }));
        nextBtn.classList.toggle('hidden', shown >= cfg.hints.length);
        nextBtn.textContent = `Show hint ${shown + 1} of ${cfg.hints.length}`;
    };
    nextBtn.addEventListener('click', () => { shown++; store.set(key, String(shown)); paint(); });
    paint();
})();

/* ───────── Sharing + community solutions ───────── */
initShare(root, cfg);
initModeration(root, cfg);

/**
 * Put someone else's solution into the editor. Your current draft for that
 * language is kept in a backup key and the change is one undo away.
 */
function openInEditor(lang, code, { outdated, label }) {
    if (!editor || !cfg.languages.some((l) => l.key === lang)) return;
    if (outdated && !confirm('This was written for an older version of the problem and may not fit the current tests or signature. Open it anyway?')) return;
    const current = store.get(codeKey(lang));
    if (current && current.trim() !== '' && current !== starterOf(lang) && current !== code) {
        if (!confirm('Replace your current code for this language? It is kept as a backup and you can undo.')) return;
        store.set(`${codeKey(lang)}-backup`, current);
    }
    if (langSelect.value !== lang) {
        langSelect.value = lang;
        langSelect.dispatchEvent(new Event('change'));
    }
    editor.setDoc(code);
    setMobileTab('code');
    toast(`Opened ${label}'s solution.`, { label: 'Undo', run: () => editor.undo() });
}

const solutions = initSolutions(root, cfg, {
    openInEditor,
    canOpen: (lang) => !!editor && cfg.languages.some((l) => l.key === lang),
});
