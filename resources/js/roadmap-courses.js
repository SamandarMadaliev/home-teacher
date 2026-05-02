import Sortable from 'sortablejs';

const root = document.getElementById('roadmap-courses-sortable');
if (root?.dataset.reorderUrl) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const reorderUrl = root.dataset.reorderUrl;

    let saveTimer = null;

    function collectIds() {
        return [...root.querySelectorAll('[data-course-id]')].map((el) =>
            Number.parseInt(el.getAttribute('data-course-id') ?? '', 10),
        );
    }

    function saveOrder() {
        const courseIds = collectIds();
        if (courseIds.length === 0 || courseIds.some((id) => Number.isNaN(id))) {
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
            body: JSON.stringify({ course_ids: courseIds }),
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
                window.alert('Could not save course order. Try again.');
                window.location.reload();
            });
    }

    new Sortable(root, {
        animation: 160,
        handle: '.roadmap-drag-handle',
        draggable: '[data-course-id]',
        ghostClass: 'opacity-50',
        onEnd() {
            if (saveTimer) {
                clearTimeout(saveTimer);
            }
            saveTimer = window.setTimeout(saveOrder, 200);
        },
    });
}
