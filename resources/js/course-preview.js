import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import '../css/plyr-overrides.css';

function initCoursePreview() {
    const overlay = document.getElementById('course-preview-modal');
    if (!overlay) {
        return;
    }

    const backdrop = document.getElementById('course-preview-backdrop');
    const closeBtn = document.getElementById('course-preview-close');
    const dismissBtn = document.getElementById('course-preview-dismiss');
    const openBtn = document.getElementById('course-preview-open');
    const videoEl = document.getElementById('course-preview-video');

    /** @type {InstanceType<typeof Plyr> | null} */
    let player = null;

    function ensurePlayer() {
        if (player || !videoEl) {
            return player;
        }

        player = new Plyr(videoEl, {
            keyboard: { focused: true, global: false },
            tooltips: { controls: true, seek: true },
            resetOnEnd: false,
        });

        return player;
    }

    function openModal() {
        overlay.hidden = false;
        overlay.removeAttribute('hidden');
        overlay.classList.add('course-preview-modal--open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('course-preview-open');

        ensurePlayer();
    }

    function closeModal() {
        if (player) {
            player.pause();
        }

        overlay.hidden = true;
        overlay.setAttribute('hidden', '');
        overlay.classList.remove('course-preview-modal--open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('course-preview-open');
    }

    backdrop?.addEventListener('click', () => closeModal());
    closeBtn?.addEventListener('click', () => closeModal());
    dismissBtn?.addEventListener('click', () => closeModal());
    openBtn?.addEventListener('click', () => openModal());

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && overlay.getAttribute('aria-hidden') === 'false') {
            closeModal();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCoursePreview);
} else {
    initCoursePreview();
}
