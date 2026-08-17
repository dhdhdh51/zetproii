<?php
$pageTitle = 'BharatSEO — AI SEO & Growth Workspace for Indian Businesses';
$pageDescription = 'Rank higher, publish faster, and never lose a lead. BharatSEO combines AI SEO content, an AI assistant trained on your business, a website chatbot, CRM, proposals and invoicing in one workspace.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/';
$appUrl = rtrim((string) config('app.url'), '/');

// Pricing preview is driven by the real plans table, so the homepage can never
// advertise a price the billing system doesn't actually charge.
$homePlans = [];
try {
    $homePlans = Database::fetchAll(
        "SELECT name, slug, description, price_monthly, currency
         FROM plans WHERE is_active = 1 ORDER BY sort_order ASC, price_monthly ASC LIMIT 3"
    );
} catch (\Throwable $e) {
    $homePlans = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "BharatSEO",
  "applicationCategory": "BusinessApplication",
  "operatingSystem": "Web",
  "description": <?= json_encode($pageDescription) ?>,
  "url": <?= json_encode($appUrl . '/') ?>,
  "offers": { "@type": "Offer", "price": "0", "priceCurrency": "INR" }
}
</script>
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>

<main>
    <!-- ============================================================ hero -->
    <section class="hero">
        <div class="aurora" aria-hidden="true"></div>
        <div class="grid-overlay" aria-hidden="true"></div>
        <div class="container">
            <div class="hero-badge">
                <span class="tag">New</span>
                <span>Bring your own AI key — <b>OpenAI, Gemini or Claude</b></span>
            </div>

            <h1>Get found on Google.<br>Turn that traffic into <span class="highlight">paying customers</span>.</h1>

            <p class="lead">BharatSEO is the AI SEO and growth workspace for Indian businesses. Research keywords, publish optimised content, answer visitors with an AI chatbot, and follow up every lead — without stitching together six different tools.</p>

            <div class="hero-actions">
                <a href="<?= url('auth/register.php') ?>" class="btn btn-primary btn-lg"><i data-lucide="rocket"></i> Start free</a>
                <a href="<?= url('features') ?>" class="btn btn-ghost btn-lg"><i data-lucide="layout-grid"></i> Explore features</a>
            </div>
            <p class="hero-note">Free plan available · No credit card required · Set up in minutes</p>

            <!-- A representative view of the dashboard, built from live markup
                 rather than a screenshot so it can never look out of date. -->
            <div class="hero-frame reveal" aria-hidden="true">
                <div class="frame-bar">
                    <span></span><span></span><span></span>
                    <span class="frame-url">app.bharatseo.site/dashboard</span>
                </div>
                <div class="frame-body">
                    <div class="metric">
                        <span class="metric-label">Keywords tracked</span>
                        <div class="metric-value">248</div>
                        <span class="metric-delta">▲ 32 this month</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Leads captured</span>
                        <div class="metric-value">1,406</div>
                        <span class="metric-delta">▲ 18.4%</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Avg. response time</span>
                        <div class="metric-value">2m 10s</div>
                        <span class="metric-delta">▼ 41% faster</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================ integrations -->
    <div class="marquee" aria-label="Works with">
        <div class="marquee-track">
            <?php
            // Duplicated once so the CSS translateX(-50%) loop is seamless.
            $integrations = [
                ['sparkles', 'OpenAI'], ['gem', 'Google Gemini'], ['brain', 'Anthropic Claude'],
                ['plug', 'Any OpenAI-compatible API'], ['credit-card', 'Razorpay'],
                ['credit-card', 'Stripe'], ['credit-card', 'Cashfree'], ['mail', 'SMTP email'],
                ['database', 'MySQL 8'], ['server', 'cPanel / Apache'],
            ];
            for ($pass = 0; $pass < 2; $pass++) {
                foreach ($integrations as [$icon, $label]) {
                    echo '<span class="marquee-item"><i data-lucide="' . View::e($icon) . '"></i>' . View::e($label) . '</span>';
                }
            }
            ?>
        </div>
    </div>

    <!-- ============================================================ problem -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">The problem</span>
                <h2>Being invisible on Google is expensive</h2>
                <p>Most small businesses in India lose customers twice: once because nobody finds them, and again because the few enquiries they do get are never followed up.</p>
            </div>
            <div class="grid grid-3">
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="search-x"></i></div>
                    <h3>Nobody finds you</h3>
                    <p>Your competitors publish weekly and rank for the searches your customers actually make. You publish when there's time — so, rarely.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="clock-alert"></i></div>
                    <h3>Enquiries go cold</h3>
                    <p>A visitor asks a question at 10pm. Nobody replies until the next afternoon. By then they've already called someone else.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="puzzle"></i></div>
                    <h3>Six disconnected tools</h3>
                    <p>SEO in one tab, content in another, leads in a spreadsheet, invoices in Word. Nothing talks to anything, so nothing gets measured.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================ product facts -->
    <section class="section section-alt">
        <div class="container">
            <div class="stat-strip reveal">
                <div class="stat">
                    <div class="stat-value" data-count="4">4</div>
                    <p class="stat-label">AI providers supported</p>
                </div>
                <div class="stat">
                    <div class="stat-value" data-count="19">19</div>
                    <p class="stat-label">Modules in the workspace</p>
                </div>
                <div class="stat">
                    <div class="stat-value" data-count="2">2</div>
                    <p class="stat-label">Languages: English + हिन्दी</p>
                </div>
                <div class="stat">
                    <div class="stat-value">100%</div>
                    <p class="stat-label">Data isolated per business</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================ features / bento -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">The workspace</span>
                <h2>Everything you need to grow, in one place</h2>
                <p>Search visibility, content production and customer follow-up are the same job. BharatSEO treats them that way.</p>
            </div>

            <div class="bento">
                <div class="card bento-wide reveal">
                    <div class="icon-wrap"><i data-lucide="pen-tool"></i></div>
                    <h3>AI SEO content engine</h3>
                    <p>Give it a keyword and your business context. Get titles, meta descriptions, outlines and full drafts written in your tone — ready to publish, fully editable.</p>
                    <ul class="check-list">
                        <li><i data-lucide="check"></i> SEO titles, meta descriptions and outlines</li>
                        <li><i data-lucide="check"></i> Long-form articles from a target keyword</li>
                        <li><i data-lucide="check"></i> Social posts for Instagram, LinkedIn, Facebook and X</li>
                        <li><i data-lucide="check"></i> Professional replies to Google reviews</li>
                    </ul>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="bot"></i></div>
                    <h3>AI assistant that knows your business</h3>
                    <p>Trained on your own knowledge base — services, pricing, FAQs and documents — so its answers are about your business, not the internet's.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="message-circle"></i></div>
                    <h3>Website chatbot, 24/7</h3>
                    <p>One embed snippet puts an AI chatbot on your site that answers questions and captures the visitor's details while you sleep.</p>
                </div>
                <div class="card bento-wide reveal">
                    <div class="icon-wrap"><i data-lucide="target"></i></div>
                    <h3>CRM built for follow-up, not data entry</h3>
                    <p>Every chatbot conversation and contact-form enquiry becomes a lead with an owner, a status and a next action. AI scores intent so you call the right person first.</p>
                    <ul class="check-list">
                        <li><i data-lucide="check"></i> Leads, customers, tasks and notes in one pipeline</li>
                        <li><i data-lucide="check"></i> AI lead scoring with a recommended next step</li>
                        <li><i data-lucide="check"></i> Automation rules for welcome emails and follow-up tasks</li>
                    </ul>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="file-text"></i></div>
                    <h3>Proposals, quotes and invoices</h3>
                    <p>Generate a priced quotation with tax and discounts, then send a clean print-ready PDF — without opening a spreadsheet.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="bar-chart-3"></i></div>
                    <h3>Analytics you'll actually check</h3>
                    <p>Leads, conversion rate, revenue and AI usage on one live dashboard, per business.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="users"></i></div>
                    <h3>Team roles and agency mode</h3>
                    <p>Invite staff with scoped permissions, or run multiple client businesses from one login with fully isolated data.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================ how it works -->
    <section class="section section-alt">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">How it works</span>
                <h2>Live in an afternoon</h2>
                <p>No onboarding calls, no implementation partner.</p>
            </div>
            <div class="grid grid-4">
                <div class="step reveal">
                    <div class="step-num">1</div>
                    <h3>Describe your business</h3>
                    <p>Services, cities you serve, and the customers you want. This becomes the context every AI feature uses.</p>
                </div>
                <div class="step reveal">
                    <div class="step-num">2</div>
                    <h3>Connect your AI key</h3>
                    <p>Paste an OpenAI, Gemini or Claude key — or point at any OpenAI-compatible endpoint. Keys are encrypted at rest.</p>
                </div>
                <div class="step reveal">
                    <div class="step-num">3</div>
                    <h3>Feed your knowledge base</h3>
                    <p>Upload documents and FAQs so the assistant and chatbot answer accurately instead of guessing.</p>
                </div>
                <div class="step reveal">
                    <div class="step-num">4</div>
                    <h3>Publish and follow up</h3>
                    <p>Ship content weekly, embed the chatbot, switch on automations, and watch the pipeline fill.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================ who it's for -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Who it's for</span>
                <h2>Built for how Indian businesses actually work</h2>
            </div>
            <div class="grid grid-4">
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="briefcase"></i></div>
                    <h3>Agencies</h3>
                    <p>Run every client from one login, with isolated data and per-client reporting.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="user"></i></div>
                    <h3>Freelancers</h3>
                    <p>Look like a studio. Send proper proposals and invoices in minutes.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="store"></i></div>
                    <h3>Local businesses</h3>
                    <p>Rank for "near me" searches and answer every enquiry the same day.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="trending-up"></i></div>
                    <h3>Growing teams</h3>
                    <p>Give staff scoped access so nothing depends on one person's inbox.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================ pricing preview -->
    <?php if (!empty($homePlans)): ?>
    <section class="section section-alt">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Pricing</span>
                <h2>Start free, upgrade when it pays for itself</h2>
                <p>Transparent pricing in rupees. No setup fees.</p>
            </div>
            <div class="grid grid-3">
                <?php foreach ($homePlans as $i => $plan): ?>
                <div class="pricing-card reveal <?= $plan['slug'] === 'growth' ? 'featured' : '' ?>">
                    <?php if ($plan['slug'] === 'growth'): ?><span class="badge-popular">Popular</span><?php endif; ?>
                    <h3><?= View::e($plan['name']) ?></h3>
                    <p><?= View::e((string) $plan['description']) ?></p>
                    <div class="price">
                        <?php if ((float) $plan['price_monthly'] <= 0): ?>
                            Free
                        <?php else: ?>
                            ₹<?= number_format((float) $plan['price_monthly'], 0) ?><span class="period">/month</span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= url('auth/register.php') ?>" class="btn <?= $plan['slug'] === 'growth' ? 'btn-primary' : 'btn-ghost' ?> btn-block">
                        Get started
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <p style="text-align:center;margin-top:28px;">
                <a href="<?= url('pricing') ?>" class="btn btn-ghost">Compare all plans <i data-lucide="arrow-right"></i></a>
            </p>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================================================ early customers -->
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Customer stories</span>
                <h2>We'd rather show you real results than invented ones</h2>
            </div>
            <div class="card reveal" style="max-width:720px;margin:0 auto;text-align:center;">
                <div class="icon-wrap" style="margin-left:auto;margin-right:auto;"><i data-lucide="quote"></i></div>
                <h3>No testimonials here yet — on purpose</h3>
                <p>BharatSEO is early, and we won't publish reviews we haven't earned. Case studies will appear here as our first customers get results worth writing about.</p>
                <p style="margin-bottom:0;"><a href="<?= url('contact') ?>" class="btn btn-ghost">Become an early customer <i data-lucide="arrow-right"></i></a></p>
            </div>
        </div>
    </section>

    <!-- ============================================================ faq -->
    <section class="section section-alt">
        <div class="container" style="max-width:800px;">
            <div class="section-head reveal">
                <span class="eyebrow">FAQ</span>
                <h2>Questions, answered</h2>
            </div>

            <details class="faq-item reveal">
                <summary>Do I need my own AI API key?</summary>
                <div>Yes. You connect your own OpenAI, Google Gemini, Anthropic Claude, or any OpenAI-compatible provider from Settings. This keeps you in control of your AI spend, and means your prompts go directly to your chosen provider. Keys are encrypted before being stored and are never sent to the browser.</div>
            </details>
            <details class="faq-item reveal">
                <summary>What happens if my AI provider is down?</summary>
                <div>You can configure more than one provider with a priority order. If the first one fails or times out, BharatSEO automatically falls back to the next, so a provider outage doesn't take your chatbot offline.</div>
            </details>
            <details class="faq-item reveal">
                <summary>Can I manage several client businesses?</summary>
                <div>Yes. Agency mode lets you run multiple businesses from one login, and every business's data is isolated at the database level — leads, documents and conversations are never shared between them.</div>
            </details>
            <details class="faq-item reveal">
                <summary>Is my data safe?</summary>
                <div>Passwords are hashed with bcrypt, API keys and SMTP credentials are encrypted at rest, every write is CSRF-protected, and all tenant queries are scoped server-side so one business can never read another's records.</div>
            </details>
            <details class="faq-item reveal">
                <summary>Can I host it myself?</summary>
                <div>Yes. BharatSEO runs on plain PHP 8.2+ and MySQL 8 with no Composer or Node.js needed at runtime, so it installs on ordinary cPanel shared hosting, a VPS, or AWS. A web installer sets up the database and admin account for you.</div>
            </details>
            <details class="faq-item reveal">
                <summary>Which languages does it support?</summary>
                <div>The interface ships in English and Hindi, and the AI features can generate content in either — useful when your customers search in one language and read in another.</div>
            </details>
        </div>
    </section>

    <!-- ============================================================ CTA -->
    <section class="section">
        <div class="container">
            <div class="cta-block reveal">
                <h2>Start ranking. Start replying. Start growing.</h2>
                <p>Create your workspace free, connect your AI key, and publish your first optimised page today.</p>
                <div class="hero-actions" style="margin-bottom:0;">
                    <a href="<?= url('auth/register.php') ?>" class="btn btn-primary btn-lg"><i data-lucide="rocket"></i> Create free account</a>
                    <a href="<?= url('contact') ?>" class="btn btn-ghost btn-lg">Talk to us</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
