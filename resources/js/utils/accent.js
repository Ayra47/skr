// Synchronize accent color across pages using localStorage

export function applyAccentColor(color) {
    if (!/^#[0-9a-fA-F]{6}$/.test(color)) {
        console.warn('Invalid color format:', color);
        return;
    }
    document.documentElement.style.setProperty('--accent', color);

    // Also update derivatives inline for immediate effect
    document.documentElement.style.setProperty('--accent-deep', `color-mix(in srgb, ${color} 55%, #000 45%)`);
    document.documentElement.style.setProperty('--accent-soft', `color-mix(in srgb, ${color} 11%, transparent)`);

    localStorage.setItem('app-accent-color', color);
}

export function getAccentColor() {
    // Priority: localStorage > window.Laravel > default
    const stored = localStorage.getItem('app-accent-color');
    if (stored && /^#[0-9a-fA-F]{6}$/.test(stored)) {
        return stored;
    }
    return window.Laravel?.profileSettings?.accent_color ?? '#5bbeff';
}

export function initAccentOnLoad() {
    const color = getAccentColor();
    applyAccentColor(color);
}
