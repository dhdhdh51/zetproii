<?php
/**
 * Minimal, dependency-free .env loader.
 * Parses KEY=VALUE lines from a .env file into $_ENV / $_SERVER / getenv().
 * No Composer package required so the app can run on any bare PHP host.
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            // In production, missing .env is a hard error - fail closed, not open.
            if ((getenv('APP_ENV') ?: 'production') === 'production') {
                error_log("[BharatAI] Missing .env file at {$path}");
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

            if (getenv($name) === false) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
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
