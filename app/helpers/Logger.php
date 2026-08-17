<?php
/**
 * Simple file-based multi-channel logger.
 * Writes JSON-lines to storage/logs/{channel}-{date}.log
 * Channels map to the system_logs table concept but also persist to disk
 * so logging still works if the DB connection itself is what failed.
 */
final class Logger
{
    private const CHANNELS = ['app', 'system', 'ai', 'email', 'payment', 'webhook', 'security', 'cron'];

    public static function log(string $channel, string $level, string $message, array $context = []): void
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            $channel = 'app';
        }

        $dir = self::baseDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . $channel . '-' . date('Y-m-d') . '.log';

        $entry = [
            'time'    => date('Y-m-d H:i:s'),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];

        @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Best-effort DB persistence for admin-visible logs. Never let a
        // logging failure break the request.
        try {
            if (class_exists('Database') && $channel !== 'system_bootstrap') {
                Database::query(
                    "INSERT INTO system_logs (channel, level, message, context, created_at) VALUES (?, ?, ?, ?, NOW())",
                    [$channel, $level, $message, json_encode($context, JSON_UNESCAPED_SLASHES)]
                );
            }
        } catch (\Throwable $e) {
            // swallow - file log above already captured it
        }
    }

    public static function app(string $message, array $context = []): void
    {
        self::log('app', 'info', $message, $context);
    }

    public static function system(string $message, array $context = []): void
    {
        self::log('system', 'error', $message, $context);
    }

    public static function ai(string $message, array $context = []): void
    {
        self::log('ai', 'info', $message, $context);
    }

    public static function email(string $message, array $context = []): void
    {
        self::log('email', 'info', $message, $context);
    }

    public static function payment(string $message, array $context = []): void
    {
        self::log('payment', 'info', $message, $context);
    }

    public static function webhook(string $message, array $context = []): void
    {
        self::log('webhook', 'info', $message, $context);
    }

    public static function security(string $message, array $context = []): void
    {
        self::log('security', 'warning', $message, $context);
    }

    public static function cron(string $message, array $context = []): void
    {
        self::log('cron', 'info', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('app', 'error', $message, $context);
    }

    private static function baseDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs';
    }
}
