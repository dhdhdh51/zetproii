<?php
require_once dirname(__DIR__) . '/app/config/bootstrap.php';

$token = (new Request())->string('token');
$verified = false;
$errorMessage = null;

if ($token === '') {
    $errorMessage = 'Missing verification token.';
} else {
    try {
        (new AuthService())->verifyEmail($token);
        $verified = true;
    } catch (\Throwable $e) {
        $errorMessage = 'This verification link is invalid or has expired.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php $pageTitle = 'Verify Email'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card" style="text-align:center;">
        <a href="<?= url() ?>" class="auth-brand" style="justify-content:center;"><i data-lucide="trending-up"></i> BharatSEO</a>
        <?php if ($verified): ?>
            <i data-lucide="check-circle" style="width:48px;height:48px;color:var(--green);margin:0 auto 12px;"></i>
            <h1>Email Verified!</h1>
            <p class="subtitle">Your account is now active. You can log in.</p>
            <a href="<?= url('auth/login.php') ?>" class="btn btn-primary" style="display:block;">Log In</a>
        <?php else: ?>
            <i data-lucide="x-circle" style="width:48px;height:48px;color:var(--red);margin:0 auto 12px;"></i>
            <h1>Verification Failed</h1>
            <p class="subtitle"><?= View::e($errorMessage) ?></p>
            <a href="<?= url('auth/login.php') ?>" class="btn btn-secondary" style="display:block;">Back to Log In</a>
        <?php endif; ?>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
