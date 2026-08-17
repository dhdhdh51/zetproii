<?php
/**
 * Shared bootstrap for every /dashboard/*.php page:
 *   - requires login (redirects to /auth/login.php otherwise)
 *   - resolves the "active business" (from session, or the user's first
 *     business) and verifies membership server-side
 *   - redirects to onboarding if the active business hasn't finished it
 * Include this at the top of every dashboard page before any HTML output.
 */

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

$currentUser = Database::fetchOne(
    "SELECT id, uuid, name, email, role, avatar_path FROM users WHERE id = ? AND deleted_at IS NULL",
    [$_SESSION['user_id']]
);

if ($currentUser === null) {
    header('Location: /auth/login.php');
    exit;
}

$myBusinesses = Database::fetchAll(
    "SELECT b.id, b.uuid, b.name, b.slug, b.logo_path, b.onboarding_completed,
            IF(b.owner_id = ?, 'BUSINESS_OWNER', bm.role) AS my_role
     FROM businesses b
     LEFT JOIN business_members bm ON bm.business_id = b.id AND bm.user_id = ?
     WHERE b.deleted_at IS NULL AND (b.owner_id = ? OR bm.user_id = ?)
     ORDER BY b.created_at ASC",
    [$currentUser['id'], $currentUser['id'], $currentUser['id'], $currentUser['id']]
);

if (empty($myBusinesses)) {
    header('Location: /dashboard/onboarding.php');
    exit;
}

$activeBusinessId = $_SESSION['active_business_id'] ?? null;
$activeBusiness = null;

foreach ($myBusinesses as $b) {
    if ((int) $b['id'] === (int) $activeBusinessId) {
        $activeBusiness = $b;
        break;
    }
}
if ($activeBusiness === null) {
    $activeBusiness = $myBusinesses[0];
    $_SESSION['active_business_id'] = (int) $activeBusiness['id'];
}

$currentBusinessRole = $activeBusiness['my_role'];

if (!$activeBusiness['onboarding_completed'] && basename($_SERVER['SCRIPT_NAME']) !== 'onboarding.php') {
    header('Location: /dashboard/onboarding.php');
    exit;
}
