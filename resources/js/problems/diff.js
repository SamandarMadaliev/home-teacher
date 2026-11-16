import { el } from './util.js';

const visible = (s) => s.replaceAll(' ', '·').replaceAll('\t', '→').replaceAll('\r', '␍');

/**
 * Side-by-side expected/actual output. Lines that differ are highlighted and
 * the first difference is called out. `showWhitespace` renders spaces and tabs
 * as visible glyphs and marks line ends.
 */
export function renderDiff(expected, actual, showWhitespace = false, actualLabel = 'Your output') {
    const exp = expected.split('\n');
    const act = actual.split('\n');
    const total = Math.max(exp.length, act.length);
    let firstDiff = -1;

    const column = (title, lines, other) => {
        const col = el('div', 'min-w-0');
        col.append(el('p', 'mb-1 text-xs text-slate-600 dark:text-slate-500', title));
        const box = el('div', 'problem-diff');
        for (let i = 0; i < total; i++) {
            const line = lines[i];
            const differs = line === undefined || other[i] === undefined || line.trimEnd() !== other[i].trimEnd();
            if (differs && firstDiff < 0) firstDiff = i;
            const row = el('div', `problem-diff-row${differs ? ' is-diff' : ''}`);
            row.append(el('span', 'problem-diff-no', String(i + 1)));
            const text = line === undefined ? '' : (showWhitespace ? `${visible(line)}↵` : line);
            row.append(el('span', 'problem-diff-text', text === '' ? ' ' : text));
            box.append(row);
        }
        col.append(box);
        return col;
    };

    const wrap = el('div', 'space-y-2');
    const grid = el('div', 'grid gap-3 sm:grid-cols-2');
    grid.append(column('Expected', exp, act), column(actualLabel, act, exp));
    wrap.append(grid);
    if (firstDiff >= 0) {
        wrap.append(el('p', 'text-xs text-slate-600 dark:text-slate-400', `First difference on line ${firstDiff + 1}.`));
    }
    return wrap;
}

/** Plain-language nudge for common mistakes. Only call for visible cases. */
export function hintFor(row) {
    if (row.status !== 'wrong_answer') return null;
    const actual = row.actual ?? '';
    const expected = row.expected ?? '';
    if (actual.trim() === '') return 'Your program printed nothing. Did you forget to print the answer?';
    if (actual.trim().toLowerCase() === expected.trim().toLowerCase()) return 'Only upper/lower case differs.';
    const a = actual.trim().split('\n').length;
    const e = expected.trim().split('\n').length;
    if (a > e && actual.includes(expected.trim())) return `Your output has ${a} lines but ${e} ${e === 1 ? 'was' : 'were'} expected. Is a debug print left in?`;
    if (a !== e) return `Your output has ${a} line${a === 1 ? '' : 's'}; ${e} expected.`;
    if (!Number.isNaN(Number(actual)) && !Number.isNaN(Number(expected)) && Number(actual) === Number(expected)) {
        return 'The value is right but the number format differs (for example 1.0 vs 1).';
    }
    return null;
}
