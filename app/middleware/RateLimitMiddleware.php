<?php
/**
 * Simple sliding-window rate limiter backed by the database (works on
 * shared hosting without Redis/Memcached). Used for login attempts and
 * general API throttling.
 */
final class RateLimitMiddleware
{
    /**
     * Throttle login attempts per email+IP combination.
     * Halts with 429 if the limit has been exceeded.
     */
    public static function checkLogin(string $email, string $ip): void
    {
        $maxAttempts = (int) config('auth.max_login_attempts', 5);
        $lockoutMinutes = (int) config('auth.lockout_minutes', 15);

        $row = Database::fetchOne(
            "SELECT COUNT(*) AS attempts FROM login_attempts
             WHERE (email = ? OR ip_address = ?) AND success = 0
               AND created_at > (NOW() - INTERVAL ? MINUTE)",
            [$email, $ip, $lockoutMinutes]
        );

        if ($row !== null && (int) $row['attempts'] >= $maxAttempts) {
            Logger::security('Login rate limit exceeded', ['email' => $email, 'ip' => $ip]);
            Response::rateLimited("Too many failed login attempts. Please try again in {$lockoutMinutes} minutes.");
        }
    }

    public static function recordLoginAttempt(string $email, string $ip, bool $success): void
    {
        Database::query(
            "INSERT INTO login_attempts (email, ip_address, success, created_at) VALUES (?, ?, ?, NOW())",
            [$email, $ip, $success ? 1 : 0]
        );
    }

    /**
     * General purpose per-key rate limit using a rolling window stored
     * in a lightweight file cache (storage/cache) - avoids adding load
     * to the DB for high-frequency API endpoints.
     */
    public static function throttle(string $key, int $maxRequests, int $windowSeconds): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.json';

        $now = time();
        $hits = [];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $hits = $decoded;
            }
        }

        // Drop hits outside the window
        $hits = array_values(array_filter($hits, fn ($t) => $t > $now - $windowSeconds));

        if (count($hits) >= $maxRequests) {
            Response::rateLimited('Rate limit exceeded. Please slow down.');
        }

        $hits[] = $now;
        @file_put_contents($file, json_encode($hits));
    }
}
