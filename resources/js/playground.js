/**
 * Playground page bootstrap.
 *
 * - CodeMirror 6 editor with language-specific parsers, line numbers, bracket
 *   matching, autocomplete, search, history, and Tab indent.
 * - Theme follows the global `html.dark` class (one-dark when dark, default light
 *   syntax theme when light).
 * - Per-language code is auto-saved to localStorage under
 *   `home-teacher-playground-code-{lang}`.
 * - Last selected language is saved under `home-teacher-playground-last-lang`.
 * - Cmd/Ctrl+Enter triggers a run; result is posted to `data-run-url` on the
 *   `#playground` container as JSON.
 */
import { EditorState, Compartment } from '@codemirror/state';
import {
    EditorView,
    keymap,
    lineNumbers,
    highlightActiveLine,
    highlightActiveLineGutter,
    highlightSpecialChars,
    drawSelection,
    rectangularSelection,
    crosshairCursor,
    dropCursor,
} from '@codemirror/view';
import {
    defaultHighlightStyle,
    syntaxHighlighting,
    indentOnInput,
    bracketMatching,
    foldGutter,
    foldKeymap,
    indentUnit,
} from '@codemirror/language';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { searchKeymap, highlightSelectionMatches } from '@codemirror/search';
import { autocompletion, completionKeymap, closeBrackets, closeBracketsKeymap } from '@codemirror/autocomplete';
import { javascript } from '@codemirror/lang-javascript';
import { python } from '@codemirror/lang-python';
import { php } from '@codemirror/lang-php';
import { go } from '@codemirror/lang-go';
import { oneDark } from '@codemirror/theme-one-dark';

const root = document.getElementById('playground');
if (root) {
    bootstrap(root);
}

function bootstrap(root) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const runUrl = root.dataset.runUrl;

    const tabs = Array.from(root.querySelectorAll('[data-lang-tab]'));
    if (tabs.length === 0) return;

    const editorMount = root.querySelector('[data-editor]');
    const stdinEl = root.querySelector('[data-stdin]');
    const runBtn = root.querySelector('[data-run]');
    const runLabel = root.querySelector('[data-run-label]');
    const resetBtn = root.querySelector('[data-reset]');
    const clearBtn = root.querySelector('[data-clear]');
    const copyBtn = root.querySelector('[data-copy]');
    const wrapBtn = root.querySelector('[data-wrap]');

    const stdoutEl = root.querySelector('[data-stdout]');
    const stderrEl = root.querySelector('[data-stderr]');
    const consoleEmpty = root.querySelector('[data-console-empty]');
    const consoleTabs = Array.from(root.querySelectorAll('[data-console-tab]'));
    const consolePanels = {
        stdout: root.querySelector('[data-console-panel="stdout"]'),
        stderr: root.querySelector('[data-console-panel="stderr"]'),
        info: root.querySelector('[data-console-panel="info"]'),
    };

    const statusLang = root.querySelector('[data-status-lang]');
    const statusPos = root.querySelector('[data-status-pos]');
    const statusMeta = root.querySelector('[data-status-meta]');
    const statusRun = root.querySelector('[data-status-run]');
    const exitBadge = root.querySelector('[data-info-exit]');
    const durationBadge = root.querySelector('[data-info-duration]');
    const timedOutBadge = root.querySelector('[data-info-timed-out]');
    const truncatedBadge = root.querySelector('[data-info-truncated]');
    const binaryBadge = root.querySelector('[data-info-binary]');

    const samples = Object.fromEntries(
        tabs.map((t) => [t.dataset.langTab, decodeURIComponent(t.dataset.sample ?? '')]),
    );
    const labels = Object.fromEntries(tabs.map((t) => [t.dataset.langTab, t.dataset.label ?? t.dataset.langTab]));
    const versions = Object.fromEntries(tabs.map((t) => [t.dataset.langTab, t.dataset.version ?? '']));
    const binaries = Object.fromEntries(tabs.map((t) => [t.dataset.langTab, t.dataset.binary ?? '']));
    const storageKey = (lang) => `home-teacher-playground-code-${lang}`;
    const lastLangKey = 'home-teacher-playground-last-lang';

    const languageExtensions = {
        javascript: () => javascript(),
        python: () => python(),
        php: () => php({ plain: false }),
        go: () => go(),
    };

    const languageCompartment = new Compartment();
    const themeCompartment = new Compartment();
    const wrapCompartment = new Compartment();

    let currentLang = resolveInitialLang();
    let wrapEnabled = false;

    const editor = new EditorView({
        parent: editorMount,
        state: EditorState.create({
            doc: loadDocFor(currentLang),
            extensions: buildExtensions(currentLang),
        }),
    });

    /** @type {(lang: string) => any[]} */
    function buildExtensions(lang) {
        return [
            lineNumbers(),
            highlightActiveLineGutter(),
            highlightSpecialChars(),
            history(),
            foldGutter(),
            drawSelection(),
            dropCursor(),
            EditorState.allowMultipleSelections.of(true),
            indentOnInput(),
            indentUnit.of('    '),
            syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
            bracketMatching(),
            closeBrackets(),
            autocompletion(),
            rectangularSelection(),
            crosshairCursor(),
            highlightActiveLine(),
            highlightSelectionMatches(),
            keymap.of([
                {
                    key: 'Mod-Enter',
                    preventDefault: true,
                    run: () => {
                        run();
                        return true;
                    },
                },
                {
                    key: 'Mod-s',
                    preventDefault: true,
                    run: () => true,
                },
                indentWithTab,
                ...closeBracketsKeymap,
                ...defaultKeymap,
                ...searchKeymap,
                ...historyKeymap,
                ...foldKeymap,
                ...completionKeymap,
            ]),
            languageCompartment.of(languageExtensions[lang]?.() ?? []),
            themeCompartment.of(currentThemeExtension()),
            wrapCompartment.of(wrapEnabled ? EditorView.lineWrapping : []),
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    saveDocFor(currentLang, update.state.doc.toString());
                }
                if (update.selectionSet || update.docChanged) {
                    updateStatus();
                }
            }),
            EditorView.theme({
                '&': { height: '100%' },
                '.cm-scroller': {
                    fontFamily: 'ui-monospace, SFMono-Regular, "JetBrains Mono", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
                    fontSize: '13px',
                    lineHeight: '1.55',
                },
                '.cm-gutters': {
                    backgroundColor: 'transparent',
                    borderRight: '1px solid color-mix(in oklab, currentColor 15%, transparent)',
                },
            }),
        ];
    }

    function currentThemeExtension() {
        return document.documentElement.classList.contains('dark') ? oneDark : [];
    }

    function currentSelectedTab() {
        return tabs.find((t) => t.getAttribute('aria-selected') === 'true')?.dataset.langTab ?? null;
    }

    function resolveInitialLang() {
        const available = tabs.map((t) => t.dataset.langTab);
        const saved = readStorage(lastLangKey);
        if (saved && available.includes(saved) && languageExtensions[saved]) {
            return saved;
        }
        return currentSelectedTab() ?? tabs[0].dataset.langTab;
    }

    function setTabSelected(lang) {
        tabs.forEach((t) => t.setAttribute('aria-selected', t.dataset.langTab === lang ? 'true' : 'false'));
    }

    function loadDocFor(lang) {
        const saved = readStorage(storageKey(lang));
        return saved ?? samples[lang] ?? '';
    }

    function saveDocFor(lang, value) {
        writeStorage(storageKey(lang), value);
    }

    function readStorage(key) {
        try { return localStorage.getItem(key); } catch (_e) { return null; }
    }

    function writeStorage(key, value) {
        try { localStorage.setItem(key, value); } catch (_e) { /* full / disabled */ }
    }

    function selectLang(lang) {
        if (!languageExtensions[lang]) return;
        setTabSelected(lang);
        currentLang = lang;
        writeStorage(lastLangKey, lang);

        editor.dispatch({
            changes: { from: 0, to: editor.state.doc.length, insert: loadDocFor(lang) },
            effects: languageCompartment.reconfigure(languageExtensions[lang]()),
        });

        updateStatus();
        editor.focus();
    }

    function updateStatus() {
        if (statusLang) {
            statusLang.textContent = labels[currentLang] ?? currentLang;
        }
        if (binaryBadge) {
            const v = versions[currentLang];
            const b = binaries[currentLang];
            binaryBadge.textContent = v || b || '';
            binaryBadge.title = b || '';
            binaryBadge.classList.toggle('hidden', !v && !b);
        }
        if (statusMeta) {
            const doc = editor.state.doc;
            const lines = doc.lines;
            const chars = doc.length;
            statusMeta.textContent = `${lines} line${lines === 1 ? '' : 's'} · ${chars} char${chars === 1 ? '' : 's'}`;
        }
        if (statusPos) {
            const head = editor.state.selection.main.head;
            const line = editor.state.doc.lineAt(head);
            const col = head - line.from + 1;
            statusPos.textContent = `Ln ${line.number}, Col ${col}`;
        }
    }

    function setActiveConsole(panel) {
        consoleTabs.forEach((t) => {
            const on = t.dataset.consoleTab === panel;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        Object.entries(consolePanels).forEach(([key, el]) => {
            if (!el) return;
            el.classList.toggle('hidden', key !== panel);
        });
    }

    function resetConsole(showHint) {
        stdoutEl.textContent = '';
        stderrEl.textContent = '';
        if (consoleEmpty) {
            consoleEmpty.classList.toggle('hidden', !showHint);
            consoleEmpty.textContent = showHint ? 'Run your code to see output here.' : '';
        }
        exitBadge?.classList.add('hidden');
        durationBadge?.classList.add('hidden');
        timedOutBadge?.classList.add('hidden');
        truncatedBadge?.classList.add('hidden');
        if (statusRun) statusRun.textContent = '';
        setActiveConsole('stdout');
        markStderrTab(false);
    }

    function markStderrTab(hasStderr) {
        const tab = consoleTabs.find((t) => t.dataset.consoleTab === 'stderr');
        if (!tab) return;
        tab.classList.toggle('text-rose-600', hasStderr);
        tab.classList.toggle('dark:text-rose-300', hasStderr);
        const dot = tab.querySelector('[data-stderr-dot]');
        if (dot) dot.classList.toggle('hidden', !hasStderr);
    }

    async function run() {
        if (runBtn?.disabled) return;
        if (!runUrl || !currentLang) return;

        resetConsole(false);
        if (runBtn) runBtn.disabled = true;
        if (runLabel) runLabel.textContent = 'Running…';
        if (statusRun) statusRun.textContent = `Running ${labels[currentLang] ?? currentLang}…`;

        try {
            const res = await fetch(runUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    language: currentLang,
                    code: editor.state.doc.toString(),
                    stdin: stdinEl?.value || null,
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                stderrEl.textContent = data.message || `Request failed (${res.status})`;
                markStderrTab(true);
                setActiveConsole('stderr');
                if (statusRun) statusRun.textContent = data.error || 'failed';
                return;
            }

            stdoutEl.textContent = data.stdout || '';
            stderrEl.textContent = data.stderr || '';
            const hasStdout = Boolean(data.stdout);
            const hasStderr = Boolean(data.stderr);
            markStderrTab(hasStderr);

            if (!hasStdout && !hasStderr) {
                if (consoleEmpty) {
                    consoleEmpty.classList.remove('hidden');
                    consoleEmpty.textContent = '(no output)';
                }
            }

            if (data.exit_code !== undefined && data.exit_code !== null && exitBadge) {
                exitBadge.textContent = `exit ${data.exit_code}`;
                exitBadge.classList.remove('hidden');
                exitBadge.classList.toggle('bg-rose-100', data.exit_code !== 0);
                exitBadge.classList.toggle('text-rose-700', data.exit_code !== 0);
                exitBadge.classList.toggle('dark:bg-rose-950/55', data.exit_code !== 0);
                exitBadge.classList.toggle('dark:text-rose-300', data.exit_code !== 0);
                exitBadge.classList.toggle('bg-emerald-100', data.exit_code === 0);
                exitBadge.classList.toggle('text-emerald-700', data.exit_code === 0);
                exitBadge.classList.toggle('dark:bg-emerald-950/55', data.exit_code === 0);
                exitBadge.classList.toggle('dark:text-emerald-300', data.exit_code === 0);
            }
            if (typeof data.duration_ms === 'number' && durationBadge) {
                durationBadge.textContent = `${data.duration_ms} ms`;
                durationBadge.classList.remove('hidden');
            }
            if (data.timed_out) timedOutBadge?.classList.remove('hidden');
            if (data.stdout_truncated || data.stderr_truncated) truncatedBadge?.classList.remove('hidden');
            if (statusRun) {
                statusRun.textContent = data.timed_out
                    ? 'timed out'
                    : data.exit_code === 0
                      ? 'ok'
                      : `exit ${data.exit_code ?? '?'}`;
            }

            // If stderr is the main signal, switch to it; otherwise show stdout.
            if (!hasStdout && hasStderr) {
                setActiveConsole('stderr');
            } else {
                setActiveConsole('stdout');
            }
        } catch (err) {
            stderrEl.textContent = `Network error: ${err?.message || err}`;
            markStderrTab(true);
            setActiveConsole('stderr');
            if (statusRun) statusRun.textContent = 'network error';
        } finally {
            if (runBtn) runBtn.disabled = false;
            if (runLabel) runLabel.textContent = 'Run';
        }
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => selectLang(tab.dataset.langTab));
    });

    runBtn?.addEventListener('click', run);
    resetBtn?.addEventListener('click', () => {
        editor.dispatch({
            changes: { from: 0, to: editor.state.doc.length, insert: samples[currentLang] ?? '' },
        });
        editor.focus();
    });
    clearBtn?.addEventListener('click', () => resetConsole(true));
    copyBtn?.addEventListener('click', async () => {
        const text = editor.state.doc.toString();
        try {
            await navigator.clipboard.writeText(text);
            const old = copyBtn.getAttribute('data-label');
            copyBtn.setAttribute('data-label', 'Copied');
            copyBtn.querySelector('[data-copy-label]').textContent = 'Copied';
            setTimeout(() => {
                copyBtn.querySelector('[data-copy-label]').textContent = old ?? 'Copy';
                copyBtn.setAttribute('data-label', old ?? 'Copy');
            }, 1200);
        } catch (_e) {
            /* clipboard blocked — ignore */
        }
    });
    wrapBtn?.addEventListener('click', () => {
        wrapEnabled = !wrapEnabled;
        wrapBtn.setAttribute('aria-pressed', wrapEnabled ? 'true' : 'false');
        editor.dispatch({
            effects: wrapCompartment.reconfigure(wrapEnabled ? EditorView.lineWrapping : []),
        });
    });

    consoleTabs.forEach((tab) => {
        tab.addEventListener('click', () => setActiveConsole(tab.dataset.consoleTab));
    });

    // React to theme toggles dispatched by the global theme switcher.
    const themeObserver = new MutationObserver(() => {
        editor.dispatch({ effects: themeCompartment.reconfigure(currentThemeExtension()) });
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    setTabSelected(currentLang);
    setActiveConsole('stdout');
    updateStatus();
}
