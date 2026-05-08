export const THEME_STORAGE_KEY = 'home-teacher-theme';

/** @param {'light' | 'dark' | 'system'} mode */
export function applyTheme(mode) {
    let useDark;
    if (mode === 'dark') {
        useDark = true;
    } else if (mode === 'light') {
        useDark = false;
    } else {
        useDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    document.documentElement.classList.toggle('dark', useDark);
}

export function initThemeToggle() {
    const btn = document.getElementById('theme-toggle');
    if (!btn) {
        return;
    }

    function syncChrome() {
        const dark = document.documentElement.classList.contains('dark');
        btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        btn.setAttribute(
            'title',
            dark ? 'Switch to light mode' : 'Switch to dark mode',
        );

        btn.querySelectorAll('[data-theme-when]').forEach((icon) => {
            const mode = icon.getAttribute('data-theme-when');
            icon.classList.toggle('hidden', (mode === 'dark') !== dark);
        });
    }

    syncChrome();

    btn.addEventListener('click', () => {
        const nextDark = !document.documentElement.classList.contains('dark');
        localStorage.setItem(
            THEME_STORAGE_KEY,
            nextDark ? 'dark' : 'light',
        );
        applyTheme(nextDark ? 'dark' : 'light');
        syncChrome();
    });

    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            if (localStorage.getItem(THEME_STORAGE_KEY) === null) {
                applyTheme('system');
                syncChrome();
            }
        });
}
