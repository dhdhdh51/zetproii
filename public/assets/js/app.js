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

// ---- Sidebar toggle (mobile) ----
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('app-sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // ---- Theme toggle ----
    const THEME_KEY = 'bharatai_theme';
    function applyTheme(theme) {
        document.body.classList.toggle('dark-mode', theme === 'dark');
    }
    const saved = localStorage.getItem(THEME_KEY) ||
        (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(saved);
    document.querySelectorAll('.theme-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const current = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            localStorage.setItem(THEME_KEY, next);
        });
    });

    if (window.lucide) {
        lucide.createIcons();
    }
});
