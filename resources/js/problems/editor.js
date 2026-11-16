import { Compartment, EditorState, StateEffect, StateField } from '@codemirror/state';
import {
    Decoration, EditorView, crosshairCursor, drawSelection, dropCursor, highlightActiveLine,
    highlightActiveLineGutter, highlightSpecialChars, keymap, lineNumbers, rectangularSelection,
} from '@codemirror/view';
import {
    bracketMatching, defaultHighlightStyle, foldGutter, foldKeymap, indentOnInput, indentUnit, syntaxHighlighting,
} from '@codemirror/language';
import { defaultKeymap, history, historyKeymap, indentWithTab, undo } from '@codemirror/commands';
import { highlightSelectionMatches, searchKeymap } from '@codemirror/search';
import { autocompletion, closeBrackets, closeBracketsKeymap, completionKeymap } from '@codemirror/autocomplete';
import { javascript } from '@codemirror/lang-javascript';
import { python } from '@codemirror/lang-python';
import { php } from '@codemirror/lang-php';
import { go } from '@codemirror/lang-go';
import { oneDark } from '@codemirror/theme-one-dark';

const LANGUAGES = {
    javascript: () => javascript(),
    python: () => python(),
    php: () => php({ plain: false }),
    go: () => go(),
};

const setErrorLine = StateEffect.define();
const errorLineField = StateField.define({
    create: () => Decoration.none,
    update(deco, tr) {
        for (const e of tr.effects) {
            if (e.is(setErrorLine)) {
                if (e.value == null || e.value < 1 || e.value > tr.state.doc.lines) return Decoration.none;
                const line = tr.state.doc.line(e.value);
                return Decoration.set([Decoration.line({ class: 'cm-error-line' }).range(line.from)]);
            }
        }
        return tr.docChanged ? Decoration.none : deco;
    },
    provide: (f) => EditorView.decorations.from(f),
});

const isDark = () => document.documentElement.classList.contains('dark');

/**
 * CodeMirror 6 editor shared by the solve page and the reference-solution
 * field. `keys` is a list of `{ key, run }` bindings added ahead of defaults.
 */
export function createEditor({ parent, language, doc = '', readOnly = false, keys = [], onChange = null }) {
    const lang = new Compartment();
    const theme = new Compartment();

    const view = new EditorView({
        parent,
        state: EditorState.create({
            doc,
            extensions: [
                lineNumbers(),
                highlightActiveLineGutter(),
                highlightSpecialChars(),
                history(),
                foldGutter(),
                drawSelection(),
                dropCursor(),
                EditorState.allowMultipleSelections.of(true),
                EditorState.readOnly.of(readOnly),
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
                errorLineField,
                keymap.of([
                    ...keys.map((k) => ({ key: k.key, preventDefault: true, run: () => { k.run(); return true; } })),
                    { key: 'Mod-s', preventDefault: true, run: () => true },
                    indentWithTab,
                    ...closeBracketsKeymap,
                    ...defaultKeymap,
                    ...searchKeymap,
                    ...historyKeymap,
                    ...foldKeymap,
                    ...completionKeymap,
                ]),
                lang.of(LANGUAGES[language]?.() ?? []),
                theme.of(isDark() ? oneDark : []),
                EditorView.contentAttributes.of({ 'aria-label': 'Code editor' }),
                EditorView.updateListener.of((u) => {
                    if (u.docChanged && onChange) onChange(u.state.doc.toString());
                }),
                EditorView.theme({
                    '&': { height: '100%' },
                    '.cm-scroller': {
                        fontFamily: 'ui-monospace, SFMono-Regular, "JetBrains Mono", Menlo, Monaco, Consolas, "Liberation Mono", monospace',
                        fontSize: '13px',
                        lineHeight: '1.55',
                    },
                    '.cm-gutters': {
                        backgroundColor: 'transparent',
                        borderRight: '1px solid color-mix(in oklab, currentColor 15%, transparent)',
                    },
                }),
            ],
        }),
    });

    // Follow the app's light/dark toggle.
    const observer = new MutationObserver(() => {
        view.dispatch({ effects: theme.reconfigure(isDark() ? oneDark : []) });
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    return {
        view,
        getDoc: () => view.state.doc.toString(),
        /** Replace the whole document as one undoable transaction. */
        setDoc(text) {
            view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: text } });
        },
        setLanguage(name) {
            view.dispatch({ effects: lang.reconfigure(LANGUAGES[name]?.() ?? []) });
        },
        undo: () => undo(view),
        focus: () => view.focus(),
        highlightLine(n) {
            view.dispatch({ effects: setErrorLine.of(n) });
            if (n && n >= 1 && n <= view.state.doc.lines) {
                const line = view.state.doc.line(n);
                view.dispatch({ selection: { anchor: line.from }, effects: EditorView.scrollIntoView(line.from, { y: 'center' }) });
            }
        },
        clearErrorLine() {
            view.dispatch({ effects: setErrorLine.of(null) });
        },
        destroy() {
            observer.disconnect();
            view.destroy();
        },
    };
}
