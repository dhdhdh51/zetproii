<?php
/**
 * POST /api/auth/login.php
 * Body: { email, password, remember? }
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

RateLimitMiddleware::throttle('login_' . Security::clientIp(), 20, 600);

Validator::make($request->all())
    ->required('email', 'Email')
    ->email('email')
    ->required('password', 'Password')
    ->validateOrFail();

$authService = new AuthService();
$user = $authService->login($request->string('email'), $request->string('password'));

// "Remember me": extend the session cookie lifetime for this browser only.
if ($request->bool('remember')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires'  => time() + 60 * 60 * 24 * 30,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$businesses = Database::fetchAll(
    "SELECT b.id, b.uuid, b.name, b.slug, b.logo_path
     FROM businesses b
     WHERE b.deleted_at IS NULL AND (b.owner_id = ? OR b.id IN (
         SELECT business_id FROM business_members WHERE user_id = ? AND status = 'active'
     ))
     ORDER BY b.created_at ASC",
    [$user['id'], $user['id']]
);

Response::success([
    'user' => $user,
    'businesses' => $businesses,
    'csrf_token' => Security::csrfToken(),
], 'Login successful.');
