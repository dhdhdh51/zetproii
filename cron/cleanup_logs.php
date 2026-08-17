<?php
/**
 * cron/cleanup_logs.php
 *
 * Purges old rows from high-volume log tables to keep the database lean
 * on shared hosting (spec #41). Also rotates file-based logs older than
 * the retention window.
 *
 * Suggested cPanel cron: daily
 *   php /home/user/public_html/cron/cleanup_logs.php
 */

require_once __DIR__ . '/_cron_bootstrap.php';

const RETENTION_DAYS_SYSTEM_LOGS = 30;
const RETENTION_DAYS_LOGIN_ATTEMPTS = 30;
const RETENTION_DAYS_CRON_LOGS = 60;
const RETENTION_DAYS_WEBHOOK_LOGS = 90;
const RETENTION_DAYS_AUDIT_LOGS = 365; // audit logs kept much longer for compliance

$ctx = cron_start('cleanup_logs');

try {
    $deleted = 0;

    $deleted += Database::query("DELETE FROM system_logs WHERE created_at < (NOW() - INTERVAL ? DAY)", [RETENTION_DAYS_SYSTEM_LOGS])->rowCount();
    $deleted += Database::query("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL ? DAY)", [RETENTION_DAYS_LOGIN_ATTEMPTS])->rowCount();
    $deleted += Database::query("DELETE FROM cron_logs WHERE started_at < (NOW() - INTERVAL ? DAY)", [RETENTION_DAYS_CRON_LOGS])->rowCount();
    $deleted += Database::query("DELETE FROM webhook_logs WHERE created_at < (NOW() - INTERVAL ? DAY)", [RETENTION_DAYS_WEBHOOK_LOGS])->rowCount();
    $deleted += Database::query("DELETE FROM audit_logs WHERE created_at < (NOW() - INTERVAL ? DAY)", [RETENTION_DAYS_AUDIT_LOGS])->rowCount();

    // Expired password resets / email verifications no longer needed.
    $deleted += Database::query("DELETE FROM password_resets WHERE expires_at < NOW()")->rowCount();
    $deleted += Database::query("DELETE FROM email_verifications WHERE expires_at < (NOW() - INTERVAL 7 DAY)")->rowCount();

    // File-based logs (storage/logs/*.log) older than retention.
    $logsDir = dirname(__DIR__) . '/storage/logs';
    $filesRemoved = 0;
    if (is_dir($logsDir)) {
        foreach (glob($logsDir . '/*.log') as $file) {
            if (filemtime($file) < strtotime('-' . RETENTION_DAYS_SYSTEM_LOGS . ' days')) {
                @unlink($file);
                $filesRemoved++;
            }
        }
    }

    cron_finish($ctx, 'success', "Deleted {$deleted} DB log rows, {$filesRemoved} log files.");
} catch (\Throwable $e) {
    cron_finish($ctx, 'failed', $e->getMessage());
}
