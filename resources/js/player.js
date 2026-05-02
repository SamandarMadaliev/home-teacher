const cfg = window.__COURSE_PLAYER__;

if (!cfg || !cfg.progressUrl) {
    throw new Error('Course player config missing');
}

const video = document.getElementById('course-video');

if (!video) {
    throw new Error('#course-video not found');
}

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function sendProgress() {
    const duration = Number.isFinite(video.duration) && video.duration > 0 ? video.duration : null;

    fetch(cfg.progressUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            current_time: video.currentTime,
            duration,
        }),
    }).catch(() => {});
}

let tick;

video.addEventListener('loadedmetadata', () => {
    if (cfg.initialPosition > 0 && !Number.isNaN(cfg.initialPosition)) {
        try {
            video.currentTime = cfg.initialPosition;
        } catch {
            //
        }
    }
});

video.addEventListener('play', () => {
    if (tick) {
        clearInterval(tick);
    }
    tick = window.setInterval(sendProgress, 10_000);
});

video.addEventListener('pause', () => {
    if (tick) {
        clearInterval(tick);
        tick = null;
    }
    sendProgress();
});

video.addEventListener('ended', () => {
    sendProgress();
    if (cfg.nextUrl) {
        window.location.assign(cfg.nextUrl);
    }
});

document.addEventListener('keydown', (e) => {
    const tag = (e.target && e.target.tagName) || '';
    if (tag === 'INPUT' || tag === 'TEXTAREA') {
        return;
    }

    if (e.code === 'Space') {
        e.preventDefault();
        if (video.paused) {
            void video.play();
        } else {
            video.pause();
        }
        return;
    }

    if (e.code === 'ArrowRight') {
        e.preventDefault();
        const dur =
            Number.isFinite(video.duration) && video.duration > 0 ? video.duration : Number.POSITIVE_INFINITY;
        video.currentTime = Math.min(video.currentTime + 10, dur);
    }
});

window.addEventListener('beforeunload', () => {
    sendProgress();
});
