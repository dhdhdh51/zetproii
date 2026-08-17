<?php
$pageTitle = 'Features — BharatSEO';
$pageDescription = 'Every BharatSEO feature: AI SEO content, an assistant trained on your business, a website chatbot, CRM with AI lead scoring, proposals and invoicing, automation, analytics and agency mode.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/features';

$featureGroups = [
    [
        'title' => 'AI SEO & content engine',
        'icon'  => 'pen-tool',
        'blurb' => 'Turn a target keyword into publishable, on-brand content.',
        'items' => [
            'SEO titles, meta descriptions and outlines',
            'Long-form articles from a target keyword',
            'Social posts for Instagram, LinkedIn, Facebook and X',
            'Professional replies to Google reviews',
        ],
    ],
    [
        'title' => 'AI assistant, grounded in your business',
        'icon'  => 'bot',
        'blurb' => 'Answers from your own documents, not the open internet.',
        'items' => [
            'Reads your knowledge base: services, pricing, FAQs, documents',
            'Drafts replies, summaries and reports',
            'Works with OpenAI, Gemini, Anthropic or any compatible API',
            'Automatic fallback to your next provider if one fails',
        ],
    ],
    [
        'title' => 'Website chatbot',
        'icon'  => 'message-circle',
        'blurb' => 'One snippet, and your site answers questions all night.',
        'items' => [
            'Embeddable widget for any website',
            'Custom branding, tone and welcome message',
            'Captures name, email and phone into your CRM',
            'Hands off to a human when it should',
        ],
    ],
    [
        'title' => 'CRM built for follow-up',
        'icon'  => 'target',
        'blurb' => 'Every enquiry gets an owner, a status and a next action.',
        'items' => [
            'Lead pipeline with custom statuses',
            'Tags, notes and a full activity timeline',
            'AI lead scoring with a recommended next step',
            'Follow-up scheduling and reminders',
        ],
    ],
    [
        'title' => 'Documents & billing',
        'icon'  => 'file-text',
        'blurb' => 'Quote, contract and invoice without leaving the app.',
        'items' => [
            'AI-drafted proposals and quotations',
            'Automatic totals with tax and per-line discounts',
            'Invoices with payment status tracking',
            'Reusable templates with placeholders',
        ],
    ],
    [
        'title' => 'Automation',
        'icon'  => 'workflow',
        'blurb' => 'The follow-ups that get forgotten, handled for you.',
        'items' => [
            'Trigger-based rules on leads and customers',
            'Welcome emails, follow-up tasks, team notifications',
            'Scheduled execution via cron',
            'Full execution logs for every run',
        ],
    ],
    [
        'title' => 'Analytics & reporting',
        'icon'  => 'bar-chart-3',
        'blurb' => 'What came in, what converted, what it cost.',
        'items' => [
            'Lead, conversion and revenue charts',
            'AI usage and cost tracking per provider',
            'Team activity feed',
            'Exportable reports',
        ],
    ],
    [
        'title' => 'Agency mode',
        'icon'  => 'building-2',
        'blurb' => 'Run every client from a single login.',
        'items' => [
            'Multiple client businesses per account',
            'Switch between businesses instantly',
            'Per-client usage and billing visibility',
            'Data isolated per client at the database level',
        ],
    ],
    [
        'title' => 'Security & administration',
        'icon'  => 'shield-check',
        'blurb' => 'Controls you would expect from a much larger product.',
        'items' => [
            'Role-based permissions for staff',
            'Audit log of every sensitive action',
            'API keys and outbound webhooks',
            'Admin panel for platform-wide control',
        ],
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
    <section class="hero" style="padding-bottom:24px;">
        <div class="aurora" aria-hidden="true"></div>
        <div class="grid-overlay" aria-hidden="true"></div>
        <div class="container">
            <span class="eyebrow">Features</span>
            <h1>Everything you need to <span class="highlight">get found and follow up</span></h1>
            <p class="lead">Every capability listed here is implemented and working in the product — nothing on this page is a mockup or a roadmap item.</p>
            <div class="hero-actions">
                <a href="<?= url('auth/register.php') ?>" class="btn btn-primary btn-lg"><i data-lucide="rocket"></i> Start free</a>
                <a href="<?= url('pricing') ?>" class="btn btn-ghost btn-lg">See pricing</a>
            </div>
        </div>
    </section>

    <section class="section" style="padding-top:16px;">
        <div class="container">
            <div class="grid grid-3">
                <?php foreach ($featureGroups as $group): ?>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="<?= View::e($group['icon']) ?>"></i></div>
                    <h3><?= View::e($group['title']) ?></h3>
                    <p><?= View::e($group['blurb']) ?></p>
                    <ul class="check-list">
                        <?php foreach ($group['items'] as $item): ?>
                        <li><i data-lucide="check"></i><span><?= View::e($item) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section section-alt">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Under the hood</span>
                <h2>Boring where it counts</h2>
                <p>Deliberately conventional technology, so it runs anywhere and stays cheap to host.</p>
            </div>
            <div class="grid grid-4">
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="server"></i></div>
                    <h3>Runs on plain hosting</h3>
                    <p>PHP 8.2+ and MySQL 8. No Node.js or Composer needed at runtime, so ordinary cPanel shared hosting is enough.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="lock"></i></div>
                    <h3>Encrypted secrets</h3>
                    <p>Provider API keys and SMTP passwords are encrypted at rest and never sent to the browser.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="split"></i></div>
                    <h3>Provider fallback</h3>
                    <p>Order your AI providers by priority; a timeout or outage moves to the next automatically.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="languages"></i></div>
                    <h3>English + हिन्दी</h3>
                    <p>The interface ships in both, and AI output can be generated in either.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="cta-block reveal">
                <h2>See it on your own business</h2>
                <p>Create a free workspace and try the features against your real services and customers.</p>
                <a href="<?= url('auth/register.php') ?>" class="btn btn-primary btn-lg"><i data-lucide="rocket"></i> Start free</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
