<?php
/**
 * POST /api/auth/resend-verification.php
 * Body: { email }
 *
 * Recovery path for an account stuck in the 'pending' state. Without this,
 * a user whose verification email never arrived had no way back into their
 * own account.
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

RateLimitMiddleware::throttle('resend_verify_' . Security::clientIp(), 5, 900);

Validator::make($request->all())->required('email', 'Email')->email('email')->validateOrFail();

$authService = new AuthService();
$outcome = $authService->resendVerificationEmail($request->string('email'));

// 'activated' is the one case where the user's next action genuinely differs,
// so it gets its own message. Every other outcome answers identically, so this
// endpoint cannot be used to find out which emails are registered.
if ($outcome === 'activated') {
    Response::success(
        ['activated' => true],
        'Email sending is not set up on this server, so your account has been activated directly. You can log in now.'
    );
}

if ($outcome === 'failed') {
    Response::error(
        'We could not send the email right now. Please try again shortly, or contact support.',
        [],
        503
    );
}

Response::success(
    ['activated' => false],
    'If that account still needs verification, a new verification link has been sent.'
);
