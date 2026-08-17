<?php
$pageTitle = 'Pricing — BharatAI Business OS';
$pageDescription = 'Simple, transparent pricing for BharatAI Business OS. Free plan available. Upgrade as you grow.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/pricing';

// Real data: pull live plans + features from the database, not hardcoded.
$plans = Database::fetchAll("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC");
$featuresByPlan = [];
foreach ($plans as $plan) {
    $featuresByPlan[$plan['id']] = Database::fetchAll(
        "SELECT feature_key, feature_value FROM plan_features WHERE plan_id = ?",
        [$plan['id']]
    );
}

$featureLabels = [
    'ai_credits' => 'AI credits / month',
    'users' => 'Team users',
    'businesses' => 'Businesses',
    'documents' => 'Documents',
    'leads' => 'Leads',
    'campaigns' => 'Campaigns',
    'chatbot_sessions' => 'Chatbot sessions / month',
    'storage_mb' => 'Storage (MB)',
    'api_access' => 'API access',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>

<main>
    <section class="hero" style="padding-bottom:20px;">
        <div class="container">
            <h1>Simple, transparent <span class="highlight">pricing</span></h1>
            <p class="lead">Start free. Upgrade only when you need more AI credits, users or businesses.</p>
        </div>
    </section>

    <section>
        <div class="container">
            <div class="grid grid-4">
                <?php foreach ($plans as $i => $plan): ?>
                <div class="card pricing-card <?= $plan['slug'] === 'growth' ? 'featured' : '' ?>">
                    <?php if ($plan['slug'] === 'growth'): ?><span class="badge-popular">Most Popular</span><?php endif; ?>
                    <h3><?= View::e($plan['name']) ?></h3>
                    <div class="price">
                        <?php if ((float) $plan['price_monthly'] == 0): ?>
                            Free
                        <?php else: ?>
                            &#8377;<?= number_format((float) $plan['price_monthly']) ?><span>/mo</span>
                        <?php endif; ?>
                    </div>
                    <p style="color:var(--color-text-muted);font-size:14px;"><?= View::e($plan['description']) ?></p>
                    <ul>
                        <?php foreach ($featuresByPlan[$plan['id']] as $f): ?>
                            <li>
                                <i data-lucide="check" style="width:16px;height:16px;color:var(--color-primary);flex-shrink:0;"></i>
                                <span><?= View::e($featureLabels[$f['feature_key']] ?? $f['feature_key']) ?>:
                                <strong><?= $f['feature_value'] === 'unlimited' ? 'Unlimited' : View::e($f['feature_value']) ?></strong></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="/auth/register.php?plan=<?= View::e($plan['slug']) ?>" class="btn btn-block <?= $plan['slug'] === 'growth' ? 'btn-primary' : 'btn-ghost' ?>">
                        <?= (float) $plan['price_monthly'] == 0 ? 'Start Free' : 'Choose ' . View::e($plan['name']) ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section-alt">
        <div class="container prose" style="max-width:760px;">
            <div class="section-head"><h2>Pricing FAQ</h2></div>
            <details class="faq-item"><summary>Can I change plans later? <i data-lucide="chevron-down"></i></summary><p>Yes, upgrade or downgrade anytime from Billing settings. Usage limits are enforced server-side immediately after a plan change.</p></details>
            <details class="faq-item"><summary>What happens if I exceed my limits? <i data-lucide="chevron-down"></i></summary><p>You'll be notified in-app and asked to upgrade before further usage of that specific feature (e.g. AI credits) is blocked.</p></details>
            <details class="faq-item"><summary>Do you offer refunds? <i data-lucide="chevron-down"></i></summary><p>See our <a href="/refund-policy">Refund Policy</a> for full details.</p></details>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
