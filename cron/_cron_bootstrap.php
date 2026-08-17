<?php
/**
 * Shared bootstrap for all cron scripts. Supports two invocation modes:
 *   1. CLI (php cron/run_automations.php) - typical cPanel cron setup.
 *   2. HTTP (https://yourdomain.com/cron/run_automations.php?key=...) -
 *      for hosts where only HTTP-triggered cron is available. Protected
 *      by CRON_SECRET (spec #18/#41: "Protect cron endpoint with a
 *      secret key if accessed over HTTP").
 *
 * Every job's start/end/result is logged to cron_logs.
 */

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

function cron_guard(): void
{
    $isCli = PHP_SAPI === 'cli';
    if ($isCli) {
        return;
    }

    $secret = (string) config('cron.secret', '');
    $provided = $_GET['key'] ?? '';

    if ($secret === '' || !hash_equals($secret, (string) $provided)) {
        http_response_code(403);
        echo "Forbidden.";
        exit;
    }
}

function cron_start(string $jobName): array
{
    $startedAt = date('Y-m-d H:i:s');
    return ['job_name' => $jobName, 'started_at' => $startedAt, 'start_time' => microtime(true)];
}

function cron_finish(array $ctx, string $status, string $output = ''): void
{
    $durationMs = (int) ((microtime(true) - $ctx['start_time']) * 1000);
    Database::query(
        "INSERT INTO cron_logs (job_name, status, output, started_at, finished_at, duration_ms) VALUES (?, ?, ?, ?, NOW(), ?)",
        [$ctx['job_name'], $status, $output, $ctx['started_at'], $durationMs]
    );
    Logger::cron("{$ctx['job_name']} finished: {$status}", ['duration_ms' => $durationMs, 'output' => $output]);
    echo "[{$ctx['job_name']}] {$status} ({$durationMs}ms): {$output}\n";
}

cron_guard();
