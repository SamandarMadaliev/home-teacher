/**
 * Owner's Share dialog on the solve page: who can open the problem
 * (only me / specific people by email, max N / community), and per person
 * whether they may see the owner's solution before solving.
 */
import { el, requestJson, toast } from './util.js';

export function initShare(root, cfg) {
    const dialog = root.querySelector('[data-share-dialog]');
    if (!dialog || !cfg.share) return;
    const $ = (sel) => dialog.querySelector(sel);

    let state = cfg.share; // { visibility, shares[], max_shares, own_solutions, moderated }
    const form = $('[data-share-form]');
    const email = $('[data-share-email]');
    const includeBox = $('[data-share-include]');
    const msg = $('[data-share-message]');
    const list = $('[data-share-list]');
    const badge = root.querySelector('[data-visibility-badge]');

    const mode = () => (state.visibility === 'public' ? 'community' : state.shares.length ? 'people' : 'private');
    const say = (text, tone = 'text-slate-600 dark:text-slate-400') => { msg.className = `text-xs ${tone}`; msg.textContent = text ?? ''; };
    const fail = (text) => say(text, 'text-rose-600 dark:text-rose-400');

    function paint() {
        dialog.querySelectorAll('[data-share-mode]').forEach((r) => { r.checked = r.value === mode(); });
        $('[data-share-count]').textContent = state.visibility === 'public'
            ? `${state.shares.length} ${state.shares.length === 1 ? 'person' : 'people'} added directly`
            : `${state.shares.length} / ${state.max_shares} people`;

        list.replaceChildren(...state.shares.map((s) => {
            const li = el('li', 'flex flex-wrap items-center gap-x-3 gap-y-1 py-2');
            const who = el('div', 'min-w-0 flex-1');
            who.append(el('p', 'truncate text-sm font-semibold text-slate-900 dark:text-slate-100', s.name), el('p', 'truncate text-xs text-slate-600 dark:text-slate-500', s.email));
            const label = el('label', 'flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-300');
            const cb = el('input');
            cb.type = 'checkbox';
            cb.checked = s.include_solution;
            cb.addEventListener('change', async () => {
                const r = await requestJson('PATCH', cfg.urls.share.replace(':id', s.id), { include_solution: cb.checked });
                if (!r.ok) { cb.checked = !cb.checked; return fail(r.data.message); }
                apply(r.data);
            });
            label.append(cb, document.createTextNode('Can see my solution'));
            const remove = el('button', 'btn-ghost px-2 py-1 text-xs text-rose-600', 'Remove');
            remove.type = 'button';
            remove.addEventListener('click', async () => {
                if (!confirm(`Remove ${s.name}'s access?`)) return;
                const r = await requestJson('DELETE', cfg.urls.share.replace(':id', s.id));
                if (!r.ok) return fail(r.data.message);
                apply(r.data);
            });
            li.append(who, label, remove);
            return li;
        }));
        $('[data-share-empty]').classList.toggle('hidden', state.shares.length > 0);

        if (badge) {
            const labels = { private: 'Only me', people: `Shared · ${state.shares.length}`, community: 'Community' };
            badge.textContent = labels[mode()];
        }
    }

    function apply(data) {
        state = { ...state, visibility: data.visibility, shares: data.shares, max_shares: data.max_shares, own_solutions: data.own_solutions };
        say(data.message);
        paint();
    }

    async function setVisibility(visibility, clearShares = false) {
        const r = await requestJson('POST', cfg.urls.visibility, { visibility, clear_shares: clearShares });
        if (!r.ok) { fail(r.data.message); paint(); return; }
        apply(r.data);
    }

    const publicWarning = () => (state.own_solutions
        ? `\n\nYour ${state.own_solutions} accepted ${state.own_solutions === 1 ? 'submission' : 'submissions'} will be visible to people who solve it. You can hide them in the Submissions tab.`
        : '');

    dialog.querySelectorAll('[data-share-mode]').forEach((radio) => radio.addEventListener('change', () => {
        const target = radio.value;
        if (target === mode()) return;
        if (target === 'community') {
            if (!confirm(`Make this a community problem? Every member will be able to see and solve it.${publicWarning()}`)) return paint();
            setVisibility('public');
        } else if (target === 'private') {
            if (state.shares.length && !confirm(`Remove access for ${state.shares.length} ${state.shares.length === 1 ? 'person' : 'people'}? Their submissions are kept and come back if you share again.`)) return paint();
            setVisibility('private', true);
        } else if (state.visibility === 'public') {
            setVisibility('private'); // back to only the people added directly
        } else {
            paint();
            email.focus();
        }
    }));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = { email: email.value.trim(), include_solution: includeBox.checked };
        if (!body.email) return email.focus();
        say('Checking…');
        let r = await requestJson('POST', cfg.urls.shares, body);
        if (!r.ok && r.data.error === 'limit') {
            if (!confirm(`${r.data.message}${publicWarning()}\n\nMake it a community problem and add this person?`)) return say('');
            r = await requestJson('POST', cfg.urls.shares, { ...body, make_public: true });
        }
        if (!r.ok) return fail(r.data.message);
        email.value = '';
        includeBox.checked = false;
        apply(r.data);
    });

    root.querySelectorAll('[data-share-open]').forEach((b) => b.addEventListener('click', () => { say(''); paint(); dialog.showModal(); }));
    $('[data-share-close]').addEventListener('click', () => dialog.close());

    paint();
}

/** Admin: hide (or restore) a community problem. Admins aren't necessarily the owner. */
export function initModeration(root, cfg) {
    root.querySelector('[data-moderate-problem]')?.addEventListener('click', async (e) => {
        const hide = e.currentTarget.dataset.moderated !== '1';
        if (hide && !confirm('Hide this problem from the community? Its owner and the people it is shared with keep access.')) return;
        const r = await requestJson('POST', cfg.urls.moderate, { hidden: hide });
        toast(r.data.message);
        if (r.ok) window.location.reload();
    });
}
