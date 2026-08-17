<?php
require_once dirname(__DIR__) . '/app/config/bootstrap.php';

// If already logged in, skip straight to the dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log In — BharatAI Business OS</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <a href="/" class="auth-brand"><i data-lucide="sparkles"></i> BharatAI Business OS</a>
        <h1>Welcome back</h1>
        <p class="subtitle">Log in to your account to continue</p>

        <div id="form-alert" class="alert alert-error"></div>

        <form id="login-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="form-row-inline">
                <label class="checkbox-row"><input type="checkbox" id="remember" name="remember"> Remember me</label>
                <a href="/auth/forgot-password.php">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary" id="login-submit">Log In</button>
        </form>

        <p class="auth-footer-link">Don't have an account? <a href="/auth/register.php">Sign up free</a></p>
    </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
document.getElementById('login-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const alertBox = document.getElementById('form-alert');
    const submitBtn = document.getElementById('login-submit');
    alertBox.classList.remove('show');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Logging in...';

    const payload = {
        email: document.getElementById('email').value,
        password: document.getElementById('password').value,
        remember: document.getElementById('remember').checked,
    };

    try {
        const json = await Api.call('/api/auth/login.php', { method: 'POST', body: payload });
        if (json.success) {
            Api.setCsrfToken(json.data.csrf_token);
            window.location.href = '/dashboard/index.php';
        } else {
            alertBox.textContent = json.message || 'Login failed.';
            alertBox.classList.add('show');
        }
    } catch (err) {
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.classList.add('show');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Log In';
    }
});
</script>
</body>
</html>
