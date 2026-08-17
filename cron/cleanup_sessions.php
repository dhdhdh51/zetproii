<?php
/**
 * cron/cleanup_sessions.php
 *
 * Removes expired PHP session files from storage/sessions (if the app
 * is configured to use file-based sessions there) and prunes very old
 * chat_sessions that were never converted to a lead.
 *
 * Suggested cPanel cron: daily
 *   php /home/user/public_html/cron/cleanup_sessions.php
 */

require_once __DIR__ . '/_cron_bootstrap.php';

$ctx = cron_start('cleanup_sessions');

try {
    $sessionsDir = dirname(__DIR__) . '/storage/sessions';
    $removed = 0;
    $maxAge = (int) config('session.lifetime', 120) * 60; // seconds

    if (is_dir($sessionsDir)) {
        foreach (glob($sessionsDir . '/sess_*') as $file) {
            if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
                @unlink($file);
                $removed++;
            }
        }
    }

    // Close out stale, abandoned chat widget sessions (no activity for 24h, still 'open').
    $stmt = Database::query(
        "UPDATE chat_sessions SET status = 'closed' WHERE status = 'open' AND updated_at < (NOW() - INTERVAL 1 DAY)"
    );

    cron_finish($ctx, 'success', "Removed {$removed} expired session file(s), closed {$stmt->rowCount()} stale chat session(s).");
} catch (\Throwable $e) {
    cron_finish($ctx, 'failed', $e->getMessage());
}
