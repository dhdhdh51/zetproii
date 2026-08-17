<?php
/**
 * GET /api/auth/verify-email.php?token=...
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
$token = $request->string('token');

if ($token === '') {
    Response::error('Missing verification token.', [], 400);
}

$authService = new AuthService();

try {
    $authService->verifyEmail($token);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), [], 400);
}

Response::success(null, 'Email verified successfully. You can now log in.');
