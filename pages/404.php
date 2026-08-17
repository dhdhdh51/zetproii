<?php
$pageTitle = 'Page Not Found — BharatAI Business OS';
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
    <section style="text-align:center;padding:100px 20px;">
        <h1 style="font-size:64px;margin-bottom:8px;">404</h1>
        <p class="lead">This page doesn't exist. Let's get you back on track.</p>
        <a href="<?= url() ?>" class="btn btn-primary">Go Home</a>
    </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
