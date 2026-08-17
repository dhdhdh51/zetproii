<?php
/**
 * Persists sensitive-action audit trail entries to the audit_logs table.
 * Used for login/logout, password changes, plan changes, payments,
 * API key creation, AI provider changes, settings changes, deletions,
 * and admin actions - per the security/compliance requirements.
 */
final class AuditLogger
{
    public static function log(?int $userId, ?int $businessId, string $action, array $metadata = []): void
    {
        try {
            Database::query(
                "INSERT INTO audit_logs (user_id, business_id, action, ip_address, user_agent, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $userId,
                    $businessId,
                    $action,
                    Security::clientIp(),
                    Security::userAgent(),
                    json_encode($metadata, JSON_UNESCAPED_SLASHES),
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('AuditLogger failed: ' . $e->getMessage());
        }
    }

    public static function admin(?int $adminUserId, string $action, string $description = '', array $metadata = []): void
    {
        try {
            Database::query(
                "INSERT INTO admin_logs (admin_user_id, action, description, metadata, ip_address, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$adminUserId, $action, $description, json_encode($metadata, JSON_UNESCAPED_SLASHES), Security::clientIp()]
            );
        } catch (\Throwable $e) {
            Logger::error('AuditLogger::admin failed: ' . $e->getMessage());
        }
    }
}
