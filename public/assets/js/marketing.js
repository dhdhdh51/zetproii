/**
 * Marketing site vanilla JS: mobile nav toggle, dark mode toggle
 * (persisted in localStorage), and contact form submission via fetch.
 */
(function () {
    'use strict';

    // ---- Mobile nav ----
    var toggle = document.getElementById('mobile-toggle');
    var mobileNav = document.getElementById('mobile-nav');
    if (toggle && mobileNav) {
        toggle.addEventListener('click', function () {
            var isOpen = mobileNav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    // ---- Dark mode ----
    var THEME_KEY = 'bharatai_theme';
    function applyTheme(theme) {
        document.body.classList.toggle('dark-mode', theme === 'dark');
    }
    var savedTheme = localStorage.getItem(THEME_KEY) ||
        (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(savedTheme);

    document.querySelectorAll('.theme-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var current = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            localStorage.setItem(THEME_KEY, next);
        });
    });

    // ---- Contact form ----
    var contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            var msgBox = document.getElementById('contact-form-msg');
            var submitBtn = contactForm.querySelector('button[type="submit"]');
            var payload = {
                name: contactForm.name.value,
                email: contactForm.email.value,
                phone: contactForm.phone ? contactForm.phone.value : '',
                subject: contactForm.subject.value,
                message: contactForm.message.value,
            };

            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';

            try {
                var res = await fetch('/api/business/contact-message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                var json = await res.json();
                msgBox.className = 'form-msg ' + (json.success ? 'success' : 'error');
                msgBox.textContent = json.message || (json.success ? 'Message sent!' : 'Something went wrong.');
                if (json.success) {
                    contactForm.reset();
                }
            } catch (err) {
                msgBox.className = 'form-msg error';
                msgBox.textContent = 'Network error. Please try again.';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Message';
            }
        });
    }
})();
