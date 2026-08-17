<?php
/**
 * Project-root front door.
 * =====================================================================
 * WHY THIS FILE EXISTS
 *
 * The document root for this app is the project root (so that /auth,
 * /dashboard, /admin and /api are directly reachable), while the marketing
 * site's router lives at /public/index.php. That meant the homepage was
 * reachable *only* through the `RewriteRule ^$ public/index.php` line in
 * .htaccess - a single point of failure. If .htaccess was missing or
 * ignored, Apache looked for a DirectoryIndex at the project root, found
 * nothing, and answered "/" with 403/404. That happens routinely:
 *
 *   - cPanel's File Manager hides dotfiles, and most FTP clients skip
 *     them, so .htaccess frequently never gets uploaded at all
 *   - some shared hosts run AllowOverride None, ignoring .htaccess
 *
 * Having a real index.php here means Apache's own DirectoryIndex serves
 * the homepage natively, with no rewrite rules involved. The homepage now
 * works on any PHP host, in a subfolder or at the domain root.
 * =====================================================================
 */

declare(strict_types=1);

// Work out which marketing route was requested. When .htaccess IS active the
// rewrite hands us ?route=... directly; when it isn't, we're being served as
// the DirectoryIndex and have to derive the route from the request URI.
if (!isset($_GET['route'])) {
    $uri = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');

    // Strip the base path (the folder this file lives in, as the browser sees
    // it), so a subfolder install resolves "/zetpro-main/" to the home route.
    $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    if ($base !== '' && $base !== '.' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }

    $route = trim($uri, '/');

    // A direct hit on /index.php is the homepage, not a route named "index.php".
    if ($route === 'index.php') {
        $route = '';
    }

    $_GET['route'] = $route;
}

require __DIR__ . '/public/index.php';
