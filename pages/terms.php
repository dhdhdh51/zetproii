<?php
$pageTitle = 'Terms of Service — BharatAI Business OS';
$pageDescription = 'Read the terms of service for using BharatAI Business OS.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/terms';
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
            <h1>Terms of Service</h1>
            <p><em>Last updated: <?= date('F j, Y') ?></em></p>

            <h2>1. Acceptance of Terms</h2>
            <p>By creating an account or using BharatAI Business OS, you agree to these Terms of Service.</p>

            <h2>2. Account Responsibilities</h2>
            <p>You are responsible for maintaining the confidentiality of your account credentials and for all activity that occurs under your account.</p>

            <h2>3. Acceptable Use</h2>
            <p>You agree not to use the platform to send spam, generate fraudulent content, violate applicable laws, or attempt to access data belonging to another business without authorization.</p>

            <h2>4. AI-Generated Content</h2>
            <p>Content generated using AI features is provided as a starting point. You are responsible for reviewing and verifying AI-generated content (proposals, quotations, emails, social posts, etc.) before sending it to customers.</p>

            <h2>5. Subscription & Billing</h2>
            <p>Paid plans are billed according to the billing cycle you select. Usage limits are enforced per your plan and are described on the <a href="/pricing">Pricing page</a>.</p>

            <h2>6. Termination</h2>
            <p>We may suspend or terminate accounts that violate these terms. You may cancel your subscription and delete your account at any time.</p>

            <h2>7. Limitation of Liability</h2>
            <p>The platform is provided "as is" without warranties of any kind. We are not liable for indirect or consequential damages arising from use of the platform.</p>

            <h2>8. Changes to Terms</h2>
            <p>We may update these terms from time to time. Continued use of the platform after changes constitutes acceptance of the updated terms.</p>

            <h2>9. Contact</h2>
            <p>Questions about these terms can be sent via our <a href="/contact">Contact page</a>.</p>
        </div>
    </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
