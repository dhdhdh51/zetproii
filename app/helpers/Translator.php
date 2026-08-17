<?php
/**
 * Minimal i18n helper (spec #43). UI strings live in /lang/{locale}/*.php
 * files returning a flat key => value array, never hardcoded into PHP
 * logic. Falls back to English, then to the raw key, so missing
 * translations never crash a page.
 */
final class Translator
{
    private static array $cache = [];

    public static function get(string $key, string $locale = 'en', array $replacements = []): string
    {
        $strings = self::load($locale);
        $value = $strings[$key] ?? self::load('en')[$key] ?? $key;

        foreach ($replacements as $k => $v) {
            $value = str_replace(':' . $k, (string) $v, $value);
        }

        return $value;
    }

    private static function load(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $file = dirname(__DIR__, 2) . "/lang/{$locale}/common.php";
        self::$cache[$locale] = is_file($file) ? (require $file) : [];
        return self::$cache[$locale];
    }
}

if (!function_exists('t')) {
    function t(string $key, array $replacements = []): string
    {
        $locale = $_SESSION['locale'] ?? config('app.locale', 'en');
        return Translator::get($key, $locale, $replacements);
    }
}
