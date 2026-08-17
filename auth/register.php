<?php
require_once dirname(__DIR__) . '/app/config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . url('dashboard/index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php $pageTitle = 'Create Your Account'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <a href="<?= url() ?>" class="auth-brand"><i data-lucide="trending-up"></i> BharatSEO</a>
        <h1>Create your account</h1>
        <p class="subtitle">Start automating your business today — free</p>

        <div id="form-alert" class="alert alert-error"></div>

        <form id="register-form">
            <div class="form-group">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" class="form-control" required maxlength="150" autocomplete="name">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="phone">Phone (optional)</label>
                <input type="tel" id="phone" name="phone" class="form-control" maxlength="30" autocomplete="tel">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                <div class="form-help">At least 8 characters, with uppercase, lowercase and a number.</div>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary" id="register-submit">Create Account</button>
        </form>

        <p class="auth-footer-link">Already have an account? <a href="<?= url('auth/login.php') ?>">Log in</a></p>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
document.getElementById('register-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const alertBox = document.getElementById('form-alert');
    const submitBtn = document.getElementById('register-submit');
    alertBox.classList.remove('show');

    const password = document.getElementById('password').value;
    const confirm = document.getElementById('password_confirmation').value;
    if (password !== confirm) {
        alertBox.textContent = 'Passwords do not match.';
        alertBox.classList.add('show');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating account...';

    const payload = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        password: password,
        password_confirmation: confirm,
    };

    try {
        const json = await Api.call(appBase() + '/api/auth/register.php', { method: 'POST', body: payload });
        if (json.success) {
            document.querySelector('.auth-card').innerHTML =
                '<div class="auth-brand"><i data-lucide="mail-check"></i> Check your email</div>' +
                '<h1>Verify your email</h1>' +
                '<p class="subtitle">We sent a verification link to <strong>' + payload.email + '</strong>. Click the link to activate your account.</p>' +
                '<a href="<?= url('auth/login.php') ?>" class="btn btn-secondary" style="display:block;text-align:center;">Back to Log In</a>';
            if (window.lucide) lucide.createIcons();
        } else {
            const firstError = json.errors && Object.values(json.errors)[0];
            alertBox.textContent = (firstError && firstError[0]) || json.message || 'Registration failed.';
            alertBox.classList.add('show');
        }
    } catch (err) {
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.classList.add('show');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Account';
    }
});
</script>
</body>
</html>
