<?php
$pageTitle = 'Refund Policy — BharatAI Business OS';
$pageDescription = 'Read the refund policy for BharatAI Business OS subscription plans.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/refund-policy';
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
            <h1>Refund Policy</h1>
            <p><em>Last updated: <?= date('F j, Y') ?></em></p>

            <h2>1. Free Plan</h2>
            <p>The Free plan requires no payment and is not subject to refunds.</p>

            <h2>2. Paid Subscriptions</h2>
            <p>Paid subscriptions are billed in advance for the selected billing cycle (monthly or yearly). You may cancel at any time; your subscription will remain active until the end of the current billing period, after which it will not renew.</p>

            <h2>3. Refund Eligibility</h2>
            <p>Refund requests submitted within 7 days of an initial paid subscription purchase will be reviewed on a case-by-case basis. Refunds are not provided for renewal charges, partial billing periods, or accounts that have been suspended for violating the Terms of Service.</p>

            <h2>4. How to Request a Refund</h2>
            <p>Contact us via the <a href="/contact">Contact page</a> with your account email and reason for the refund request.</p>

            <h2>5. Processing Time</h2>
            <p>Approved refunds are processed back to the original payment method within 5-10 business days, depending on the payment gateway used.</p>
        </div>
    </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
