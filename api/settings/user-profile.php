<?php
/**
 * POST /api/settings/user-profile.php  Body: { name, phone, current_password?, new_password? }
 * Updates the current user's own profile / password.
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}
Security::requireCsrf();

$user = AuthMiddleware::user();
$fullUser = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$user['id']]);

$sets = [];
$params = [];
if ($request->has('name')) {
    $sets[] = "name = ?";
    $params[] = Security::cleanString($request->string('name'));
}
if ($request->has('phone')) {
    $sets[] = "phone = ?";
    $params[] = Security::cleanString($request->string('phone'));
}

if ($request->string('new_password') !== '') {
    if (!Security::verifyPassword($request->string('current_password'), $fullUser['password_hash'])) {
        Response::validationError(['current_password' => ['Current password is incorrect.']]);
    }
    Validator::make($request->all())->strongPassword('new_password')->validateOrFail();
    $sets[] = "password_hash = ?";
    $params[] = Security::hashPassword($request->string('new_password'));
    AuditLogger::log((int) $user['id'], null, 'password_changed', []);
}

if (!empty($sets)) {
    $params[] = $user['id'];
    Database::query("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?", $params);
}

Response::success(Database::fetchOne("SELECT id, name, email, phone, role FROM users WHERE id = ?", [$user['id']]), 'Profile updated.');
