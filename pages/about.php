<?php
$pageTitle = 'About Us — BharatAI Business OS';
$pageDescription = 'Learn about BharatAI Business OS, an AI-powered business automation platform built for small businesses, agencies and freelancers.';
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
    <section class="hero" style="padding-bottom:20px;">
        <div class="container prose">
            <h1>About <span class="highlight">BharatAI Business OS</span></h1>
            <p class="lead">We built BharatAI Business OS to give small businesses, agencies and freelancers the same AI-powered automation that larger companies already have — without the complexity or the price tag.</p>
        </div>
    </section>

    <section>
        <div class="container prose">
            <h2>Our mission</h2>
            <p>Running a small business shouldn't mean juggling ten disconnected tools. BharatAI Business OS brings your CRM, AI assistant, chatbot, documents and automation into a single platform, so you can spend less time on admin and more time on customers.</p>

            <h2>Built for real businesses</h2>
            <p>Every feature in this platform — from lead qualification to automated follow-ups to AI-generated proposals — is designed around how small teams actually work day to day.</p>

            <h2>Open, honest, and secure</h2>
            <p>Your data is isolated per business, your AI provider keys are encrypted, and you can bring your own AI provider of choice — OpenAI, Gemini, Anthropic, or any OpenAI-compatible endpoint.</p>
        </div>
    </section>

    <section class="section-alt">
        <div class="container">
            <div class="cta-block">
                <h2>Join us</h2>
                <p>Start automating your business today.</p>
                <a href="<?= url('auth/register.php') ?>" class="btn btn-primary btn-lg">Start Free</a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
