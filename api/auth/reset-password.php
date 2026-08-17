<?php
/**
 * POST /api/auth/reset-password.php
 * Body: { token, password, password_confirmation }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

Validator::make($request->all())
    ->required('token', 'Token')
    ->required('password', 'Password')
    ->strongPassword('password')
    ->validateOrFail();

if ($request->string('password') !== $request->string('password_confirmation')) {
    Response::validationError(['password_confirmation' => ['Passwords do not match.']]);
}

$authService = new AuthService();

try {
    $authService->resetPassword($request->string('token'), $request->string('password'));
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), [], 400);
}

Response::success(null, 'Your password has been reset successfully. You can now log in.');
