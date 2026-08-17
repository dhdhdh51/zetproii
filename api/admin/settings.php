<?php
/**
 * GET  /api/admin/settings.php               -> all platform settings (secrets masked)
 * POST /api/admin/settings.php  Body: { key: value, ... }
 * Handles both general platform settings and SMTP config (smtp_* keys),
 * encrypting sensitive values (smtp_password) before storage.
 */

require_once dirname(__DIR__, 2) . '/app/config/bootstrap.php';

$admin = AuthMiddleware::requireRole(['ADMIN', 'SUPER_ADMIN']);
$request = new Request();

$encryptedKeys = ['smtp_password', 'google_client_secret'];

if ($request->method === 'GET') {
    $rows = Database::fetchAll("SELECT setting_key, setting_value, is_encrypted FROM settings");
    $result = [];
    foreach ($rows as $row) {
        if ((int) $row['is_encrypted'] === 1) {
            $result[$row['setting_key']] = $row['setting_value'] !== null ? '••••••••' : '';
        } else {
            $result[$row['setting_key']] = $row['setting_value'];
        }
    }
    Response::success($result);
}

if ($request->method === 'POST') {
    Security::requireCsrf();
    $data = $request->all();

    foreach ($data as $key => $value) {
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
        if ($key === '' || $key === 'csrf_token') {
            continue;
        }

        $isEncrypted = in_array($key, $encryptedKeys, true);

        // Don't overwrite an encrypted secret with the masked placeholder.
        if ($isEncrypted && $value === '••••••••') {
            continue;
        }

        $storedValue = $isEncrypted && $value !== '' ? Security::encrypt((string) $value) : (is_scalar($value) ? (string) $value : json_encode($value));

        Database::query(
            "INSERT INTO settings (setting_key, setting_value, is_encrypted, updated_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = VALUES(is_encrypted), updated_at = NOW()",
            [$key, $storedValue, $isEncrypted ? 1 : 0]
        );
    }

    AuditLogger::admin((int) $admin['id'], 'platform_settings_updated', '', ['keys' => array_keys($data)]);
    Response::success(null, 'Settings saved.');
}

Response::error('Method not allowed', [], 405);
