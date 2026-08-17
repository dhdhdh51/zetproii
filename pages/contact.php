<?php
$pageTitle = 'Contact Us — BharatSEO';
$pageDescription = 'Get in touch with the BharatSEO team. We would love to hear from you.';
$canonicalUrl = rtrim((string) config('app.url'), '/') . '/contact';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>

<main>
    <section class="hero" style="padding-bottom:8px;">
        <div class="aurora" aria-hidden="true"></div>
        <div class="grid-overlay" aria-hidden="true"></div>
        <div class="container">
            <span class="eyebrow">Contact</span>
            <h1>Get in <span class="highlight">touch</span></h1>
            <p class="lead">Questions, feedback, or partnership ideas — send us a message and we'll respond as soon as we can.</p>
        </div>
    </section>

    <section class="section" style="padding-top:16px;">
        <div class="container">
            <div class="grid grid-3" style="align-items:start;">
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="message-square"></i></div>
                    <h3>Product questions</h3>
                    <p>Not sure whether BharatSEO fits your business? Describe your setup and we'll tell you honestly.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="life-buoy"></i></div>
                    <h3>Existing customer?</h3>
                    <p>Support tickets raised from inside your dashboard reach us faster and arrive with your account context attached.</p>
                </div>
                <div class="card reveal">
                    <div class="icon-wrap"><i data-lucide="handshake"></i></div>
                    <h3>Agencies &amp; partners</h3>
                    <p>Managing SEO for several clients? Tell us how you work — agency mode is shaped by this feedback.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" style="padding-top:0;">
        <div class="container" style="max-width:680px;">
            <div class="form-card reveal">
                <div id="contact-form-msg" class="form-msg"></div>
                <form id="contact-form">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control" required maxlength="190">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required maxlength="190">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone (optional)</label>
                        <input type="tel" id="phone" name="phone" class="form-control" maxlength="30">
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" required maxlength="5000"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Send Message</button>
                </form>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
