<?php
$pageTitle = 'Features — BharatAI Business OS';
$pageDescription = 'Explore every feature of BharatAI Business OS: CRM, AI assistant, chatbot, automation, proposals, quotations, invoicing, analytics and more.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/features';

$featureGroups = [
    [
        'title' => 'CRM & Lead Management',
        'icon' => 'users',
        'items' => ['Full lead pipeline with custom statuses', 'Tags, notes, activity timeline', 'AI lead qualification & scoring', 'Follow-up scheduling & reminders'],
    ],
    [
        'title' => 'AI Business Assistant',
        'icon' => 'bot',
        'items' => ['Understands your knowledge base', 'Draft replies, summaries & reports', 'Multi-provider: OpenAI, Gemini, Anthropic, custom', 'Automatic fallback if a provider fails'],
    ],
    [
        'title' => 'AI Website Chatbot',
        'icon' => 'message-circle',
        'items' => ['Embeddable JS widget for any website', 'Custom branding, tone & welcome message', 'Automatic lead capture', 'Human handoff support'],
    ],
    [
        'title' => 'Documents & Billing',
        'icon' => 'file-text',
        'items' => ['AI-generated proposals & quotations', 'Auto-calculated totals, tax & discounts', 'Invoices with payment tracking', 'Reusable templates with placeholders'],
    ],
    [
        'title' => 'Automation',
        'icon' => 'workflow',
        'items' => ['Trigger-based automation rules', 'Welcome emails, follow-up tasks, notifications', 'Cron-based scheduled execution', 'Full execution logs'],
    ],
    [
        'title' => 'Analytics & Reporting',
        'icon' => 'bar-chart-3',
        'items' => ['Leads, conversion & revenue charts', 'AI usage & cost tracking', 'Team activity feed', 'Exportable reports'],
    ],
    [
        'title' => 'Agency Mode',
        'icon' => 'building-2',
        'items' => ['Manage multiple client businesses', 'Switch businesses instantly', 'Per-client usage & billing visibility', 'Isolated data per client'],
    ],
    [
        'title' => 'Security & Admin',
        'icon' => 'shield-check',
        'items' => ['Role-based permissions', 'Full audit logging', 'API keys & webhooks', 'Admin panel for platform control'],
    ],
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
            <h1>Everything you need to <span class="highlight">run your business</span></h1>
            <p class="lead">Every feature below is fully functional — not a mockup.</p>
        </div>
    </section>

    <section>
        <div class="container">
            <div class="grid grid-3">
                <?php foreach ($featureGroups as $group): ?>
                <div class="card">
                    <div class="icon-wrap"><i data-lucide="<?= View::e($group['icon']) ?>"></i></div>
                    <h3><?= View::e($group['title']) ?></h3>
                    <ul style="list-style:none;padding:0;margin:10px 0 0;">
                        <?php foreach ($group['items'] as $item): ?>
                        <li style="display:flex;gap:8px;padding:5px 0;font-size:14px;color:var(--color-text-muted);">
                            <i data-lucide="check" style="width:15px;height:15px;color:var(--color-primary);flex-shrink:0;margin-top:2px;"></i>
                            <span><?= View::e($item) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section-alt">
        <div class="container">
            <div class="cta-block">
                <h2>See it in action</h2>
                <p>Create your free account and explore every feature yourself.</p>
                <a href="<?= url('auth/register.php') ?>" class="btn btn-primary btn-lg">Start Free</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
