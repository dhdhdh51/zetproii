<?php
/**
 * POST /api/business/settings-save.php
 * Body: { business_id, settings: { key: value, ... } }
 * Generic key/value settings store for a business (business_settings
 * table) - used for things like ai_default_tone, invoice_prefix, etc.
 * that don't warrant a dedicated column on `businesses`.
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

$settings = $request->array('settings');
if (empty($settings)) {
    Response::validationError(['settings' => ['No settings provided.']]);
}

foreach ($settings as $key => $value) {
    $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
    if ($key === '') {
        continue;
    }
    Database::query(
        "INSERT INTO business_settings (business_id, setting_key, setting_value, created_at, updated_at)
         VALUES (?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
        [$businessId, $key, is_scalar($value) ? (string) $value : json_encode($value)]
    );
}

AuditLogger::log((int) $user['id'], $businessId, 'settings_updated', ['keys' => array_keys($settings)]);

Response::success(null, 'Settings saved.');
