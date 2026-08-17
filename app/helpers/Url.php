<?php
/**
 * URL / base-path helper.
 *
 * WHY THIS EXISTS: hardcoding absolute paths like "/assets/css/app.css"
 * only works when the project sits exactly at the web root. It silently
 * breaks when the app is installed in a subfolder (e.g. extracting
 * zetpro-main.zip into public_html/ gives public_html/zetpro-main/, so
 * "/assets/..." points outside the app and every stylesheet 404s -
 * leaving a completely unstyled site).
 *
 * These helpers detect the app's real base path at runtime by comparing
 * the project directory against the web server's document root, so every
 * generated URL is correct whether the app is at the domain root, in a
 * subfolder, or on a subdomain. The detection can be overridden with
 * BASE_PATH in .env if a host reports misleading values.
 */
final class Url
{
    private static ?string $basePath = null;

    /**
     * The URL path prefix the app is served under.
     * Returns '' at the domain root, or e.g. '/zetpro-main' in a subfolder.
     */
    public static function basePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        // 1. Explicit override always wins.
        $configured = (string) config('app.base_path', '');
        if ($configured !== '') {
            return self::$basePath = '/' . trim($configured, '/');
        }

        // 2. Derive from the filesystem: how far below the document root
        //    does the project directory sit?
        $projectRoot = dirname(__DIR__, 2); // .../<project>
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';

        if ($docRoot !== '') {
            $realProject = realpath($projectRoot) ?: $projectRoot;
            $realDocRoot = realpath($docRoot) ?: $docRoot;

            // Normalise separators for cross-platform safety.
            $realProject = str_replace('\\', '/', $realProject);
            $realDocRoot = str_replace('\\', '/', $realDocRoot);

            if ($realProject === $realDocRoot) {
                return self::$basePath = '';
            }
            if (str_starts_with($realProject, rtrim($realDocRoot, '/') . '/')) {
                $suffix = substr($realProject, strlen(rtrim($realDocRoot, '/')));
                return self::$basePath = '/' . trim($suffix, '/');
            }
        }

        // 3. Fall back to deriving it from the running script's URL. Each
        //    entry point lives a known depth below the project root.
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        foreach (['/public/index.php', '/install.php', '/diagnose.php'] as $known) {
            if (str_ends_with($scriptName, $known)) {
                return self::$basePath = rtrim(substr($scriptName, 0, -strlen($known)), '/');
            }
        }
        // /auth/x.php, /dashboard/x.php, /admin/x.php, /api/a/b.php, /cron/x.php
        if (preg_match('#^(.*)/(auth|dashboard|admin|cron)/[^/]+\.php$#', $scriptName, $m)) {
            return self::$basePath = rtrim($m[1], '/');
        }
        if (preg_match('#^(.*)/api/[^/]+/[^/]+\.php$#', $scriptName, $m)) {
            return self::$basePath = rtrim($m[1], '/');
        }

        return self::$basePath = '';
    }

    /**
     * Builds an app URL from a root-relative path.
     * url('auth/login.php') => '/zetpro-main/auth/login.php' (or '/auth/login.php')
     */
    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $base = self::basePath();
        return $base . ($path === '' ? '/' : '/' . $path);
    }

    /**
     * Builds a static asset URL.
     *
     * Points at the asset's REAL physical location (public/assets/...) so
     * it is served directly by the web server as an existing file. That
     * means assets keep working even if the /assets/ -> public/assets/
     * rewrite in .htaccess is unavailable (AllowOverride None, nginx
     * without the mapping, etc.) - removing the single point of failure
     * that previously made the whole site render unstyled.
     */
    public static function asset(string $path): string
    {
        return self::url('public/assets/' . ltrim($path, '/'));
    }

    /** Absolute URL (scheme + host + path), used for emails, sitemap, OG tags. */
    public static function absolute(string $path = ''): string
    {
        $configured = rtrim((string) config('app.url', ''), '/');
        if ($configured !== '') {
            $path = ltrim($path, '/');
            return $configured . ($path === '' ? '' : '/' . $path);
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . self::url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return Url::asset($path);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return Url::url($path);
    }
}
