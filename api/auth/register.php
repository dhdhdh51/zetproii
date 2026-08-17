<?php
/**
 * POST /api/auth/register.php
 * Body: { name, email, password, password_confirmation, phone? }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

RateLimitMiddleware::throttle('register_' . Security::clientIp(), 10, 600);

Validator::make($request->all())
    ->required('name', 'Name')
    ->maxLength('name', 150)
    ->required('email', 'Email')
    ->email('email')
    ->required('password', 'Password')
    ->strongPassword('password')
    ->validateOrFail();

$data = $request->all();
if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? $data['password'] ?? '')) {
    Response::validationError(['password_confirmation' => ['Passwords do not match.']]);
}

$authService = new AuthService();
$user = $authService->register(
    $request->string('name'),
    $request->string('email'),
    $request->string('password'),
    $request->string('phone') ?: null
);

Response::success($user, 'Registration successful. Please check your email to verify your account.', 201);
