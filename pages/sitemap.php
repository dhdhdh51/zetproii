<?php
/** Dynamic sitemap.xml - lists all public marketing routes. */
header('Content-Type: application/xml; charset=utf-8');
$base = rtrim((string) config('app.url'), '/');
$routes = ['', 'features', 'pricing', 'about', 'contact', 'blog', 'privacy', 'terms', 'refund-policy'];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($routes as $route) {
    $url = $base . '/' . $route;
    echo "  <url><loc>" . htmlspecialchars(rtrim($url, '/') . ($route === '' ? '/' : '')) . "</loc></url>\n";
}
echo '</urlset>';
