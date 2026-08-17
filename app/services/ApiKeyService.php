<?php
/**
 * API key lifecycle management (spec #30). The raw key is shown to the
 * user exactly once at creation time - only its hash is stored, matching
 * standard practice for API credentials.
 */
final class ApiKeyService
{
    public function list(int $businessId): array
    {
        return Database::fetchAll(
            "SELECT id, name, key_prefix, permissions, expires_at, last_used_at, revoked_at, created_at
             FROM api_keys WHERE business_id = ? ORDER BY created_at DESC",
            [$businessId]
        );
    }

    public function create(int $businessId, string $name, array $permissions, ?string $expiresAt, int $userId): array
    {
        $rawKey = Security::generateApiKey();
        $prefix = substr($rawKey, 0, 12);
        $hash = hash('sha256', $rawKey);

        Database::query(
            "INSERT INTO api_keys (business_id, name, key_prefix, key_hash, permissions, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$businessId, Security::cleanString($name), $prefix, $hash, json_encode($permissions), $expiresAt]
        );
        $id = (int) Database::lastInsertId();

        AuditLogger::log($userId, $businessId, 'api_key_created', ['api_key_id' => $id]);

        return ['id' => $id, 'raw_key' => $rawKey, 'name' => $name]; // raw_key shown ONCE
    }

    public function revoke(int $businessId, int $id, int $userId): void
    {
        Database::query("UPDATE api_keys SET revoked_at = NOW() WHERE id = ? AND business_id = ?", [$id, $businessId]);
        AuditLogger::log($userId, $businessId, 'api_key_revoked', ['api_key_id' => $id]);
    }

    // ---------------- Webhooks ----------------

    public function listWebhooks(int $businessId): array
    {
        return Database::fetchAll("SELECT * FROM webhooks WHERE business_id = ? ORDER BY created_at DESC", [$businessId]);
    }

    public function createWebhook(int $businessId, string $targetUrl, array $events, int $userId): array
    {
        if (!filter_var($targetUrl, FILTER_VALIDATE_URL) || !str_starts_with($targetUrl, 'https://')) {
            Response::validationError(['target_url' => ['Webhook URL must be a valid HTTPS URL.']]);
        }

        $secret = Security::randomToken(24);
        Database::query(
            "INSERT INTO webhooks (business_id, target_url, events, secret, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())",
            [$businessId, $targetUrl, json_encode($events), $secret]
        );
        $id = (int) Database::lastInsertId();

        AuditLogger::log($userId, $businessId, 'webhook_created', ['webhook_id' => $id]);

        return Database::fetchOne("SELECT * FROM webhooks WHERE id = ?", [$id]);
    }

    public function deleteWebhook(int $businessId, int $id): void
    {
        Database::query("DELETE FROM webhooks WHERE id = ? AND business_id = ?", [$id, $businessId]);
    }

    /**
     * Dispatches a webhook event to all active webhooks subscribed to it,
     * signing the payload with HMAC-SHA256 using the webhook's secret.
     * Called by AutomationService/services on business events, and
     * retried by cron/process_webhooks.php on failure.
     */
    public static function dispatch(int $businessId, string $event, array $payload): void
    {
        $webhooks = Database::fetchAll(
            "SELECT * FROM webhooks WHERE business_id = ? AND is_active = 1",
            [$businessId]
        );

        foreach ($webhooks as $webhook) {
            $events = json_decode($webhook['events'], true) ?: [];
            if (!in_array($event, $events, true)) {
                continue;
            }

            $body = json_encode(['event' => $event, 'data' => $payload, 'timestamp' => time()]);
            $signature = hash_hmac('sha256', $body, $webhook['secret']);

            Database::query(
                "INSERT INTO webhook_logs (webhook_id, event, payload, attempt, status, created_at) VALUES (?, ?, ?, 1, 'pending', NOW())",
                [$webhook['id'], $event, $body]
            );
            $logId = (int) Database::lastInsertId();

            $result = HttpClient::postJson($webhook['target_url'], json_decode($body, true), [
                'X-BharatSEO-Signature: ' . $signature,
                'X-BharatSEO-Event: ' . $event,
            ], 10);

            Database::query(
                "UPDATE webhook_logs SET response_code = ?, response_body = ?, status = ? WHERE id = ?",
                [$result['status'], substr($result['body'], 0, 1000), ($result['status'] >= 200 && $result['status'] < 300) ? 'success' : 'failed', $logId]
            );
        }
    }
}
