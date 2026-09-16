import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import '../css/plyr-overrides.css';
import '../css/theater-mode.css';
import '../css/up-next.css';
import { initLessonRename } from './lesson-rename.js';
import { celebrateCourseComplete } from './confetti.js';

const cfg = window.__COURSE_PLAYER__;

if (!cfg || !cfg.progressUrl) {
    throw new Error('Course player config missing');
}

initLessonRename();

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/** @type {boolean} */
let courseCelebrationShown = false;

function maybeCelebrateCourseComplete(payload) {
    if (courseCelebrationShown || !payload?.course_just_completed) {
        return;
    }

    courseCelebrationShown = true;
    celebrateCourseComplete({
        title: payload.course_title ?? cfg.courseTitle,
        courseUrl: cfg.courseUrl,
    });
}

/*
 * The player element (`#course-video`) only exists for playable video lessons — PDF / HTML
 * lessons render a document viewer instead (see initManualCompletion() below). Everything in
 * this block is Plyr-specific and skipped entirely when there's nothing to play.
 */
const el = document.getElementById('course-video');

if (el) {
    /** @type {InstanceType<typeof Plyr>} */
    const player = new Plyr(el, {
        keyboard: { focused: true, global: false },
        tooltips: { controls: true, seek: true },
        resetOnEnd: false,
        fullscreen: { enabled: true, fallback: true, iosNative: true },
    });

    const mediaDuration = () => {
        const d = player.duration;
        return Number.isFinite(d) && d > 0 ? d : null;
    };

    const sendProgress = () =>
        fetch(cfg.progressUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                current_time: player.currentTime,
                duration: mediaDuration(),
            }),
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((payload) => {
                if (payload) {
                    maybeCelebrateCourseComplete(payload);
                }
            })
            .catch(() => {});

    let tick;

    player.on('loadedmetadata', () => {
        const pos = cfg.initialPosition;
        if (typeof pos === 'number' && pos > 0 && !Number.isNaN(pos)) {
            const dur = mediaDuration();
            const safe = dur !== null ? Math.min(pos, Math.max(0, dur - 0.5)) : pos;
            try {
                player.currentTime = safe;
            } catch {
                //
            }
        }
    });

    player.on('play', () => {
        if (tick) {
            clearInterval(tick);
        }
        tick = window.setInterval(sendProgress, 10_000);
    });

    player.on('pause', () => {
        if (tick) {
            clearInterval(tick);
            tick = null;
        }
        sendProgress();
    });

    player.on('ended', () => {
        sendProgress();
        if (cfg.nextUrl) {
            showUpNextOverlay();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (isKeyboardTypingTarget(e.target)) {
            return;
        }

        if (isUpNextOverlayOpen()) {
            return;
        }

        if (e.code === 'Space') {
            e.preventDefault();
            if (player.paused) {
                void player.play();
            } else {
                player.pause();
            }
            return;
        }

        if (e.code === 'ArrowLeft') {
            e.preventDefault();
            player.currentTime = Math.max(0, player.currentTime - 10);
            return;
        }

        if (e.code === 'ArrowRight') {
            e.preventDefault();
            const dur = mediaDuration() ?? Number.POSITIVE_INFINITY;
            player.currentTime = Math.min(player.currentTime + 10, dur);
            return;
        }

        if (e.code === 'ArrowUp') {
            e.preventDefault();
            player.increaseVolume(0.1);
            return;
        }

        if (e.code === 'ArrowDown') {
            e.preventDefault();
            player.decreaseVolume(0.1);
            return;
        }

        if (e.code === 'KeyF' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            e.preventDefault();
            player.fullscreen.toggle();
        }
    });

    window.addEventListener('beforeunload', () => {
        sendProgress();
    });

    /** Seek + timestamp helpers for lesson notes (optional DOM on watch page). */
    cfg.getCurrentTime = () => (Number.isFinite(player.currentTime) ? player.currentTime : 0);

    cfg.seekTo = (sec) => {
        const t = Number(sec);
        if (!Number.isFinite(t)) {
            return;
        }
        const dur = mediaDuration() ?? Number.POSITIVE_INFINITY;
        player.currentTime = Math.min(Math.max(0, t), dur);
    };
}

const UP_NEXT_COUNTDOWN_SEC = 5;

/** @type {ReturnType<typeof setInterval> | null} */
let upNextIntervalId = null;

function clearUpNextCountdown() {
    if (upNextIntervalId !== null) {
        window.clearInterval(upNextIntervalId);
        upNextIntervalId = null;
    }
}

function hideUpNextOverlay() {
    const overlay = document.getElementById('up-next-overlay');
    if (!overlay) {
        return;
    }
    overlay.classList.remove('up-next-overlay--visible');
    overlay.setAttribute('aria-hidden', 'true');
}

function isUpNextOverlayOpen() {
    return Boolean(document.getElementById('up-next-overlay')?.classList.contains('up-next-overlay--visible'));
}

function showUpNextOverlay() {
    const overlay = document.getElementById('up-next-overlay');
    const secEl = document.getElementById('up-next-seconds');
    const titleEl = document.getElementById('up-next-title');

    if (!overlay || !cfg.nextUrl) {
        return;
    }

    if (titleEl && cfg.nextTitle) {
        titleEl.textContent = cfg.nextTitle;
    }

    overlay.classList.add('up-next-overlay--visible');
    overlay.setAttribute('aria-hidden', 'false');

    let n = UP_NEXT_COUNTDOWN_SEC;
    if (secEl) {
        secEl.textContent = String(n);
    }

    clearUpNextCountdown();
    upNextIntervalId = window.setInterval(() => {
        n -= 1;
        if (n <= 0) {
            clearUpNextCountdown();
            hideUpNextOverlay();
            window.location.assign(cfg.nextUrl);
            return;
        }
        if (secEl) {
            secEl.textContent = String(n);
        }
    }, 1000);

    const cancelBtn = document.getElementById('up-next-cancel');
    cancelBtn?.focus({ preventScroll: true });
}

function initUpNextAutoplay() {
    const overlay = document.getElementById('up-next-overlay');
    if (!overlay || !cfg.nextUrl) {
        return;
    }

    const cancel = () => {
        clearUpNextCountdown();
        hideUpNextOverlay();
    };

    document.getElementById('up-next-cancel')?.addEventListener('click', cancel);

    document.getElementById('up-next-backdrop')?.addEventListener('click', cancel);

    document.getElementById('up-next-now')?.addEventListener('click', () => {
        clearUpNextCountdown();
        hideUpNextOverlay();
        window.location.assign(cfg.nextUrl);
    });

    document.addEventListener('keydown', (e) => {
        if (e.code !== 'Escape') {
            return;
        }
        if (!overlay.classList.contains('up-next-overlay--visible')) {
            return;
        }
        if (isKeyboardTypingTarget(e.target)) {
            return;
        }
        e.preventDefault();
        cancel();
    });
}

initUpNextAutoplay();

function isKeyboardTypingTarget(target) {
    if (!target || !target.tagName) {
        return false;
    }
    const tag = target.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        return true;
    }
    return Boolean(target.isContentEditable);
}

/* In-page theater mode (wider video; lesson list moves below the player — not Fullscreen API) */
const THEATER_STORAGE_KEY = 'homeTeacherTheaterMode';

function applyTheaterMode(enabled) {
    document.documentElement.classList.toggle('theater-watch', enabled);

    const btn = document.getElementById('theater-mode-toggle');
    if (btn) {
        btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        const labelOn = btn.getAttribute('data-label-on') ?? 'Exit theater';
        const labelOff = btn.getAttribute('data-label-off') ?? 'Theater mode';
        btn.textContent = enabled ? labelOn : labelOff;
    }

    try {
        localStorage.setItem(THEATER_STORAGE_KEY, enabled ? '1' : '0');
    } catch {
        //
    }

    window.requestAnimationFrame(() => {
        window.dispatchEvent(new Event('resize'));
    });
}

function initTheaterMode() {
    const layout = document.getElementById('watch-layout');
    const btn = document.getElementById('theater-mode-toggle');

    if (!layout || !btn) {
        return;
    }

    let initial = false;
    try {
        initial = localStorage.getItem(THEATER_STORAGE_KEY) === '1';
    } catch {
        //
    }
    applyTheaterMode(initial);

    btn.addEventListener('click', () => {
        applyTheaterMode(!document.documentElement.classList.contains('theater-watch'));
    });

    document.addEventListener('keydown', (e) => {
        if (e.code !== 'KeyT' || e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }

        if (isKeyboardTypingTarget(e.target)) {
            return;
        }

        if (isUpNextOverlayOpen()) {
            return;
        }

        e.preventDefault();
        applyTheaterMode(!document.documentElement.classList.contains('theater-watch'));
    });
}

initTheaterMode();

function formatMediaTime(seconds) {
    const t = Math.max(0, Math.floor(Number(seconds)));
    const h = Math.floor(t / 3600);
    const m = Math.floor((t % 3600) / 60);
    const s = t % 60;
    if (h > 0) {
        return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }
    return `${m}:${String(s).padStart(2, '0')}`;
}

/**
 * "Mark as done" toggle for lessons with no play position (PDF / HTML). Posts to
 * videos.mark-complete and reuses the same course-completion celebration as videos.
 */
function initManualCompletion() {
    const btn = document.getElementById('mark-complete-btn');
    const url = btn?.getAttribute('data-mark-complete-url');
    if (!btn || !url) {
        return;
    }

    const badge = document.getElementById('lesson-completed-badge');

    const applyState = (completed) => {
        btn.setAttribute('aria-pressed', completed ? 'true' : 'false');
        const onLabel = btn.getAttribute('data-label-on') ?? 'Completed ✓';
        const offLabel = btn.getAttribute('data-label-off') ?? 'Mark as done';
        btn.textContent = completed ? onLabel : offLabel;
        btn.classList.toggle('mark-complete-btn--done', completed);
        badge?.classList.toggle('hidden', !completed);
    };

    btn.addEventListener('click', () => {
        const next = btn.getAttribute('aria-pressed') !== 'true';
        btn.disabled = true;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ completed: next }),
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(new Error('request failed'))))
            .then((payload) => {
                applyState(Boolean(payload.completed));
                maybeCelebrateCourseComplete(payload);
            })
            .catch(() => {})
            .finally(() => {
                btn.disabled = false;
            });
    });
}

initManualCompletion();

function initLessonNotes() {
    const input = document.getElementById('note-timestamp-input');
    const label = document.getElementById('note-timestamp-label');
    const btnUse = document.getElementById('note-at-current-time');
    const btnClear = document.getElementById('note-clear-timestamp');

    if (!input || !label || !btnUse || !btnClear) {
        return;
    }

    function updateLabel() {
        const raw = input.value.trim();
        if (raw === '') {
            label.classList.add('hidden');
            label.textContent = '';
            return;
        }
        const n = Number(raw);
        if (!Number.isFinite(n)) {
            return;
        }
        label.textContent = `Will save at ${formatMediaTime(n)} in this lesson`;
        label.classList.remove('hidden');
    }

    btnUse.addEventListener('click', () => {
        const t = cfg.getCurrentTime?.() ?? NaN;
        if (!Number.isFinite(t) || t < 0) {
            return;
        }
        input.value = String(Math.round(t * 1000) / 1000);
        updateLabel();
    });

    btnClear.addEventListener('click', () => {
        input.value = '';
        updateLabel();
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-note-seek]');
        if (!btn) {
            return;
        }
        const s = Number(btn.getAttribute('data-note-seek'));
        if (!Number.isFinite(s)) {
            return;
        }
        cfg.seekTo?.(s);
    });

    updateLabel();
}

initLessonNotes();

function initWatchLessonTabs() {
    const root = document.getElementById('watch-lesson-tabs');
    if (!root) {
        return;
    }

    const tabs = Array.from(root.querySelectorAll('[data-watch-tab]'));
    const panels = Array.from(root.querySelectorAll('[data-watch-tab-panel]'));
    if (tabs.length === 0 || panels.length === 0) {
        return;
    }

    function activate(name) {
        tabs.forEach((tab) => {
            const active = tab.dataset.watchTab === name;
            tab.classList.toggle('watch-tab--active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.setAttribute('tabindex', active ? '0' : '-1');
        });
        panels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.watchTabPanel !== name);
        });
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activate(tab.dataset.watchTab);
        });
    });

    const tablist = root.querySelector('[role="tablist"]');
    tablist?.addEventListener('keydown', (e) => {
        const current = tabs.findIndex((t) => t.getAttribute('aria-selected') === 'true');
        if (current < 0) {
            return;
        }

        let next = current;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            next = (current + 1) % tabs.length;
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            next = (current - 1 + tabs.length) % tabs.length;
        } else if (e.key === 'Home') {
            next = 0;
        } else if (e.key === 'End') {
            next = tabs.length - 1;
        } else {
            return;
        }

        e.preventDefault();
        tabs[next].focus();
        activate(tabs[next].dataset.watchTab);
    });
}

initWatchLessonTabs();
