<?php
/**
 * Public marketing site front controller.
 * Maps clean URLs (/, /features, /pricing, ...) to page templates in
 * /pages. This file only serves the marketing site - the app (auth,
 * dashboard, admin, api) are separate top-level folders with their own
 * PHP entry points, matching the required project structure.
 */

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

$route = trim((string) ($_GET['route'] ?? ''), '/');
if ($route === '') {
    $route = 'home';
}

// ---------------------------------------------------------------------
// Static asset safety net.
//
// Assets are normally served directly by the web server (either from
// their real path under /public/assets/..., or via the /assets/ ->
// public/assets/ rewrite in .htaccess). If neither applies - e.g. the
// host has AllowOverride None so .htaccess is ignored - the request
// lands here instead, and previously fell through to the 404 page,
// which made the entire site render with no styling at all.
//
// Serving it here guarantees CSS/JS/images always load, on any host.
// ---------------------------------------------------------------------
if (preg_match('#^(?:public/)?(assets/.+)$#', $route, $m)) {
    $relative = $m[1];

    // Block traversal, then resolve strictly inside public/assets.
    if (!str_contains($relative, '..')) {
        $assetsRoot = __DIR__ . '/assets';
        $candidate = realpath(__DIR__ . '/' . $relative);

        if ($candidate !== false && str_starts_with($candidate, realpath($assetsRoot) . DIRECTORY_SEPARATOR) && is_file($candidate)) {
            $types = [
                'css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
                'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
                'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
            ];
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            if (isset($types[$ext])) {
                header('Content-Type: ' . $types[$ext] . '; charset=utf-8');
                header('Content-Length: ' . filesize($candidate));
                header('Cache-Control: public, max-age=86400');
                header('X-Asset-Served-By: php-fallback');
                readfile($candidate);
                exit;
            }
        }
    }

    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Asset not found';
    exit;
}

$pagesDir = dirname(__DIR__) . '/pages';

$routeMap = [
    'home'           => 'home.php',
    'features'       => 'features.php',
    'pricing'        => 'pricing.php',
    'about'          => 'about.php',
    'contact'        => 'contact.php',
    'blog'           => 'blog.php',
    'privacy'        => 'privacy.php',
    'terms'          => 'terms.php',
    'refund-policy'  => 'refund-policy.php',
    'sitemap.xml'    => 'sitemap.php',
    'robots.txt'     => 'robots.php',
];

$file = $routeMap[$route] ?? null;

if ($file === null || !is_file($pagesDir . '/' . $file)) {
    http_response_code(404);
    $notFoundFile = $pagesDir . '/404.php';
    if (is_file($notFoundFile)) {
        require $notFoundFile;
    } else {
        echo '404 Not Found';
    }
    exit;
}

require $pagesDir . '/' . $file;
