<?php
/**
 * Shared <head> for every /auth/*.php screen.
 *
 * WHY THIS EXISTS: each auth page previously carried its own copy of this
 * markup, and none of them defined window.__BASE__ — even though their inline
 * scripts built request URLs with it. That produced the literal string
 * "undefined/api/auth/register.php", which the browser resolved against the
 * current directory as /auth/undefined/api/auth/register.php: a 404. Sign-up,
 * forgot-password and reset-password all failed with a generic
 * "Something went wrong" no matter what the user typed.
 *
 * Defining it in one shared place means a page can no longer be added that
 * uses the base path without also publishing it.
 *
 * @var string $pageTitle  Set by the including page before the include.
 */
$pageTitle = $pageTitle ?? 'Account';
$appName = (string) config('app.name', 'BharatSEO');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= View::e($pageTitle) ?> — <?= View::e($appName) ?></title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#f6f7fb">

<link rel="icon" href="<?= asset('images/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" media="all">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">

<script>
    // Published for the inline scripts on these pages, which use it to build
    // API URLs that must keep working in a subfolder install.
    window.__BASE__ = <?= json_encode(Url::basePath()) ?>;

    // Applied before first paint so the chosen theme never flashes.
    (function () {
        try {
            var saved = localStorage.getItem('bharatseo-theme') || localStorage.getItem('bharatai_theme');
            if (saved === 'light' || saved === 'dark') {
                document.documentElement.setAttribute('data-theme', saved);
            }
        } catch (e) { /* private mode: fall back to the default theme */ }
    })();
</script>

<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" async></script>
