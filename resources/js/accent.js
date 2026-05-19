import { UserAccentColor } from './accent-constants.js';

/**
 * @param {string} accent
 */
export function applyAccent(accent) {
    const resolved = UserAccentColor.resolve(accent);
    document.documentElement.dataset.accent = resolved;
}

/**
 * @param {string} accent
 */
function syncSwatches(accent) {
    const resolved = UserAccentColor.resolve(accent);
    document.querySelectorAll('[data-accent-swatch]').forEach((button) => {
        const value = button.getAttribute('data-accent-value');
        const selected = value === resolved;
        button.classList.toggle('accent-picker-swatch--selected', selected);
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
}

export function initAccentPicker() {
    const root = document.getElementById('accent-picker');
    if (!root) {
        return;
    }

    const updateUrl = root.dataset.updateUrl;
    if (!updateUrl) {
        return;
    }

    const initial = document.documentElement.dataset.accent || UserAccentColor.DEFAULT;
    syncSwatches(initial);

    root.querySelectorAll('[data-accent-swatch]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();

            const accent = button.getAttribute('data-accent-value');
            if (!accent || button.getAttribute('aria-pressed') === 'true') {
                return;
            }

            const previous = document.documentElement.dataset.accent || UserAccentColor.DEFAULT;
            applyAccent(accent);
            syncSwatches(accent);

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const response = await fetch(updateUrl, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token ?? '',
                    },
                    body: JSON.stringify({ accent_color: accent }),
                });

                if (!response.ok) {
                    throw new Error('Failed to save accent');
                }

                const data = await response.json();
                applyAccent(data.accent_color);
                syncSwatches(data.accent_color);
            } catch {
                applyAccent(previous);
                syncSwatches(previous);
            }
        });
    });
}
