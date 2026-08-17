/**
 * Shared authenticated-app JavaScript: fetch wrapper (with CSRF token
 * auto-attached), toast notifications, sidebar toggle, theme toggle.
 * Every AJAX call in the dashboard/admin goes through Api.call().
 */
/**
 * The URL path prefix the app is served under ('' at the domain root, or
 * e.g. '/zetpro-main' when installed in a subfolder). Injected server-side
 * as window.__BASE__; falls back to '' so nothing breaks if absent.
 */
function appBase() {
    return (typeof window.__BASE__ === 'string') ? window.__BASE__ : '';
}

const Api = (function () {
    let csrfToken = window.__CSRF_TOKEN__ || '';

    function setCsrfToken(token) {
        csrfToken = token;
    }

    async function call(url, options = {}) {
        const opts = Object.assign({ method: 'GET', headers: {} }, options);
        opts.headers = Object.assign({}, opts.headers);

        if (opts.body && !(opts.body instanceof FormData)) {
            opts.headers['Content-Type'] = 'application/json';
            if (typeof opts.body !== 'string') {
                opts.body = JSON.stringify(opts.body);
            }
        }

        if (opts.method !== 'GET' && csrfToken) {
            opts.headers['X-CSRF-Token'] = csrfToken;
        }

        opts.credentials = 'same-origin';

        let res;
        try {
            res = await fetch(url, opts);
        } catch (err) {
            Toast.error('Network error. Please check your connection.');
            throw err;
        }

        let json;
        try {
            json = await res.json();
        } catch (err) {
            Toast.error('Unexpected server response.');
            throw err;
        }

        // A 401 normally means the session expired, so bounce to the login page.
        // But on the login page itself a 401 just means "wrong password" - and
        // redirecting there would reload the page and wipe what the user typed.
        // Let the caller render the message inline instead.
        if (res.status === 401 && !/\/auth\/(login|register|forgot-password|reset-password)\.php$/.test(window.location.pathname)) {
            Toast.error(json.message || 'Session expired. Please log in again.');
            setTimeout(() => { window.location.href = appBase() + '/auth/login.php'; }, 1200);
        }

        return json;
    }

    return { call, setCsrfToken };
})();

const Toast = (function () {
    function ensureContainer() {
        let el = document.getElementById('toast-container');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toast-container';
            document.body.appendChild(el);
        }
        return el;
    }

    function show(message, type) {
        const container = ensureContainer();
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.textContent = message;
        container.appendChild(el);
        setTimeout(() => el.remove(), 4500);
    }

    return {
        success: (msg) => show(msg, 'success'),
        error: (msg) => show(msg, 'error'),
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    /* ---------------------------------------------------------- sidebar */
    // Below 1024px the sidebar slides in over the content, so it needs a
    // backdrop: without one, tapping the page can't dismiss it and the content
    // underneath stays scrollable and clickable behind the overlay.
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('app-sidebar');

    if (toggle && sidebar) {
        let backdrop = document.querySelector('.sidebar-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'sidebar-backdrop';
            document.body.appendChild(backdrop);
        }

        const setOpen = (open) => {
            sidebar.classList.toggle('open', open);
            backdrop.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
        backdrop.addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { setOpen(false); }
        });
        // Following a link should never leave the overlay covering the new page.
        sidebar.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => setOpen(false)));
    }

    /* ---------------------------------------------------------- theme */
    // The theme lives in data-theme on <html> so CSS custom properties can be
    // swapped wholesale. The key matches the inline snippet that applies the
    // theme pre-paint, and the old key is migrated once so existing users keep
    // the theme they had chosen.
    const THEME_KEY = 'bharatseo-theme';
    const LEGACY_THEME_KEY = 'bharatai_theme';

    const readStored = () => {
        try {
            return localStorage.getItem(THEME_KEY) || localStorage.getItem(LEGACY_THEME_KEY);
        } catch (e) {
            return null;
        }
    };

    const currentTheme = () => {
        const explicit = document.documentElement.getAttribute('data-theme');
        if (explicit) { return explicit; }
        // The app defaults to light, so only an explicit OS dark preference
        // plus no stored choice should start us in dark.
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    };

    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
        document.body.classList.toggle('dark-mode', theme === 'dark');
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) { /* private mode */ }
    };

    const stored = readStored();
    if (stored === 'light' || stored === 'dark') {
        applyTheme(stored);
    } else {
        document.body.classList.toggle('dark-mode', currentTheme() === 'dark');
    }

    document.querySelectorAll('.theme-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        });
    });

    /* ---------------------------------------------------------- tabs */
    // Shared behaviour for every .tabs group, so individual pages don't each
    // reimplement it. Panels are matched by data-tab -> data-panel.
    document.querySelectorAll('.tabs').forEach((group) => {
        group.addEventListener('click', (e) => {
            const btn = e.target.closest('.tab-btn[data-tab]');
            if (!btn) { return; }

            const name = btn.getAttribute('data-tab');
            group.querySelectorAll('.tab-btn').forEach((b) => b.classList.toggle('active', b === btn));

            const scope = group.closest('.page-body') || document;
            scope.querySelectorAll('[data-panel]').forEach((panel) => {
                panel.classList.toggle('active', panel.getAttribute('data-panel') === name);
            });
        });
    });

    /* ---------------------------------------------------------- icons */
    // The icon library is loaded async from a CDN, so it can arrive after this
    // DOMContentLoaded handler has already run. A one-shot check here would
    // silently drop every icon whenever the CDN was slow, so try now and then
    // poll briefly. Icons are decorative and every icon-only control has an
    // aria-label, so giving up after ~10s is safe.
    const buildIcons = () => {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
            return true;
        }
        return false;
    };

    if (!buildIcons()) {
        let tries = 0;
        const timer = setInterval(() => {
            tries += 1;
            if (buildIcons() || tries > 100) {
                clearInterval(timer);
            }
        }, 100);
    }
});
