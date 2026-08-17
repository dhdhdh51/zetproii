<?php
/**
 * POST /api/business/update.php  Body: { business_id, name, website, phone, email, address, city, state, country, currency, timezone }
 * Updates core business profile fields (Settings page).
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$request = new Request();
if ($request->method !== 'POST') {
    Response::error('Method not allowed', [], 405);
}
Security::requireCsrf();

$user = AuthMiddleware::user();
$businessId = $request->int('business_id');
$role = AuthMiddleware::requireBusinessAccess((int) $user['id'], $businessId);
PermissionMiddleware::require($role, 'settings.manage');

$allowed = ['name', 'website', 'phone', 'email', 'address', 'city', 'state', 'country', 'currency', 'timezone'];
$sets = [];
$params = [];
foreach ($allowed as $col) {
    if ($request->has($col)) {
        $sets[] = "{$col} = ?";
        $params[] = $col === 'email' ? Security::cleanEmail($request->string($col)) : Security::cleanString($request->string($col));
    }
}

if (!empty($sets)) {
    $params[] = $businessId;
    Database::query("UPDATE businesses SET " . implode(', ', $sets) . " WHERE id = ?", $params);
    AuditLogger::log((int) $user['id'], $businessId, 'business_updated', ['fields' => array_keys($request->all())]);
}

Response::success(Database::fetchOne("SELECT * FROM businesses WHERE id = ?", [$businessId]), 'Business settings saved.');
