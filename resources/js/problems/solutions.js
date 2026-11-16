/**
 * Solutions tab: other people's accepted code (each person's latest per
 * language), shown after you solve the problem. People a problem was shared
 * with "including the solution" see the owner's solutions straight away.
 */
import { createEditor } from './editor.js';
import { el, relativeTime, requestJson, toast } from './util.js';

export function initSolutions(root, cfg, { openInEditor, canOpen }) {
    const panel = root.querySelector('[data-statement-panel="solutions"]');
    const tab = root.querySelector('[data-statement-tab="solutions"]');
    if (!panel || !tab) return { unlock() {} };

    const sc = cfg.solutions; // { access: all|owner|locked, count }
    const langLabel = (k) => cfg.languageLabels[k] ?? k;
    let loaded = false;
    let filters = { language: '', sort: 'newest' };
    let viewer = null;

    const body = el('div', 'space-y-3');
    panel.replaceChildren(body);

    function locked() {
        const n = sc.count;
        body.replaceChildren(
            el('p', 'text-sm text-slate-700 dark:text-slate-300', '🔒 Solve this problem to see how other people solved it.'),
            el('p', 'text-xs text-slate-600 dark:text-slate-500', n ? `${n} ${n === 1 ? 'solution is' : 'solutions are'} waiting.` : 'Nobody has shared a solution yet.'),
        );
    }

    async function load() {
        loaded = true;
        viewer?.destroy();
        viewer = null;
        body.replaceChildren(el('p', 'text-sm text-slate-600 dark:text-slate-400', 'Loading…'));
        const qs = new URLSearchParams(Object.entries(filters).filter(([, v]) => v));
        const res = await fetch(`${cfg.urls.solutions}?${qs}`, { headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            if (data.error === 'locked') { sc.access = 'locked'; return locked(); }
            return body.replaceChildren(el('p', 'text-sm text-rose-600', data.message ?? 'Could not load solutions.'));
        }
        renderList(data);
    }

    function toolbar() {
        const bar = el('div', 'flex flex-wrap items-center gap-2');
        const lang = el('select', 'input-field w-auto py-1 text-xs font-sans');
        lang.setAttribute('aria-label', 'Language');
        [['', 'All languages'], ...Object.entries(cfg.languageLabels)].forEach(([v, l]) => {
            const o = el('option', '', l);
            o.value = v;
            o.selected = v === filters.language;
            lang.append(o);
        });
        lang.addEventListener('change', () => { filters.language = lang.value; load(); });
        const sort = el('select', 'input-field w-auto py-1 text-xs font-sans');
        sort.setAttribute('aria-label', 'Sort');
        [['newest', 'Newest'], ['fastest', 'Fastest']].forEach(([v, l]) => {
            const o = el('option', '', l);
            o.value = v;
            o.selected = v === filters.sort;
            sort.append(o);
        });
        sort.addEventListener('change', () => { filters.sort = sort.value; load(); });
        bar.append(lang, sort);
        return bar;
    }

    function renderList(data) {
        const nodes = [toolbar()];
        if (data.access === 'owner') {
            nodes.push(el('p', 'rounded-lg bg-accent/5 p-2.5 text-xs text-slate-700 dark:text-slate-300',
                'The author shared their solution with you. Solve the problem to see everyone else’s.'));
        }

        const list = el('ul', 'divide-y divide-slate-200/90 dark:divide-slate-800/90');
        list.setAttribute('role', 'list');

        if (data.reference) {
            list.append(row({
                title: 'Author’s reference solution',
                meta: `${data.reference.author ?? 'Author'} · ${langLabel(data.reference.language)}`,
                badges: [['Reference', 'bg-accent/10 text-accent']],
                open: () => openSolution(cfg.urls.reference, 'Reference solution'),
            }));
        }
        data.solutions.forEach((s) => {
            const badges = [];
            if (s.is_you) badges.push(['You', 'bg-accent/10 text-accent']);
            else if (s.is_owner) badges.push(['Author', 'bg-slate-500/10 text-slate-700 dark:text-slate-300']);
            if (s.outdated) badges.push(['Older version', 'bg-amber-500/10 text-amber-700 dark:text-amber-300']);
            if (s.moderated) badges.push(['Hidden by admin', 'bg-rose-500/10 text-rose-700 dark:text-rose-300']);
            list.append(row({
                title: s.author,
                meta: `${langLabel(s.language)} · ${s.duration_ms} ms · ${relativeTime(s.created_at)}`,
                badges,
                open: () => openSolution(cfg.urls.solution.replace(':id', s.id), s.author, s),
            }));
        });
        if (!data.reference && !data.solutions.length) {
            nodes.push(el('p', 'text-sm text-slate-600 dark:text-slate-400', filters.language ? 'No solutions in this language yet.' : 'No solutions yet.'));
        } else {
            nodes.push(list);
        }

        if (data.solutions.some((s) => s.is_you)) {
            const hideAll = el('button', 'btn-ghost text-xs', 'Hide all mine');
            hideAll.type = 'button';
            hideAll.addEventListener('click', async () => {
                if (!confirm('Hide all your accepted submissions from Solutions? You can show them again from the Submissions tab.')) return;
                const r = await requestJson('POST', cfg.urls.hideAll, {});
                toast(r.data.message);
                if (r.ok) load();
            });
            nodes.push(hideAll);
        }
        body.replaceChildren(...nodes);
    }

    function row({ title, meta, badges, open }) {
        const li = el('li');
        const btn = el('button', 'flex w-full flex-wrap items-center justify-between gap-2 py-2.5 text-left text-xs hover:bg-slate-500/5');
        btn.type = 'button';
        const left = el('span', 'flex flex-wrap items-center gap-1.5');
        left.append(el('span', 'font-semibold text-slate-900 dark:text-slate-100', title));
        badges.forEach(([t, c]) => left.append(el('span', `rounded-full px-2 py-0.5 text-[11px] font-semibold ${c}`, t)));
        btn.append(left, el('span', 'text-slate-600 dark:text-slate-500', meta));
        btn.addEventListener('click', open);
        li.append(btn);
        return li;
    }

    async function openSolution(url, title, summary = null) {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const s = await res.json().catch(() => ({}));
        if (!res.ok) return toast(s.message ?? 'Could not load that solution.');

        const head = el('div', 'flex flex-wrap items-center justify-between gap-2');
        const back = el('button', 'text-xs font-semibold text-slate-600 hover:underline dark:text-slate-400', '← All solutions');
        back.type = 'button';
        back.addEventListener('click', load);
        head.append(back, el('span', 'text-xs font-semibold text-slate-700 dark:text-slate-300', `${title} · ${langLabel(s.language)}`));

        const nodes = [head];
        if (s.outdated) {
            nodes.push(el('p', 'rounded-lg bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-300',
                'Written for an older version of this problem (the tests or the function signature changed since). It may not pass now.'));
        }
        const box = el('div', 'overflow-hidden rounded-xl border border-slate-200/95 dark:border-slate-800/90');
        const host = el('div', 'problem-editor h-80');
        box.append(host);
        nodes.push(box);

        const actions = el('div', 'flex flex-wrap gap-2');
        const openBtn = el('button', 'btn-secondary text-xs', 'Open in editor');
        openBtn.type = 'button';
        openBtn.disabled = !canOpen(s.language);
        if (openBtn.disabled) openBtn.title = `${langLabel(s.language)} isn’t available here.`;
        openBtn.addEventListener('click', () => openInEditor(s.language, s.code, { outdated: s.outdated, label: title }));
        actions.append(openBtn);

        if (cfg.canModerate && summary) {
            const mod = el('button', 'btn-ghost text-xs text-rose-600', summary.moderated ? 'Restore (admin)' : 'Hide (admin)');
            mod.type = 'button';
            mod.addEventListener('click', async () => {
                const r = await requestJson('POST', cfg.urls.moderateSolution.replace(':id', summary.id), { hidden: !summary.moderated });
                toast(r.data.message);
                if (r.ok) load();
            });
            actions.append(mod);
        }
        nodes.push(actions);

        body.replaceChildren(...nodes);
        viewer?.destroy();
        viewer = createEditor({ parent: host, language: s.language, doc: s.code ?? '', readOnly: true });
    }

    tab.addEventListener('click', () => {
        if (sc.access === 'locked') locked();
        else if (!loaded) load();
    });
    if (sc.access === 'locked') locked();

    return {
        /** Called after an accepted submission: everyone's solutions open up. */
        unlock() {
            sc.access = 'all';
            sc.count += 1;
            loaded = false;
            if (tab.getAttribute('aria-selected') === 'true') load();
        },
    };
}
