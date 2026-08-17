<?php
$pageTitle = 'Page not found — BharatSEO';
$pageDescription = 'The page you are looking for could not be found.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/404';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>

<main>
    <section class="hero">
        <div class="aurora" aria-hidden="true"></div>
        <div class="grid-overlay" aria-hidden="true"></div>
        <div class="container">
            <div class="error-code">404</div>
            <h1>This page doesn't exist</h1>
            <p class="lead" style="margin-left:auto;margin-right:auto;">The link may be out of date, or the address might have a typo. Here's the way back.</p>
            <div class="hero-actions">
                <a href="<?= url() ?>" class="btn btn-primary btn-lg"><i data-lucide="home"></i> Go to homepage</a>
                <a href="<?= url('features') ?>" class="btn btn-ghost btn-lg">Browse features</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
