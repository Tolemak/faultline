const YEAR = 60 * 60 * 24 * 365;
const THEMES = ['light', 'dark'];

function setCookie(name, value) {
    const secure = window.location.protocol === 'https:' ? '; secure' : '';
    document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${YEAR}; samesite=lax${secure}`;
}

function storedTheme() {
    try {
        return window.localStorage.getItem('faultline-theme');
    } catch {
        return null;
    }
}

function applyInitialTheme() {
    const root = document.documentElement;
    if (THEMES.includes(root.dataset.theme)) {
        return;
    }

    let theme = storedTheme();
    if (!THEMES.includes(theme)) {
        theme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }
    root.dataset.theme = theme;
    setCookie('faultline_theme', theme);
}

document.addEventListener('tolemak-theme', (event) => {
    if (THEMES.includes(event.detail?.theme)) {
        setCookie('faultline_theme', event.detail.theme);
    }
});

document.addEventListener('tolemak-lang', (event) => {
    if (['pl', 'en'].includes(event.detail?.lang)) {
        setCookie('faultline_lang', event.detail.lang);
        window.location.reload();
    }
});

applyInitialTheme();
