import confetti from 'canvas-confetti';

const BURST_COLORS = ['#38bdf8', '#2563eb', '#0ea5e9', '#10b981', '#f59e0b', '#a78bfa'];

/**
 * @param {{ title?: string, courseUrl?: string }} [options]
 */
export function celebrateCourseComplete(options = {}) {
    const end = Date.now() + 2800;

    const frame = () => {
        confetti({
            particleCount: 2,
            angle: 60,
            spread: 62,
            origin: { x: 0, y: 0.65 },
            colors: BURST_COLORS,
            disableForReducedMotion: true,
        });
        confetti({
            particleCount: 2,
            angle: 120,
            spread: 62,
            origin: { x: 1, y: 0.65 },
            colors: BURST_COLORS,
            disableForReducedMotion: true,
        });

        if (Date.now() < end) {
            requestAnimationFrame(frame);
        }
    };

    confetti({
        particleCount: 88,
        spread: 78,
        startVelocity: 38,
        origin: { y: 0.58 },
        colors: BURST_COLORS,
        disableForReducedMotion: true,
    });

    frame();

    showCourseCompleteToast(options.title, options.courseUrl);
}

/**
 * @param {string} [title]
 * @param {string} [courseUrl]
 */
function showCourseCompleteToast(title, courseUrl) {
    const toast = document.getElementById('course-complete-toast');
    const titleEl = document.getElementById('course-complete-toast-title');
    const link = document.getElementById('course-complete-toast-link');

    if (!toast || !titleEl) {
        return;
    }

    titleEl.textContent = title ? `You finished “${title}”` : 'You finished this course';

    if (link && courseUrl) {
        link.href = courseUrl;
        link.classList.remove('hidden');
    } else if (link) {
        link.classList.add('hidden');
    }

    toast.classList.remove('hidden');
    toast.classList.add('course-complete-toast--visible');

    window.setTimeout(() => {
        toast.classList.remove('course-complete-toast--visible');
        window.setTimeout(() => toast.classList.add('hidden'), 400);
    }, 9000);
}
