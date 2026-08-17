<?php
$pageTitle = 'BharatAI Business OS — AI-Powered Business Automation Platform';
$pageDescription = 'Run your entire business with AI: CRM, lead management, AI chatbot, proposals, quotations, invoicing, and automation — all in one platform.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "BharatAI Business OS",
  "applicationCategory": "BusinessApplication",
  "operatingSystem": "Web",
  "offers": { "@type": "Offer", "price": "0", "priceCurrency": "INR" }
}
</script>
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>

<main>
    <section class="hero">
        <div class="container">
            <div class="hero-badge"><i data-lucide="zap" style="width:14px;height:14px;"></i> AI-powered business automation</div>
            <h1>Run your business on <span class="highlight">autopilot</span> with AI</h1>
            <p class="lead">BharatAI Business OS unifies your CRM, AI assistant, chatbot, proposals, quotations, invoicing and automation into one platform built for small businesses, agencies and freelancers.</p>
            <div class="hero-actions">
                <a href="/auth/register.php" class="btn btn-primary btn-lg"><i data-lucide="rocket"></i> Start Free</a>
                <a href="/features" class="btn btn-ghost btn-lg"><i data-lucide="play-circle"></i> See Features</a>
            </div>
        </div>
    </section>

    <!-- Problem -->
    <section class="section-alt">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">The Problem</span>
                <h2>Running a small business means juggling ten tools</h2>
                <p>Leads slip through spreadsheets. Follow-ups get forgotten. Proposals take hours. Your team spends more time on admin than on customers.</p>
            </div>
            <div class="grid grid-3">
                <div class="card"><div class="icon-wrap"><i data-lucide="inbox"></i></div><h3>Leads go cold</h3><p>Without automated follow-ups, most leads never hear back in time.</p></div>
                <div class="card"><div class="icon-wrap"><i data-lucide="clock"></i></div><h3>Hours lost on admin</h3><p>Manually writing proposals, quotes and emails eats your most valuable time.</p></div>
                <div class="card"><div class="icon-wrap"><i data-lucide="puzzle"></i></div><h3>Disconnected tools</h3><p>CRM here, invoicing there, chat somewhere else — nothing talks to each other.</p></div>
            </div>
        </div>
    </section>

    <!-- Solution / Features -->
    <section>
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">The Solution</span>
                <h2>One AI-powered operating system for your business</h2>
                <p>Everything connected. Every action tracked. Every follow-up automated.</p>
            </div>
            <div class="grid grid-3">
                <div class="card"><div class="icon-wrap"><i data-lucide="users"></i></div><h3>Complete CRM</h3><p>Leads, customers, tasks and follow-ups in one pipeline your whole team can see.</p></div>
                <div class="card"><div class="icon-wrap"><i data-lucide="bot"></i></div><h3>AI Business Assistant</h3><p>Ask your AI to qualify leads, draft replies, summarize your day, or write a proposal.</p></div>
                <div class="card"><div class="icon-wrap"><i data-lucide="message-circle"></i></div><h3>AI Website Chatbot</h3><p>An embeddable chatbot that knows your business and captures leads 24/7.</p></div>
                <div class="card"><div class="icon-wrap"><i data-lucide="workflow"></i></div><h3>Automation Rules</h3><p>Auto-send welcome emails, create follow-up tasks, and notify your team instantly.</p></div>
                <div class="card"><div class="icon-wrap"><i data-lucide="file-text"></i></div><h3>Proposals & Quotes</h3><p>Generate professional, print-ready documents with AI in seconds — fully editable.</p></div>
                <div class="card"><div class="icon-wrap"><i data-lucide="bar-chart-3"></i></div><h3>Real-Time Analytics</h3><p>Track leads, conversions, revenue and AI usage from one live dashboard.</p></div>
            </div>
        </div>
    </section>

    <!-- AI capabilities -->
    <section class="section-alt">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">AI Capabilities</span>
                <h2>AI that actually understands your business</h2>
                <p>Powered by your own knowledge base — documents, FAQs, products and services — with support for OpenAI, Gemini, Anthropic, or any OpenAI-compatible provider.</p>
            </div>
            <div class="grid grid-4">
                <div class="card"><h3>Lead Qualification</h3><p>AI scores intent, buying probability and recommends next action.</p></div>
                <div class="card"><h3>Review Replies</h3><p>Generate professional responses to positive, neutral or negative reviews.</p></div>
                <div class="card"><h3>Social Content</h3><p>Instagram, Facebook, LinkedIn and X posts tailored to your audience.</p></div>
                <div class="card"><h3>SEO Content</h3><p>Titles, meta descriptions, outlines and full articles for your target keywords.</p></div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section>
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">How It Works</span>
                <h2>Get started in minutes</h2>
            </div>
            <div class="grid grid-2" style="max-width:760px;margin:0 auto;gap:28px;">
                <div class="step"><div class="step-num">1</div><div><h3>Create your business profile</h3><p>Tell us about your business, services and target customers.</p></div></div>
                <div class="step"><div class="step-num">2</div><div><h3>Connect your AI provider</h3><p>Bring your own OpenAI, Gemini or Anthropic key — or use a custom endpoint.</p></div></div>
                <div class="step"><div class="step-num">3</div><div><h3>Import your knowledge base</h3><p>Upload documents, FAQs and product info so your AI knows your business.</p></div></div>
                <div class="step"><div class="step-num">4</div><div><h3>Automate & grow</h3><p>Turn on automations, embed your chatbot, and let AI do the busywork.</p></div></div>
            </div>
        </div>
    </section>

    <!-- Use cases -->
    <section class="section-alt">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Use Cases</span>
                <h2>Built for how you actually work</h2>
            </div>
            <div class="grid grid-4">
                <div class="card"><h3>Agencies</h3><p>Manage multiple client businesses from one dashboard with isolated data.</p></div>
                <div class="card"><h3>Freelancers</h3><p>Send proposals and quotes without a design tool or spreadsheet.</p></div>
                <div class="card"><h3>Local Businesses</h3><p>Capture and respond to leads from your website automatically.</p></div>
                <div class="card"><h3>Growing Teams</h3><p>Give staff role-based access to leads, customers and tasks.</p></div>
            </div>
        </div>
    </section>

    <!-- Testimonials (placeholder, per instructions - no fake testimonials) -->
    <section>
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">What Our Customers Say</span>
                <h2>Trusted by growing businesses</h2>
            </div>
            <div class="placeholder-note">
                Customer testimonials will appear here once published by BharatAI Business OS customers. No fabricated reviews are shown.
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section-alt">
        <div class="container prose" style="max-width:760px;">
            <div class="section-head">
                <span class="eyebrow">FAQ</span>
                <h2>Frequently asked questions</h2>
            </div>
            <details class="faq-item"><summary>Do I need my own AI API key? <i data-lucide="chevron-down"></i></summary><p>Yes — connect your OpenAI, Gemini, Anthropic, or any OpenAI-compatible provider key from Settings. Your keys are encrypted and never exposed to the browser.</p></details>
            <details class="faq-item"><summary>Can I use this for multiple client businesses? <i data-lucide="chevron-down"></i></summary><p>Yes, Agency mode lets you manage multiple client businesses with fully isolated data from one login.</p></details>
            <details class="faq-item"><summary>Is my data secure? <i data-lucide="chevron-down"></i></summary><p>All business data is isolated per tenant, passwords are hashed, and sensitive keys are encrypted at rest.</p></details>
            <details class="faq-item"><summary>Can I self-host this? <i data-lucide="chevron-down"></i></summary><p>Yes — BharatAI Business OS runs on plain PHP + MySQL and deploys to any cPanel, Apache, or VPS environment.</p></details>
        </div>
    </section>

    <!-- CTA -->
    <section>
        <div class="container">
            <div class="cta-block">
                <h2>Ready to automate your business?</h2>
                <p>Start free. No credit card required.</p>
                <a href="/auth/register.php" class="btn btn-primary btn-lg">Get Started Free</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
