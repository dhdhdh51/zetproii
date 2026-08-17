<?php
$pageTitle = 'Contact Us — BharatAI Business OS';
$pageDescription = 'Get in touch with the BharatAI Business OS team. We would love to hear from you.';
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
    <section class="hero" style="padding-bottom:0;">
        <div class="container">
            <h1>Get in <span class="highlight">touch</span></h1>
            <p class="lead">Questions, feedback, or partnership ideas — send us a message and we'll respond as soon as we can.</p>
        </div>
    </section>

    <section>
        <div class="container">
            <div class="card form-card">
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
