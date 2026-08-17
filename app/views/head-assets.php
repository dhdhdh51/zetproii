<?php
/**
 * Shared <head> assets for every authenticated screen (/dashboard and /admin).
 *
 * Kept in one file so the font stack, stylesheet and pre-paint theme snippet
 * can never drift between the 34 pages that need them. Include it in place of
 * a bare stylesheet <link>.
 */
?>
<link rel="icon" href="<?= asset('images/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" media="all">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script>
    // Runs before the first paint so a user on the dark theme never sees a
    // flash of the light one. app.js later takes over toggling; this only
    // restores the already-stored preference. The legacy key is still read so
    // a choice made before the BharatSEO rename is honoured.
    (function () {
        try {
            var saved = localStorage.getItem('bharatseo-theme') || localStorage.getItem('bharatai_theme');
            if (saved === 'light' || saved === 'dark') {
                document.documentElement.setAttribute('data-theme', saved);
            }
        } catch (e) { /* private mode: keep the default light theme */ }
    })();
</script>
