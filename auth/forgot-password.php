<?php
require_once dirname(__DIR__) . '/app/config/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — BharatAI Business OS</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <a href="/" class="auth-brand"><i data-lucide="sparkles"></i> BharatAI Business OS</a>
        <h1>Forgot your password?</h1>
        <p class="subtitle">Enter your email and we'll send you a reset link</p>

        <div id="form-alert" class="alert alert-error"></div>
        <div id="form-success" class="alert alert-success"></div>

        <form id="forgot-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
            </div>
            <button type="submit" class="btn btn-primary" id="forgot-submit">Send Reset Link</button>
        </form>

        <p class="auth-footer-link"><a href="/auth/login.php">Back to Log In</a></p>
    </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
document.getElementById('forgot-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const alertBox = document.getElementById('form-alert');
    const successBox = document.getElementById('form-success');
    const submitBtn = document.getElementById('forgot-submit');
    alertBox.classList.remove('show');
    successBox.classList.remove('show');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending...';

    try {
        const json = await Api.call('/api/auth/forgot-password.php', {
            method: 'POST',
            body: { email: document.getElementById('email').value },
        });
        if (json.success) {
            successBox.textContent = json.message;
            successBox.classList.add('show');
            document.getElementById('forgot-form').reset();
        } else {
            alertBox.textContent = json.message || 'Something went wrong.';
            alertBox.classList.add('show');
        }
    } catch (err) {
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.classList.add('show');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Reset Link';
    }
});
</script>
</body>
</html>
