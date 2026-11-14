export const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

export const store = {
    get(key) {
        try { return localStorage.getItem(key); } catch { return null; }
    },
    set(key, value) {
        try { localStorage.setItem(key, value); } catch { /* full or disabled */ }
    },
};

export function debounce(fn, ms) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
}

export const STATUS_LABELS = {
    accepted: 'Accepted',
    wrong_answer: 'Wrong answer',
    runtime_error: 'Runtime error',
    time_limit: 'Time limit exceeded',
    compile_error: 'Compile error',
};

/** Tailwind text colour per verdict status. */
export const STATUS_TEXT = {
    accepted: 'text-emerald-600 dark:text-emerald-400',
    wrong_answer: 'text-rose-600 dark:text-rose-400',
    runtime_error: 'text-amber-600 dark:text-amber-400',
    time_limit: 'text-amber-600 dark:text-amber-400',
    compile_error: 'text-rose-600 dark:text-rose-400',
};

export function relativeTime(iso) {
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 45) return 'just now';
    const units = [['day', 86400], ['hour', 3600], ['minute', 60]];
    for (const [name, secs] of units) {
        if (diff >= secs) {
            const n = Math.floor(diff / secs);
            return `${n} ${name}${n === 1 ? '' : 's'} ago`;
        }
    }
    return 'just now';
}

/**
 * POST JSON and read a newline-delimited JSON response, calling `onEvent`
 * for each event as it arrives. Throws an Error with a readable message for
 * HTTP-level failures (validation, rate limit, runtime missing).
 */
export async function postNdjson(url, body, onEvent, signal) {
    const res = await fetch(url, {
        method: 'POST',
        signal,
        headers: { 'Content-Type': 'application/json', Accept: 'application/x-ndjson, application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });

    if (!res.ok) throw new Error(await httpErrorMessage(res));

    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    for (;;) {
        const { done, value } = await reader.read();
        buffer += decoder.decode(value ?? new Uint8Array(), { stream: !done });
        let nl;
        while ((nl = buffer.indexOf('\n')) >= 0) {
            const line = buffer.slice(0, nl).trim();
            buffer = buffer.slice(nl + 1);
            if (line) onEvent(JSON.parse(line));
        }
        if (done) break;
    }
    if (buffer.trim()) onEvent(JSON.parse(buffer));
}

export async function postJson(url, body, signal) {
    const res = await fetch(url, {
        method: 'POST',
        signal,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error(await httpErrorMessage(res));
    return res.json();
}

async function httpErrorMessage(res) {
    if (res.status === 429) {
        const wait = res.headers.get('Retry-After');
        return `Too many requests${wait ? ` — try again in ${wait}s` : ''}.`;
    }
    const data = await res.json().catch(() => ({}));
    const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
    return first ?? data.message ?? `Request failed (${res.status}).`;
}

/** Find the most relevant source line mentioned in a runtime error. */
/** `harness`: function problems, where Go's main.go is the hidden harness. */
export function errorLineFromStderr(stderr, language, harness = false) {
    if (!stderr) return null;
    const last = (re) => {
        let m, found = null;
        const rx = new RegExp(re.source, 'g');
        while ((m = rx.exec(stderr)) !== null) found = Number(m[1]);
        return found;
    };
    const first = (re) => {
        const m = re.exec(stderr);
        return m ? Number(m[1]) : null;
    };
    switch (language) {
        case 'python': return last(/File "[^"]*main\.py", line (\d+)/);
        case 'javascript': return first(/main\.js:(\d+)/);
        case 'go': return first(/solution\.go:(\d+)/) ?? (harness ? null : first(/main\.go:(\d+)/));
        case 'php': return first(/on line (\d+)/);
        default: return null;
    }
}

/** Small bottom-centre toast with an optional action button. */
export function toast(message, action) {
    let host = document.getElementById('problem-toasts');
    if (!host) {
        host = el('div', 'problem-toasts');
        host.id = 'problem-toasts';
        host.setAttribute('role', 'status');
        document.body.append(host);
    }
    const node = el('div', 'problem-toast');
    node.append(el('span', '', message));
    const remove = () => node.remove();
    if (action) {
        const btn = el('button', 'problem-toast-action', action.label);
        btn.type = 'button';
        btn.addEventListener('click', () => { action.run(); remove(); });
        node.append(btn);
    }
    host.append(node);
    setTimeout(remove, action ? 7000 : 3500);
}
