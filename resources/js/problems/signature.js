/**
 * Function-style problems: types, starter-code generation, JSON value
 * checking and the paste parsers. Mirrors App\Support\FunctionSignature.
 *
 * A signature is { name, params: [{ name, type }], returns }.
 * A test's input is one minified JSON value per line, in parameter order.
 */

export const TYPE_GROUPS = [
    ['Scalars', ['int', 'float', 'string', 'bool']],
    ['Lists', ['int[]', 'float[]', 'string[]', 'bool[]']],
    ['Grids', ['int[][]', 'float[][]', 'string[][]', 'bool[][]']],
];
export const FUNCTION_LANGUAGES = ['python', 'javascript', 'php', 'go'];

const PY = { int: 'int', float: 'float', string: 'str', bool: 'bool' };
const JS = { int: 'number', float: 'number', string: 'string', bool: 'boolean' };
const PHP = { int: 'int', float: 'float', string: 'string', bool: 'bool' };
const GO = { int: 'int', float: 'float64', string: 'string', bool: 'bool' };
const GO_ZERO = { int: '0', float: '0', string: '""', bool: 'false' };

const depth = (t) => (t.match(/\[\]/g) || []).length;
const base = (t) => t.replace(/\[\]/g, '');

export function langType(lang, type) {
    const b = base(type);
    const d = depth(type);
    if (lang === 'python') {
        let t = PY[b];
        for (let i = 0; i < d; i++) t = `list[${t}]`;
        return t;
    }
    if (lang === 'javascript') return JS[b] + '[]'.repeat(d);
    if (lang === 'php') return d ? 'array' : PHP[b];
    if (lang === 'go') return '[]'.repeat(d) + GO[b];
    return type;
}

/** Starter code for a language, generated from the signature. */
export function starterFor(lang, sig) {
    const name = sig.name || 'solve';
    const params = sig.params.filter((p) => p.name);
    if (lang === 'python') {
        const args = params.map((p) => `${p.name}: ${langType('python', p.type)}`).join(', ');
        return `def ${name}(${args}) -> ${langType('python', sig.returns)}:\n    # Write your solution here\n    pass\n`;
    }
    if (lang === 'javascript') {
        const doc = [
            '/**',
            ...params.map((p) => ` * @param {${langType('javascript', p.type)}} ${p.name}`),
            ` * @return {${langType('javascript', sig.returns)}}`,
            ' */',
        ].join('\n');
        return `${doc}\nfunction ${name}(${params.map((p) => p.name).join(', ')}) {\n    // Write your solution here\n}\n`;
    }
    if (lang === 'php') {
        const phpDoc = (t) => (depth(t) ? PHP[base(t)] + '[]'.repeat(depth(t)) : PHP[t]);
        const doc = [
            '/**',
            ...params.map((p) => ` * @param ${phpDoc(p.type)} $${p.name}`),
            ` * @return ${phpDoc(sig.returns)}`,
            ' */',
        ].join('\n');
        const args = params.map((p) => `${langType('php', p.type)} $${p.name}`).join(', ');
        return `<?php\n\n${doc}\nfunction ${name}(${args}): ${langType('php', sig.returns)}\n{\n    // Write your solution here\n}\n`;
    }
    if (lang === 'go') {
        // Go won't compile a function without a return, so start with a zero value.
        const args = params.map((p) => `${p.name} ${langType('go', p.type)}`).join(', ');
        const zero = depth(sig.returns) ? 'nil' : GO_ZERO[sig.returns];
        return `package main\n\nfunc ${name}(${args}) ${langType('go', sig.returns)} {\n\t// Write your solution here\n\treturn ${zero}\n}\n`;
    }
    return '';
}

/** One-line human signature: twoSum(nums: int[], target: int) → int[] */
export const describeSignature = (sig) => `${sig.name || '…'}(${sig.params.map((p) => `${p.name}: ${p.type}`).join(', ')}) → ${sig.returns}`;

/* ───────── JSON values ───────── */

/** Remove whitespace outside strings without re-encoding numbers (keeps big ints exact). */
export function minifyJson(text) {
    let out = '';
    let inStr = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (inStr) {
            out += c;
            if (c === '\\') { out += text[++i] ?? ''; continue; }
            if (c === '"') inStr = false;
        } else if (c === '"') { inStr = true; out += c; }
        else if (!/\s/.test(c)) out += c;
    }
    return out;
}

class Num { constructor(v, isInt) { this.v = v; this.isInt = isInt; } }

/** Parse JSON keeping int-vs-float apart (via the reviver's source text when available). */
function parseTyped(text) {
    return JSON.parse(text, (k, v, ctx) => (typeof v === 'number'
        ? new Num(v, ctx?.source ? !/[.eE]/.test(ctx.source) : Number.isInteger(v))
        : v));
}

function matches(v, type) {
    if (type.endsWith('[]')) return Array.isArray(v) && v.every((x) => matches(x, type.slice(0, -2)));
    if (type === 'int') return v instanceof Num && v.isInt;
    if (type === 'float') return v instanceof Num;
    if (type === 'string') return typeof v === 'string';
    if (type === 'bool') return typeof v === 'boolean';
    return false;
}

function describe(v) {
    if (v === null) return 'null';
    if (v instanceof Num) return v.isInt ? 'an int' : 'a float';
    if (typeof v === 'string') return 'a string';
    if (typeof v === 'boolean') return 'a boolean';
    if (Array.isArray(v)) return 'a list';
    return 'an object';
}

export const typeLabel = (t) => ({ int: 'an int', float: 'a number', string: 'a string', bool: 'a boolean' }[t] ?? `a list (${t})`);

/** Check one raw value against a type → null when fine, else a short message. */
export function checkValue(raw, type) {
    const text = raw.trim();
    if (text === '') return 'Missing value';
    let v;
    try { v = parseTyped(text); } catch {
        return type === 'string' ? 'Not valid JSON — wrap text in "double quotes"' : 'Not valid JSON';
    }
    return matches(v, type) ? null : `Should be ${typeLabel(type)}, got ${describe(v)}`;
}

/**
 * Normalise what an author typed for a parameter: minify JSON, and quote
 * bare text for string parameters ("hello" → "\"hello\"").
 */
export function normalizeValue(raw, type) {
    const text = raw.trim();
    if (text === '') return '';
    try { JSON.parse(text); return minifyJson(text); } catch { /* fall through */ }
    if (type === 'string') return JSON.stringify(text);
    return text.replace(/\s*\n\s*/g, ' ');
}

/** Split stored input into one raw value per parameter. */
export function splitArgs(input, count) {
    const lines = (input ?? '').replace(/\r\n/g, '\n').replace(/\n+$/, '').split('\n');
    return Array.from({ length: count }, (_, i) => (count === 0 ? '' : lines[i] ?? ''));
}

/** "nums = [2,7]\ntarget = 9" for display. */
export function formatArgs(sig, input) {
    if (!sig.params.length) return '(no arguments)';
    const vals = splitArgs(input, sig.params.length);
    return sig.params.map((p, i) => `${p.name} = ${vals[i]}`).join('\n');
}

/* ───────── Paste parsers ───────── */

/** Split on commas that sit outside brackets and strings. */
function splitTopLevel(text, sep = ',') {
    const parts = [];
    let cur = '';
    let level = 0;
    let inStr = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (inStr) {
            cur += c;
            if (c === '\\') { cur += text[++i] ?? ''; continue; }
            if (c === '"') inStr = false;
            continue;
        }
        if (c === '"') inStr = true;
        else if ('[{('.includes(c)) level++;
        else if (']})'.includes(c)) level--;
        if (c === sep && level === 0) { parts.push(cur); cur = ''; continue; }
        cur += c;
    }
    if (cur.trim() !== '') parts.push(cur);
    return parts.map((p) => p.trim());
}

const PY_TYPES = { int: 'int', float: 'float', str: 'string', bool: 'bool' };

function pyType(t) {
    t = t.replace(/\s+/g, '');
    const m = /^(?:List|list)\[(.+)\]$/.exec(t);
    if (m) { const inner = pyType(m[1]); return inner ? `${inner}[]` : null; }
    const s = PY_TYPES[t];
    return s ?? null;
}

/**
 * Parse a pasted signature — Python (`def twoSum(self, nums: List[int], target: int) -> List[int]:`)
 * or TypeScript (`function twoSum(nums: number[], target: number): number[]`).
 * Returns a signature or an error message string.
 */
export function parseSignature(text) {
    const py = /def\s+([A-Za-z_]\w*)\s*\(([^)]*)\)\s*(?:->\s*([^:]+))?:?/.exec(text);
    if (py) {
        const params = [];
        for (const part of splitTopLevel(py[2])) {
            if (!part || part === 'self') continue;
            const [name, type] = part.split(':').map((s) => s?.trim());
            const t = type ? pyType(type.split('=')[0]) : null;
            if (!t) return `Can’t map the type of “${name}” (${type ?? 'missing'}). Supported: int, float, str, bool and lists of them.`;
            params.push({ name, type: t });
        }
        const returns = py[3] ? pyType(py[3].trim()) : null;
        if (!returns) return `Can’t map the return type (${py[3]?.trim() ?? 'missing'}).`;
        return { name: py[1], params, returns };
    }
    const ts = /function\s+([A-Za-z_$][\w$]*)\s*\(([^)]*)\)\s*:\s*([^{\s]+)/.exec(text)
        ?? /([A-Za-z_$][\w$]*)\s*=\s*(?:function)?\s*\(([^)]*)\)\s*:\s*([^={\s]+)/.exec(text);
    if (ts) {
        const tsType = (t) => {
            t = t.trim();
            const d = depth(t);
            const b = { number: 'int', string: 'string', boolean: 'bool' }[base(t)];
            return b ? b + '[]'.repeat(d) : null;
        };
        const params = [];
        for (const part of splitTopLevel(ts[2])) {
            const [name, type] = part.split(':').map((s) => s?.trim());
            const t = type ? tsType(type) : null;
            if (!t) return `Can’t map the type of “${name}”. Supported: number, string, boolean and arrays of them.`;
            params.push({ name, type: t });
        }
        const returns = tsType(ts[3]);
        if (!returns) return 'Can’t map the return type.';
        return { name: ts[1], params, returns };
    }
    return 'Paste a Python `def …` or TypeScript `function …` signature.';
}

/**
 * Parse LeetCode-style examples:
 *   Input: nums = [2,7,11,15], target = 9
 *   Output: [0,1]
 * Values are matched to parameters by name, falling back to position.
 * Returns [{ input, expected_output }].
 */
export function parseLeetcodeExamples(text, sig) {
    const out = [];
    const rx = /Input:\s*([\s\S]*?)\n?\s*Output:\s*(.+)/g;
    let m;
    while ((m = rx.exec(text)) !== null) {
        const parts = splitTopLevel(m[1].replace(/\n/g, ' '));
        const byName = {};
        const positional = [];
        parts.forEach((p) => {
            const eq = /^([A-Za-z_]\w*)\s*=\s*([\s\S]+)$/.exec(p);
            if (eq) byName[eq[1]] = eq[2].trim();
            else positional.push(p);
        });
        const values = sig.params.map((p, i) => normalizeValue(byName[p.name] ?? Object.values(byName)[i] ?? positional[i] ?? '', p.type));
        out.push({
            input: values.join('\n'),
            expected_output: normalizeValue(m[2].trim(), sig.returns),
        });
    }
    return out;
}

export const looksLikeLeetcode = (text) => /Input:\s*[\s\S]*Output:/.test(text);
