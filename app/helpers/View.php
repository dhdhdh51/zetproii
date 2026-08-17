<?php
/**
 * Minimal server-side view renderer for marketing pages and app shells.
 * Not a templating DSL - just plain PHP includes with output buffering,
 * which keeps the stack dependency-free (no Twig/Blade needed).
 */
final class View
{
    public static function render(string $viewPath, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $viewPath;
        return (string) ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return Security::escape($value);
    }
}
