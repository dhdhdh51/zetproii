<?php
/**
 * GET /api/auth/me.php
 * Returns the currently authenticated user + their businesses, or 401.
 * Frontend uses this on page load to restore session state and CSRF token.
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$user = AuthMiddleware::user();

$businesses = Database::fetchAll(
    "SELECT b.id, b.uuid, b.name, b.slug, b.logo_path, b.onboarding_completed,
            IF(b.owner_id = ?, 'BUSINESS_OWNER', bm.role) AS my_role
     FROM businesses b
     LEFT JOIN business_members bm ON bm.business_id = b.id AND bm.user_id = ?
     WHERE b.deleted_at IS NULL AND (b.owner_id = ? OR bm.user_id = ?)
     ORDER BY b.created_at ASC",
    [$user['id'], $user['id'], $user['id'], $user['id']]
);

Response::success([
    'user' => $user,
    'businesses' => $businesses,
    'csrf_token' => Security::csrfToken(),
]);
