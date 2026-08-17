<?php
/**
 * POST /api/auth/forgot-password.php
 * Body: { email }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

RateLimitMiddleware::throttle('forgot_pw_' . Security::clientIp(), 5, 900);

Validator::make($request->all())->required('email', 'Email')->email('email')->validateOrFail();

$authService = new AuthService();
$authService->requestPasswordReset($request->string('email'));

// Always return success regardless of whether the email exists (prevents
// account enumeration).
Response::success(null, 'If an account exists with that email, a password reset link has been sent.');
