/**
 * Lesson title: view mode + control to reveal inline edit (Save / Cancel / Escape).
 */
export function initLessonRename(root = document) {
    root.querySelectorAll('[data-lesson-rename]').forEach((block) => {
        const view = block.querySelector('.lesson-rename-view');
        const edit = block.querySelector('.lesson-rename-edit');
        const trigger = block.querySelector('.lesson-rename-trigger');
        const cancel = block.querySelector('.lesson-rename-cancel');
        const input = edit?.querySelector('input[name="title"]');
        if (!view || !edit || !trigger || !input) {
            return;
        }

        function close() {
            const reset = edit.getAttribute('data-original-title');
            if (reset != null) {
                input.value = reset;
            }
            block.classList.remove('lesson-rename--editing');
            trigger.setAttribute('aria-expanded', 'false');
        }

        function open() {
            block.classList.add('lesson-rename--editing');
            trigger.setAttribute('aria-expanded', 'true');
            input.focus();
            input.select();
        }

        trigger.setAttribute('aria-expanded', block.classList.contains('lesson-rename--editing') ? 'true' : 'false');
        if (input.id) {
            trigger.setAttribute('aria-controls', input.id);
        }

        if (block.classList.contains('lesson-rename--editing')) {
            queueMicrotask(() => {
                input.focus();
                input.select();
            });
        }

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            open();
        });

        cancel?.addEventListener('click', () => close());

        edit.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
            }
        });
    });
}
