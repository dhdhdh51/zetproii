/**
 * BharatSEO marketing site — progressive enhancement only.
 *
 * Nothing here is required for the page to be readable or navigable: the theme
 * is already applied by the inline snippet in <head>, the FAQ uses native
 * <details>, and reveal animations are opt-in via a class this file adds.
 */
(function () {
    'use strict';

    var base = (typeof window.__BASE__ === 'string') ? window.__BASE__ : '';

    /* ---------------------------------------------------------------- theme */
    // Must match the key used by the inline snippet in partials/head.php.
    var THEME_KEY = 'bharatseo-theme';
    var LEGACY_THEME_KEY = 'bharatai_theme';

    function currentTheme() {
        var explicit = document.documentElement.getAttribute('data-theme');
        if (explicit) { return explicit; }
        // No explicit choice yet, so we're following the OS preference.
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'dark';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        // Keep the legacy body class in sync: older cached pages style off it.
        document.body.classList.toggle('dark-mode', theme === 'dark');
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) { /* private mode */ }
    }

    // One-time migration so anyone who picked a theme on the old site keeps it.
    try {
        if (!localStorage.getItem(THEME_KEY)) {
            var legacy = localStorage.getItem(LEGACY_THEME_KEY);
            if (legacy === 'light' || legacy === 'dark') {
                localStorage.setItem(THEME_KEY, legacy);
                document.documentElement.setAttribute('data-theme', legacy);
            }
        }
    } catch (e) { /* ignore */ }

    document.body.classList.toggle('dark-mode', currentTheme() === 'dark');

    Array.prototype.forEach.call(document.querySelectorAll('.theme-toggle'), function (btn) {
        btn.addEventListener('click', function () {
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        });
    });

    /* ---------------------------------------------------------------- mobile nav */
    var navToggle = document.getElementById('mobile-toggle');
    var mobileNav = document.getElementById('mobile-nav');
    if (navToggle && mobileNav) {
        navToggle.addEventListener('click', function () {
            var isOpen = mobileNav.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        // Close on navigation so the menu doesn't cover the page it just opened.
        Array.prototype.forEach.call(mobileNav.querySelectorAll('a'), function (a) {
            a.addEventListener('click', function () {
                mobileNav.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    /* ---------------------------------------------------------------- sticky header */
    var header = document.getElementById('site-header');
    if (header) {
        var onScroll = function () {
            header.classList.toggle('scrolled', window.scrollY > 8);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ---------------------------------------------------------------- scroll reveal */
    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var revealables = document.querySelectorAll('.reveal');

    if (revealables.length) {
        if (prefersReduced || !('IntersectionObserver' in window)) {
            // Can't (or shouldn't) animate: leave everything visible. The CSS
            // only hides .reveal under .reveal-ready, which we never add here.
            Array.prototype.forEach.call(revealables, function (el) { el.classList.add('in'); });
        } else {
            // Only now is it safe to hide them, because we know we can reveal them.
            document.documentElement.classList.add('reveal-ready');

            var revealAll = function () {
                Array.prototype.forEach.call(revealables, function (el) { el.classList.add('in'); });
            };

            // FAILSAFE: content must never be able to get stuck invisible.
            // A reveal-on-scroll effect that starts at opacity 0 is a liability -
            // anything that stops the observer from firing (a capture of the full
            // page, print, an unusual scroll container, a browser quirk) leaves
            // real copy unreadable. So everything is revealed unconditionally
            // shortly after load; the animation is only ever a head start.
            var failsafe = window.setTimeout(revealAll, 1400);

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) { return; }
                    entry.target.classList.add('in');
                    observer.unobserve(entry.target);
                });
            }, {
                // Generous bottom margin so anything just past the fold animates
                // in before the user reaches it, rather than popping in late.
                rootMargin: '0px 0px 20% 0px',
                threshold: 0.01
            });

            Array.prototype.forEach.call(revealables, function (el, i) {
                // Stagger siblings slightly so grids cascade instead of popping.
                el.style.transitionDelay = ((i % 4) * 70) + 'ms';
                observer.observe(el);
            });

            // Printing must show everything, whatever the scroll position was.
            if (window.matchMedia) {
                var printQuery = window.matchMedia('print');
                if (printQuery.addEventListener) {
                    printQuery.addEventListener('change', function (e) { if (e.matches) { revealAll(); } });
                }
            }
            window.addEventListener('beforeprint', revealAll);
            // Nothing left to time out for once the page is fully loaded and idle.
            window.addEventListener('pagehide', function () { window.clearTimeout(failsafe); });
        }
    }

    /* ---------------------------------------------------------------- count up */
    // Animates [data-count] numbers once, when first scrolled into view. The
    // final value is already in the HTML, so it is correct without JS.
    var counters = document.querySelectorAll('[data-count]');
    if (counters.length && !prefersReduced && 'IntersectionObserver' in window) {
        var countObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                var el = entry.target;
                countObserver.unobserve(el);

                var target = parseInt(el.getAttribute('data-count'), 10);
                if (isNaN(target)) { return; }

                var duration = 900;
                var started = null;
                var tick = function (now) {
                    if (started === null) { started = now; }
                    var progress = Math.min((now - started) / duration, 1);
                    // easeOutCubic
                    var eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = String(Math.round(target * eased));
                    if (progress < 1) { requestAnimationFrame(tick); }
                };
                requestAnimationFrame(tick);
            });
        }, { threshold: 0.5 });

        Array.prototype.forEach.call(counters, function (el) { countObserver.observe(el); });
    }

    /* ---------------------------------------------------------------- billing toggle */
    // Pricing page: swaps the displayed price between monthly and yearly. Both
    // values are rendered server-side, so this only changes which one is shown.
    var billingToggle = document.querySelector('.billing-toggle');
    if (billingToggle) {
        billingToggle.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-cycle]');
            if (!btn) { return; }

            var cycle = btn.getAttribute('data-cycle');
            Array.prototype.forEach.call(billingToggle.querySelectorAll('button'), function (b) {
                b.classList.toggle('active', b === btn);
            });
            Array.prototype.forEach.call(document.querySelectorAll('[data-price-monthly]'), function (el) {
                el.textContent = el.getAttribute(cycle === 'yearly' ? 'data-price-yearly' : 'data-price-monthly');
            });
            Array.prototype.forEach.call(document.querySelectorAll('[data-period]'), function (el) {
                el.textContent = cycle === 'yearly' ? '/year' : '/month';
            });
        });
    }

    /* ---------------------------------------------------------------- icons */
    // Replaces every <i data-lucide="name"> with its SVG. This file is deferred,
    // so the icon library has already executed by the time we get here.
    // Re-checked on DOMContentLoaded too, in case this script somehow runs first.
    function buildIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
            return true;
        }
        return false;
    }

    if (!buildIcons()) {
        document.addEventListener('DOMContentLoaded', buildIcons);
        // The library is loaded from a CDN; if it is merely slow rather than
        // blocked, catch it on window load as a last attempt.
        window.addEventListener('load', buildIcons);
    }

    /* ---------------------------------------------------------------- contact form */
    var contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var msgBox = document.getElementById('contact-form-msg');
            var submitBtn = contactForm.querySelector('button[type="submit"]');
            var originalLabel = submitBtn ? submitBtn.textContent : '';

            var payload = {
                name: contactForm.name.value,
                email: contactForm.email.value,
                phone: contactForm.phone ? contactForm.phone.value : '',
                subject: contactForm.subject.value,
                message: contactForm.message.value
            };

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Sending...';
            }

            fetch(base + '/api/business/contact-message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    msgBox.className = 'form-msg show ' + (json.success ? 'success' : 'error');
                    msgBox.textContent = json.message || (json.success ? 'Message sent.' : 'Something went wrong.');
                    if (json.success) { contactForm.reset(); }
                })
                .catch(function () {
                    msgBox.className = 'form-msg show error';
                    msgBox.textContent = 'Network error. Please try again.';
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalLabel;
                    }
                });
        });
    }
})();
