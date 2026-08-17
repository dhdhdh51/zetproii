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

    /** True while a log line is being written to the database - see log(). */
    private static bool $persisting = false;

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
        //
        // The guard makes this non-reentrant. Persisting a log line touches the
        // database, and anything that fails down there logs - which would come
        // straight back here and try to persist again. That cycle (connection
        // failure -> log -> connect -> failure) once recursed until PHP hit its
        // memory limit and returned an empty 500. The file write above always
        // happens, so nothing is lost by skipping the nested DB write.
        if (self::$persisting) {
            return;
        }

        self::$persisting = true;
        try {
            if (class_exists('Database') && $channel !== 'system_bootstrap') {
                Database::query(
                    "INSERT INTO system_logs (channel, level, message, context, created_at) VALUES (?, ?, ?, ?, NOW())",
                    [$channel, $level, $message, json_encode($context, JSON_UNESCAPED_SLASHES)]
                );
            }
        } catch (\Throwable $e) {
            // swallow - file log above already captured it
        } finally {
            self::$persisting = false;
        }
    }

    public static function app(string $message, array $context = []): void
    {
        self::log('app', 'info', $message, $context);
    }

    /**
     * A system-level FAILURE (DB unreachable, uncaught exception, PHP error).
     * Logged at 'error' so it shows up in Admin > System Logs error filters.
     * For things that are merely worth recording, use systemInfo().
     */
    public static function system(string $message, array $context = []): void
    {
        self::log('system', 'error', $message, $context);
    }

    /**
     * A notable system EVENT that is not a failure - e.g. an account being
     * activated automatically because the server cannot send email. Kept at
     * 'info' so it stays out of error reports while remaining auditable.
     */
    public static function systemInfo(string $message, array $context = []): void
    {
        self::log('system', 'info', $message, $context);
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
