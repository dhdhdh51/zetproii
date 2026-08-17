<?php
/**
 * Ensures a request comes from an authenticated user (session-based for
 * the web app, or Bearer API-key based for /api/v1 external calls).
 * Never trusts user_id/business_id sent from the frontend - always
 * resolves identity from the session or a verified API key.
 */
final class AuthMiddleware
{
    /** Resolves and returns the authenticated user row, or halts with 401. */
    public static function user(): array
    {
        if (empty($_SESSION['user_id'])) {
            Response::unauthorized('Please log in to continue.');
        }

        $user = Database::fetchOne(
            "SELECT id, uuid, name, email, role, status, onboarding_completed FROM users WHERE id = ? AND deleted_at IS NULL",
            [$_SESSION['user_id']]
        );

        if ($user === null || $user['status'] !== 'active') {
            session_destroy();
            Response::unauthorized('Your session is no longer valid. Please log in again.');
        }

        return $user;
    }

    /** Same as user() but returns null instead of halting - for optional-auth endpoints. */
    public static function optionalUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return Database::fetchOne(
            "SELECT id, uuid, name, email, role, status, onboarding_completed FROM users WHERE id = ? AND deleted_at IS NULL",
            [$_SESSION['user_id']]
        );
    }

    public static function requireRole(array $allowedRoles): array
    {
        $user = self::user();
        if (!in_array($user['role'], $allowedRoles, true)) {
            Response::forbidden('You do not have permission to perform this action.');
        }
        return $user;
    }

    /**
     * Verifies the requesting user belongs to $businessId (via ownership or
     * business_members) and returns their membership role within it.
     * This is the ONLY correct way to authorize business-scoped requests -
     * business_id from the request body/query must always be re-checked here.
     */
    public static function requireBusinessAccess(int $userId, int $businessId): string
    {
        $owner = Database::fetchOne(
            "SELECT id FROM businesses WHERE id = ? AND owner_id = ? AND deleted_at IS NULL",
            [$businessId, $userId]
        );
        if ($owner !== null) {
            return 'BUSINESS_OWNER';
        }

        $member = Database::fetchOne(
            "SELECT role FROM business_members WHERE business_id = ? AND user_id = ? AND status = 'active'",
            [$businessId, $userId]
        );
        if ($member !== null) {
            return $member['role'];
        }

        Logger::security('Unauthorized business access attempt', [
            'user_id' => $userId,
            'business_id' => $businessId,
            'ip' => Security::clientIp(),
        ]);
        Response::forbidden('You do not have access to this business.');
    }

    /** For API key authenticated requests (external API consumers). */
    public static function apiKeyBusiness(): array
    {
        $request = new Request();
        $token = $request->bearerToken();
        if ($token === null || !str_starts_with($token, 'bak_')) {
            Response::unauthorized('Missing or invalid API key.');
        }

        $prefix = substr($token, 0, 12);
        $keyHash = hash('sha256', $token);

        // Expiry is evaluated by MySQL (is_expired) rather than compared in PHP,
        // so a PHP/MySQL timezone difference can never wrongly accept or reject
        // a key.
        $row = Database::fetchOne(
            "SELECT ak.id, ak.business_id, ak.permissions, ak.expires_at, ak.revoked_at,
                    (ak.expires_at IS NOT NULL AND ak.expires_at <= NOW()) AS is_expired,
                    b.status AS business_status
             FROM api_keys ak JOIN businesses b ON b.id = ak.business_id
             WHERE ak.key_prefix = ? AND ak.key_hash = ?",
            [$prefix, $keyHash]
        );

        if ($row === null || $row['revoked_at'] !== null || $row['business_status'] !== 'active') {
            Response::unauthorized('API key is invalid or revoked.');
        }

        if ((int) $row['is_expired'] === 1) {
            Response::unauthorized('API key has expired.');
        }

        Database::query("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?", [$row['id']]);

        return $row;
    }
}
