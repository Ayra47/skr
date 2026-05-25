export function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('app-theme', theme);
}

export function getTheme() {
    const stored = localStorage.getItem('app-theme');
    if (stored === 'dark' || stored === 'light') {
        return stored;
    }
    return window.Laravel?.profileSettings?.theme ?? 'dark';
}

export function initThemeOnLoad() {
    applyTheme(getTheme());
}
