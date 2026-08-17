<?php
require_once dirname(__DIR__) . '/app/config/bootstrap.php';

$token = (new Request())->string('token');
if ($token === '') {
    header('Location: ' . url('auth/forgot-password.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php $pageTitle = 'Reset Password'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <a href="<?= url() ?>" class="auth-brand"><i data-lucide="trending-up"></i> BharatSEO</a>
        <h1>Set a new password</h1>
        <p class="subtitle">Choose a strong new password for your account</p>

        <div id="form-alert" class="alert alert-error"></div>

        <form id="reset-form">
            <input type="hidden" id="token" value="<?= View::e($token) ?>">
            <div class="form-group">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                <div class="form-help">At least 8 characters, with uppercase, lowercase and a number.</div>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary" id="reset-submit">Reset Password</button>
        </form>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
document.getElementById('reset-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const alertBox = document.getElementById('form-alert');
    const submitBtn = document.getElementById('reset-submit');
    alertBox.classList.remove('show');

    const password = document.getElementById('password').value;
    const confirm = document.getElementById('password_confirmation').value;
    if (password !== confirm) {
        alertBox.textContent = 'Passwords do not match.';
        alertBox.classList.add('show');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Resetting...';

    try {
        const json = await Api.call(appBase() + '/api/auth/reset-password.php', {
            method: 'POST',
            body: {
                token: document.getElementById('token').value,
                password: password,
                password_confirmation: confirm,
            },
        });
        if (json.success) {
            document.querySelector('.auth-card').innerHTML =
                '<div class="auth-brand"><i data-lucide="check-circle"></i> Password Reset</div>' +
                '<h1>All set!</h1>' +
                '<p class="subtitle">Your password has been reset successfully.</p>' +
                '<a href="<?= url('auth/login.php') ?>" class="btn btn-primary" style="display:block;text-align:center;">Log In</a>';
            if (window.lucide) lucide.createIcons();
        } else {
            alertBox.textContent = json.message || 'This link is invalid or expired.';
            alertBox.classList.add('show');
        }
    } catch (err) {
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.classList.add('show');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Reset Password';
    }
});
</script>
</body>
</html>
