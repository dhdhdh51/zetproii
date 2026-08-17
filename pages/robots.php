<?php
/** Dynamic robots.txt - disallows app/admin areas, allows marketing pages. */
header('Content-Type: text/plain; charset=utf-8');
$base = rtrim((string) config('app.url'), '/');
?>
User-agent: *
Allow: /
Disallow: /api/
Disallow: /admin/
Disallow: /dashboard/
Disallow: /auth/
Disallow: /cron/
Disallow: /storage/

Sitemap: <?= $base ?>/sitemap.xml
