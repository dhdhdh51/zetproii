<?php
/**
 * Checks a user's effective permissions within a specific business,
 * based on their business_members.role (or ownership) mapped through
 * roles -> role_permissions -> permissions.
 */
final class PermissionMiddleware
{
    private const ROLE_SLUG_MAP = [
        'BUSINESS_OWNER' => 'business_owner',
        'MANAGER'        => 'manager',
        'STAFF'          => 'staff',
        'AGENCY_OWNER'   => 'agency_owner',
        'AGENCY_STAFF'   => 'agency_staff',
        'ADMIN'          => 'admin',
        'SUPER_ADMIN'    => 'super_admin',
    ];

    /**
     * @return bool true if $businessRole grants $permissionSlug
     */
    public static function roleHasPermission(string $businessRole, string $permissionSlug): bool
    {
        $slug = self::ROLE_SLUG_MAP[$businessRole] ?? null;
        if ($slug === null) {
            return false;
        }

        $row = Database::fetchOne(
            "SELECT 1 FROM role_permissions rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.slug = ? AND p.slug = ? LIMIT 1",
            [$slug, $permissionSlug]
        );

        return $row !== null;
    }

    /**
     * Halts the request with 403 if the given business role lacks the
     * required permission. SUPER_ADMIN/ADMIN (platform roles) always pass.
     */
    public static function require(string $businessRole, string $permissionSlug): void
    {
        if (in_array($businessRole, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return;
        }

        if (!self::roleHasPermission($businessRole, $permissionSlug)) {
            Logger::security('Permission denied', [
                'role' => $businessRole,
                'permission' => $permissionSlug,
                'ip' => Security::clientIp(),
            ]);
            Response::forbidden("You don't have permission to: {$permissionSlug}");
        }
    }
}
