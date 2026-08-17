<?php
/** @var string $pageTitle */
/** @var string $pageDescription */
/** @var string $canonicalUrl */
$appUrl = rtrim((string) config('app.url'), '/');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= View::e($pageTitle) ?></title>
<meta name="description" content="<?= View::e($pageDescription) ?>">
<link rel="canonical" href="<?= View::e($canonicalUrl) ?>">

<meta property="og:title" content="<?= View::e($pageTitle) ?>">
<meta property="og:description" content="<?= View::e($pageDescription) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= View::e($canonicalUrl) ?>">
<meta property="og:site_name" content="BharatAI Business OS">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= View::e($pageTitle) ?>">
<meta name="twitter:description" content="<?= View::e($pageDescription) ?>">

<link rel="icon" href="<?= asset('images/favicon.svg') ?>">
<link rel="stylesheet" href="<?= asset('css/marketing.css') ?>">
<script>window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
