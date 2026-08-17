<?php
$pageTitle = 'About — BharatSEO';
$pageDescription = 'BharatSEO is an AI SEO and growth workspace for Indian small businesses, agencies and freelancers — search visibility, content, leads and follow-ups in one place.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/about';
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
            <span class="eyebrow">About us</span>
            <h1>Search visibility, without a <span class="highlight">marketing team</span></h1>
            <p class="lead">BharatSEO gives Indian small businesses the SEO and follow-up machinery that larger companies pay agencies for — as one workspace they can actually run themselves.</p>
        </div>
    </section>

    <section class="section" style="padding-top:8px;">
        <div class="container">
            <div class="grid grid-3">
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="target"></i></div>
                    <h3>The problem we work on</h3>
                    <p>A good local business can be nearly invisible online. Ranking takes consistent content, and converting takes fast follow-up. Both are jobs nobody on a five-person team has time for.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="layers"></i></div>
                    <h3>Our approach</h3>
                    <p>Treat visibility and follow-up as one workflow. Content that earns the visit and a CRM that catches the enquiry belong in the same system, sharing the same context about your business.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="scale"></i></div>
                    <h3>How we think about AI</h3>
                    <p>AI should draft, score and summarise — you decide. Every AI output in BharatSEO is editable before it goes anywhere, and you connect your own provider key so you keep control of the spend.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-alt">
        <div class="container prose">
            <h2>Who it's built for</h2>
            <p>Agencies managing several client businesses, freelancers who need to look established, local businesses competing on "near me" searches, and small teams that have outgrown running everything from one person's inbox.</p>

            <h2>What we believe about pricing</h2>
            <p>You bring your own AI provider key, so we never resell tokens or mark up model usage. There is a genuinely usable free plan, prices are in rupees, and there are no setup fees.</p>

            <h2>Where your data lives</h2>
            <p>Every business's records are isolated at the database level, so one workspace can never read another's leads, documents or conversations. Passwords are hashed with bcrypt and provider keys are encrypted at rest. You can also self-host BharatSEO on your own cPanel hosting, VPS or cloud account and keep the database entirely under your control.</p>

            <h2>Being straight with you</h2>
            <p>BharatSEO is early. You won't find invented testimonials or customer counts on this site — when we have results worth showing, we'll publish them with names attached. Until then, the product itself is the pitch: create a free workspace and judge it directly.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="cta-block reveal">
                <h2>Want to talk to us?</h2>
                <p>We're small enough that you'll reach someone who works on the product.</p>
                <div class="hero-actions" style="margin-bottom:0;">
                    <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg"><i data-lucide="mail"></i> Get in touch</a>
                    <a href="<?= url('auth/register.php') ?>" class="btn btn-ghost btn-lg">Start free</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
