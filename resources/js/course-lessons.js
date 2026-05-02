import Sortable from 'sortablejs';

const root = document.getElementById('course-lessons-sortable');
if (root?.dataset.reorderUrl) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const reorderUrl = root.dataset.reorderUrl;

    let saveTimer = null;

    function collectIds() {
        return [...root.querySelectorAll('[data-video-id]')].map((el) =>
            Number.parseInt(el.getAttribute('data-video-id') ?? '', 10),
        );
    }

    function saveOrder() {
        const videoIds = collectIds();
        if (videoIds.length === 0 || videoIds.some((id) => Number.isNaN(id))) {
            return;
        }

        fetch(reorderUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ video_ids: videoIds }),
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error(`Reorder failed (${res.status})`);
                }
                return res.json();
            })
            .then(() => {
                window.location.reload();
            })
            .catch(() => {
                window.alert('Could not save lesson order. Try again.');
                window.location.reload();
            });
    }

    new Sortable(root, {
        animation: 160,
        handle: '.lesson-drag-handle',
        draggable: '[data-video-id]',
        ghostClass: 'opacity-50',
        onEnd() {
            if (saveTimer) {
                clearTimeout(saveTimer);
            }
            saveTimer = window.setTimeout(saveOrder, 200);
        },
    });
}
