<?php
/**
 * Blog listing page. Reads real posts from seo_content where it has been
 * published as a blog article (status='final') for the platform's own
 * marketing business, if one is configured. Shows a clean empty state
 * rather than fabricated posts when none exist yet.
 */
$pageTitle = 'Blog — BharatSEO';
$pageDescription = 'Insights on AI automation, CRM, and growing your small business with BharatSEO.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/blog';

$posts = [];
try {
    $posts = Database::fetchAll(
        "SELECT title, slug, meta_description, created_at FROM seo_content
         WHERE status = 'final' ORDER BY created_at DESC LIMIT 20"
    );
} catch (\Throwable $e) {
    $posts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>

<main>
    <section class="hero" style="padding-bottom:24px;">
        <div class="aurora" aria-hidden="true"></div>
        <div class="grid-overlay" aria-hidden="true"></div>
        <div class="container">
            <span class="eyebrow">Blog</span>
            <h1>The <span class="highlight">BharatSEO</span> blog</h1>
            <p class="lead">Practical guidance on ranking, AI content and turning search traffic into customers.</p>
        </div>
    </section>

    <section class="section" style="padding-top:16px;">
        <div class="container">
            <?php if (empty($posts)): ?>
                <div class="placeholder-note">
                    No articles have been published yet. Check back soon — new posts will appear here automatically as they're published.
                </div>
            <?php else: ?>
                <div class="grid grid-3">
                    <?php foreach ($posts as $post): ?>
                    <div class="card reveal">
                        <h3><?= View::e($post['title']) ?></h3>
                        <p><?= View::e($post['meta_description'] ?? '') ?></p>
                        <p style="font-size:13px;margin-top:10px;">
                            <a href="<?= url('blog') ?>/<?= View::e($post['slug']) ?>">Read more &rarr;</a>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
