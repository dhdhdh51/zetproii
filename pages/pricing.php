<?php
$pageTitle = 'Pricing — BharatSEO';
$pageDescription = 'Simple, transparent pricing in rupees. Start on the free plan and upgrade only when you need more AI credits, users or businesses.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/pricing';

// Real data: plans and their limits come from the database, so this page can
// never advertise a plan or a price the billing system doesn't actually apply.
$plans = Database::fetchAll("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC");
$featuresByPlan = [];
foreach ($plans as $plan) {
    $featuresByPlan[$plan['id']] = Database::fetchAll(
        "SELECT feature_key, feature_value FROM plan_features WHERE plan_id = ?",
        [$plan['id']]
    );
}

$featureLabels = [
    'ai_credits'       => 'AI credits / month',
    'users'            => 'Team users',
    'businesses'       => 'Businesses',
    'documents'        => 'Documents',
    'leads'            => 'Leads',
    'campaigns'        => 'Campaigns',
    'chatbot_sessions' => 'Chatbot sessions / month',
    'storage_mb'       => 'Storage (MB)',
    'api_access'       => 'API access',
];

/** Formats a rupee amount, or the word Free for a zero price. */
$money = static function (float $amount): string {
    return $amount <= 0 ? 'Free' : '₹' . number_format($amount, 0);
};
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
            <span class="eyebrow">Pricing</span>
            <h1>Simple, transparent <span class="highlight">pricing</span></h1>
            <p class="lead">Start free. Upgrade only when you need more AI credits, users or businesses. All prices in Indian rupees, no setup fees.</p>
        </div>
    </section>

    <section class="section" style="padding-top:16px;">
        <div class="container">
            <?php if (count($plans) > 1): ?>
            <div style="text-align:center;">
                <div class="billing-toggle" role="group" aria-label="Billing period">
                    <button type="button" data-cycle="monthly" class="active">Monthly</button>
                    <button type="button" data-cycle="yearly">Yearly <span class="save">save more</span></button>
                </div>
            </div>
            <?php endif; ?>

            <div class="pricing-grid">
                <?php foreach ($plans as $plan): ?>
                <?php
                $isFeatured = $plan['slug'] === 'growth';
                $monthly = (float) $plan['price_monthly'];
                $yearly = (float) ($plan['price_yearly'] ?? 0);
                ?>
                <div class="pricing-card reveal <?= $isFeatured ? 'featured' : '' ?>">
                    <?php if ($isFeatured): ?><span class="badge-popular">Most popular</span><?php endif; ?>

                    <h3><?= View::e($plan['name']) ?></h3>
                    <p><?= View::e((string) $plan['description']) ?></p>

                    <div class="price">
                        <?php /* Both prices are rendered server-side; the toggle only swaps
                                 which one is displayed, so no request is needed. */ ?>
                        <span data-price-monthly="<?= View::e($money($monthly)) ?>"
                              data-price-yearly="<?= View::e($money($yearly)) ?>"><?= View::e($money($monthly)) ?></span>
                        <?php if ($monthly > 0): ?>
                            <span class="period" data-period>/month</span>
                        <?php endif; ?>
                    </div>

                    <ul class="check-list">
                        <?php foreach ($featuresByPlan[$plan['id']] as $f): ?>
                        <li>
                            <i data-lucide="check"></i>
                            <span><?= View::e($featureLabels[$f['feature_key']] ?? $f['feature_key']) ?>:
                                <strong><?= $f['feature_value'] === 'unlimited' ? 'Unlimited' : View::e($f['feature_value']) ?></strong>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <a href="<?= url('auth/register.php') ?>?plan=<?= View::e($plan['slug']) ?>"
                       class="btn btn-block <?= $isFeatured ? 'btn-primary' : 'btn-ghost' ?>">
                        <?= $monthly <= 0 ? 'Start free' : 'Choose ' . View::e($plan['name']) ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <p style="text-align:center;margin-top:30px;color:var(--text-faint);font-size:0.88rem;">
                You connect your own AI provider key, so AI credits cover platform usage — you're never marked up on tokens.
            </p>
        </div>
    </section>

    <section class="section section-alt">
        <div class="container" style="max-width:800px;">
            <div class="section-head reveal">
                <span class="eyebrow">FAQ</span>
                <h2>Pricing questions</h2>
            </div>
            <details class="faq-item reveal">
                <summary>Can I change plans later?</summary>
                <div>Yes — upgrade or downgrade at any time from Billing settings. Usage limits are enforced server-side and take effect immediately after the change.</div>
            </details>
            <details class="faq-item reveal">
                <summary>What happens if I hit a limit?</summary>
                <div>You're warned in-app as you approach it. Once a limit is reached, only that specific feature pauses (for example AI generation) — the rest of your workspace keeps working, and nothing is deleted.</div>
            </details>
            <details class="faq-item reveal">
                <summary>Do I pay for AI usage separately?</summary>
                <div>You bring your own OpenAI, Gemini or Claude key, so your model usage is billed by that provider at their rates. BharatSEO doesn't resell tokens or add a markup.</div>
            </details>
            <details class="faq-item reveal">
                <summary>Do you offer refunds?</summary>
                <div>See our <a href="<?= url('refund-policy') ?>">refund policy</a> for the full terms.</div>
            </details>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="cta-block reveal">
                <h2>Try it on the free plan first</h2>
                <p>Create a workspace, connect your AI key, and publish something. Upgrade only if it earns it.</p>
                <a href="<?= url('auth/register.php') ?>" class="btn btn-primary btn-lg"><i data-lucide="rocket"></i> Start free</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
