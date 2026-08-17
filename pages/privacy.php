<?php
$pageTitle = 'Privacy Policy — BharatAI Business OS';
$pageDescription = 'Read the BharatAI Business OS privacy policy covering data collection, usage, storage and your rights.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/privacy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<main>
    <section>
        <div class="container prose">
            <h1>Privacy Policy</h1>
            <p><em>Last updated: <?= date('F j, Y') ?></em></p>

            <h2>1. Information We Collect</h2>
            <p>We collect account information (name, email, phone), business information you provide during onboarding, and usage data such as leads, customers and documents you create within the platform.</p>

            <h2>2. How We Use Your Information</h2>
            <p>We use your information to operate the platform, provide AI-powered features using the provider you configure, send transactional emails (verification, password resets, notifications), and improve the service.</p>

            <h2>3. Data Isolation</h2>
            <p>Each business's data is isolated at the database level. Business data is never shared with or accessible to other tenants on the platform.</p>

            <h2>4. Third-Party AI Providers</h2>
            <p>When you use AI features, relevant content is sent to the AI provider you have configured (OpenAI, Google Gemini, Anthropic, or a custom provider) using your own API key. We do not share your data with AI providers you have not configured.</p>

            <h2>5. Data Security</h2>
            <p>Passwords are hashed using industry-standard algorithms. API keys and SMTP credentials are encrypted at rest. We apply CSRF protection, input validation, and role-based access control throughout the platform.</p>

            <h2>6. Data Retention</h2>
            <p>You may delete your account, business data, or specific records at any time. Some records may be retained as soft-deleted for a limited period for recovery and audit purposes before permanent deletion.</p>

            <h2>7. Your Rights</h2>
            <p>You may request a copy of your data or request deletion of your account by contacting us via the <a href="<?= url('contact') ?>">Contact page</a>.</p>

            <h2>8. Contact</h2>
            <p>For privacy-related questions, please reach out via our <a href="<?= url('contact') ?>">Contact page</a>.</p>
        </div>
    </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
