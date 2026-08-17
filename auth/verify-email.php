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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Email — BharatAI Business OS</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card" style="text-align:center;">
        <a href="/" class="auth-brand" style="justify-content:center;"><i data-lucide="sparkles"></i> BharatAI Business OS</a>
        <?php if ($verified): ?>
            <i data-lucide="check-circle" style="width:48px;height:48px;color:var(--color-success);margin:0 auto 12px;"></i>
            <h1>Email Verified!</h1>
            <p class="subtitle">Your account is now active. You can log in.</p>
            <a href="/auth/login.php" class="btn btn-primary" style="display:block;">Log In</a>
        <?php else: ?>
            <i data-lucide="x-circle" style="width:48px;height:48px;color:var(--color-danger);margin:0 auto 12px;"></i>
            <h1>Verification Failed</h1>
            <p class="subtitle"><?= View::e($errorMessage) ?></p>
            <a href="/auth/login.php" class="btn btn-secondary" style="display:block;">Back to Log In</a>
        <?php endif; ?>
    </div>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
