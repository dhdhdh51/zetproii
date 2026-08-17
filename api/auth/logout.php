<?php
/**
 * POST /api/auth/logout.php
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

Security::requireCsrf();

$user = AuthMiddleware::user();

$authService = new AuthService();
$authService->logout((int) $user['id']);

Response::success(null, 'Logged out successfully.');
