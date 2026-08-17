<?php
/**
 * cron/process_webhooks.php
 *
 * Retries failed webhook deliveries with exponential backoff, up to a
 * maximum of 5 attempts (spec #31: "Implement retry mechanism").
 *
 * Suggested cPanel cron: every 10 minutes
 *   php /home/user/public_html/cron/process_webhooks.php
 */

require_once __DIR__ . '/_cron_bootstrap.php';

const MAX_WEBHOOK_ATTEMPTS = 5;

$ctx = cron_start('process_webhooks');

try {
    $retried = 0;

    // Retry failed logs where the backoff window has elapsed:
    // attempt 1 -> wait 2 min, attempt 2 -> wait 8 min, attempt 3 -> wait 30 min, etc.
    $failedLogs = Database::fetchAll(
        "SELECT wl.*, w.target_url, w.secret, w.is_active FROM webhook_logs wl
         JOIN webhooks w ON w.id = wl.webhook_id
         WHERE wl.status = 'failed' AND wl.attempt < ? AND w.is_active = 1
           AND wl.created_at <= (NOW() - INTERVAL POW(4, wl.attempt) MINUTE)
         LIMIT 100",
        [MAX_WEBHOOK_ATTEMPTS]
    );

    foreach ($failedLogs as $log) {
        $signature = hash_hmac('sha256', $log['payload'], $log['secret']);
        $result = HttpClient::postJson($log['target_url'], json_decode($log['payload'], true) ?: [], [
            'X-BharatAI-Signature: ' . $signature,
            'X-BharatAI-Event: ' . $log['event'],
        ], 10);

        $success = $result['status'] >= 200 && $result['status'] < 300;

        Database::query(
            "INSERT INTO webhook_logs (webhook_id, event, payload, response_code, response_body, attempt, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $log['webhook_id'], $log['event'], $log['payload'], $result['status'],
                substr($result['body'], 0, 1000), $log['attempt'] + 1, $success ? 'success' : 'failed',
            ]
        );
        $retried++;
    }

    cron_finish($ctx, 'success', "Retried {$retried} failed webhook delivery(ies).");
} catch (\Throwable $e) {
    cron_finish($ctx, 'failed', $e->getMessage());
}
