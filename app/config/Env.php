<?php
/**
 * Minimal, dependency-free .env loader.
 * Parses KEY=VALUE lines from a .env file into $_ENV / $_SERVER / getenv().
 * No Composer package required so the app can run on any bare PHP host.
 */
final class Env
{
    /** Paths already processed, so repeated loads are cheap and idempotent. */
    private static array $loaded = [];

    /** True once any source actually supplied configuration. */
    private static bool $haveConfig = false;

    /**
     * Loads configuration from a PHP file that returns an associative array.
     *
     * PREFER THIS OVER .env. A plain .env file is static text, so if the
     * project's .htaccess is missing or ignored the web server will happily
     * serve it - handing out the database password and APP_KEY to anyone who
     * requests /.env. A .php file is always executed instead of echoed, so
     * requesting /env.php directly returns nothing, on every host, with no
     * .htaccess required.
     */
    public static function loadPhp(string $path): void
    {
        if (isset(self::$loaded[$path]) || !is_file($path)) {
            return;
        }
        self::$loaded[$path] = true;

        $values = require $path;
        if (!is_array($values)) {
            error_log("[BharatSEO] {$path} must return an array of config values");
            return;
        }

        foreach ($values as $name => $value) {
            self::put((string) $name, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
        }
        self::$haveConfig = true;
    }

    /** Did any configuration source (env.php or .env) actually load? */
    public static function isConfigured(): bool
    {
        return self::$haveConfig;
    }

    private static function put(string $name, string $value): void
    {
        if ($name === '' || getenv($name) !== false) {
            return; // never overwrite an already-set value: first source wins
        }
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    public static function load(string $path): void
    {
        if (isset(self::$loaded[$path])) {
            return;
        }
        self::$loaded[$path] = true;

        if (!is_file($path)) {
            // Only a problem if nothing else supplied config either.
            if (!self::$haveConfig && (getenv('APP_ENV') ?: 'production') === 'production') {
                error_log("[BharatSEO] No configuration found (looked for env.php and {$path})");
            }
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Strip surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if ($name === '') {
                continue;
            }

            self::put($name, $value);
            self::$haveConfig = true;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        // Convert common boolean-like strings
        $lower = strtolower((string) $value);
        if (in_array($lower, ['true', '(true)'], true)) {
            return true;
        }
        if (in_array($lower, ['false', '(false)'], true)) {
            return false;
        }
        if ($lower === 'null' || $lower === '(null)') {
            return null;
        }

        return $value;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $val = self::get($key);
        return $val === null || $val === '' ? $default : (int) $val;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::get($key);
        if ($val === null || $val === '') {
            return $default;
        }
        return (bool) $val;
    }
}
