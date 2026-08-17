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
