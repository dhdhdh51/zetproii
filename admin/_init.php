<?php
/**
 * Shared bootstrap for every /admin/*.php page. Requires the logged-in
 * user to have the platform-level ADMIN or SUPER_ADMIN role - this is
 * completely separate from any business_members role and is checked
 * directly off users.role, never off client input.
 */

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . url('auth/login.php'));
    exit;
}

$currentUser = Database::fetchOne(
    "SELECT id, uuid, name, email, role FROM users WHERE id = ? AND deleted_at IS NULL",
    [$_SESSION['user_id']]
);

if ($currentUser === null || !in_array($currentUser['role'], ['ADMIN', 'SUPER_ADMIN'], true)) {
    http_response_code(403);
    echo '<h1>403 Forbidden</h1><p>You do not have access to the admin panel.</p>';
    exit;
}

$isSuperAdmin = $currentUser['role'] === 'SUPER_ADMIN';
