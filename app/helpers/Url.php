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
     * Marketing pages that are normally reachable at a "pretty" extension-less
     * URL (/features) via the mod_rewrite router. Each one also has a real
     * <route>.php shim at the project root, used when rewriting is unavailable.
     */
    private const PRETTY_ROUTES = [
        'features', 'pricing', 'about', 'contact',
        'blog', 'privacy', 'terms', 'refund-policy',
    ];

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
        if (str_ends_with($scriptName, '/public/index.php')) {
            return self::$basePath = rtrim(substr($scriptName, 0, -strlen('/public/index.php')), '/');
        }
        // Any entry point sitting directly at the project root - index.php,
        // install.php, diagnose.php, features.php and the other page shims -
        // means the directory part of SCRIPT_NAME *is* the base path.
        if (preg_match('#^(.*)/[^/]+\.php$#', $scriptName, $m) && !str_contains(trim($m[1], '/'), '/')) {
            $head = rtrim($m[1], '/');
            if (!preg_match('#/(auth|dashboard|admin|api|cron|public)$#', $head)) {
                return self::$basePath = $head;
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
     * Is our .htaccess actually being honoured by the web server?
     *
     * This is a real runtime probe, not a guess: .htaccess sets
     * `SetEnv HTACCESS_ACTIVE 1`, so the variable is present only when the
     * file both exists AND is processed. It comes back unset in the two cases
     * that used to silently break the site:
     *   - .htaccess was never uploaded (cPanel File Manager hides dotfiles by
     *     default, and most FTP clients skip them)
     *   - the host runs AllowOverride None, so .htaccess is ignored
     * Apache prefixes the variable with REDIRECT_ after an internal rewrite,
     * so both spellings are accepted.
     */
    public static function rewriteActive(): bool
    {
        return isset($_SERVER['HTACCESS_ACTIVE']) || isset($_SERVER['REDIRECT_HTACCESS_ACTIVE']);
    }

    /**
     * Builds an app URL from a root-relative path.
     * url('auth/login.php') => '/zetpro-main/auth/login.php' (or '/auth/login.php')
     *
     * Extension-less marketing routes ('features') only resolve through the
     * mod_rewrite router, so when rewriting is unavailable they are pointed at
     * their real shim file ('features.php') instead of producing a dead link.
     */
    public static function url(string $path = ''): string
    {
        $path = ltrim($path, '/');

        if ($path !== '' && in_array($path, self::PRETTY_ROUTES, true) && !self::rewriteActive()) {
            $path .= '.php';
        }

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
        $relative = ltrim($path, '/');
        $url = self::url('public/assets/' . $relative);

        // Cache-bust with the file's own modification time.
        //
        // WHY: hosts serve these with a long Cache-Control (LiteSpeed shared
        // hosting sends max-age=604800 - a week). With a fixed URL, a visitor
        // who had loaded the site before kept their stale stylesheet after a
        // deploy and saw a half-styled page, while the server was serving the
        // correct file all along. Only an incognito window looked right.
        //
        // Keying on mtime means the URL changes exactly when the file does: new
        // deploys invalidate immediately, and unchanged files stay cached.
        $file = dirname(__DIR__, 2) . '/public/assets/' . $relative;
        $mtime = @filemtime($file);
        if ($mtime !== false) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $mtime;
        }

        return $url;
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
