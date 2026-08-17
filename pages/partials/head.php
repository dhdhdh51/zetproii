<?php
/** @var string $pageTitle */
/** @var string $pageDescription */
/** @var string $canonicalUrl */
$appUrl = rtrim((string) config('app.url'), '/');
$appName = (string) config('app.name', 'BharatSEO');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= View::e($pageTitle) ?></title>
<meta name="description" content="<?= View::e($pageDescription) ?>">
<link rel="canonical" href="<?= View::e($canonicalUrl) ?>">
<meta name="theme-color" content="#06060c">

<meta property="og:title" content="<?= View::e($pageTitle) ?>">
<meta property="og:description" content="<?= View::e($pageDescription) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= View::e($canonicalUrl) ?>">
<meta property="og:site_name" content="<?= View::e($appName) ?>">
<meta property="og:image" content="<?= View::e($appUrl) ?>/public/assets/images/og-cover.svg">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= View::e($pageTitle) ?>">
<meta name="twitter:description" content="<?= View::e($pageDescription) ?>">
<meta name="twitter:image" content="<?= View::e($appUrl) ?>/public/assets/images/og-cover.svg">

<link rel="icon" href="<?= asset('images/favicon.svg') ?>">

<?php /* Preconnect first so the font request starts as early as possible. The
         font is a progressive enhancement: the stylesheet declares a system
         fallback stack, so the site is fully readable if it never arrives. */ ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" media="all">

<link rel="stylesheet" href="<?= asset('css/marketing.css') ?>">

<script>
    window.__BASE__ = <?= json_encode(Url::basePath()) ?>;

    // Applied before the first paint so a visitor who chose light mode never
    // sees a flash of the dark theme (and vice versa). Kept inline and tiny
    // for that reason - moving it to an external file would reintroduce the flash.
    (function () {
        try {
            var saved = localStorage.getItem('bharatseo-theme');
            if (saved === 'light' || saved === 'dark') {
                document.documentElement.setAttribute('data-theme', saved);
            }
        } catch (e) { /* private mode: fall through to the OS preference */ }
    })();
</script>

<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" defer></script>
