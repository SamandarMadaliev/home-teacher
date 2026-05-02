import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import '../css/plyr-overrides.css';
import '../css/theater-mode.css';

const cfg = window.__COURSE_PLAYER__;

if (!cfg || !cfg.progressUrl) {
    throw new Error('Course player config missing');
}

const el = document.getElementById('course-video');

if (!el) {
    throw new Error('#course-video not found');
}

/** @type {InstanceType<typeof Plyr>} */
const player = new Plyr(el, {
    keyboard: { focused: true, global: false },
    tooltips: { controls: true, seek: true },
    resetOnEnd: false,
});

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function mediaDuration() {
    const d = player.duration;
    return Number.isFinite(d) && d > 0 ? d : null;
}

function sendProgress() {
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
    }).catch(() => {});
}

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
        window.location.assign(cfg.nextUrl);
    }
});

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

document.addEventListener('keydown', (e) => {
    if (isKeyboardTypingTarget(e.target)) {
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
    }
});

window.addEventListener('beforeunload', () => {
    sendProgress();
});

/* In-page theater mode (wider video, hide lesson list — not Fullscreen API) */
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

        e.preventDefault();
        applyTheaterMode(!document.documentElement.classList.contains('theater-watch'));
    });
}

initTheaterMode();
