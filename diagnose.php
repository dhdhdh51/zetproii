<?php
/**
 * BharatAI Business OS — Installation Diagnostic
 *
 * Visit this page in a browser if anything looks wrong (unstyled pages,
 * 404s, database errors). It reports exactly what the server is doing so
 * you don't have to guess.
 *
 * SECURITY: delete this file once your install is healthy. It only ever
 * reports status/paths - it never prints passwords, API keys or the
 * contents of your .env.
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

$rows = [];
$fail = 0;
$warnCount = 0;

function row(string $label, bool|string $status, string $detail = ''): void
{
    global $rows, $fail, $warnCount;
    if ($status === 'warn') {
        $warnCount++;
    } elseif ($status === false) {
        $fail++;
    }
    $rows[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
}

// ---------------------------------------------------------------- PHP
row('PHP version ' . PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>='), 'Requires PHP 8.2+');
foreach (['pdo_mysql', 'curl', 'openssl', 'fileinfo', 'json', 'mbstring'] as $ext) {
    row("PHP extension: {$ext}", extension_loaded($ext));
}

// ---------------------------------------------------------------- Files
$root = __DIR__;
row('.env file exists', is_file("$root/.env"), is_file("$root/.env") ? '' : 'Run install.php, or create .env from .env.example');
row('database/schema.sql present', is_file("$root/database/schema.sql"));
row('.htaccess present', is_file("$root/.htaccess"), is_file("$root/.htaccess") ? '' : 'Hidden file — make sure it was uploaded');
foreach (['storage', 'storage/logs', 'storage/cache', 'storage/uploads', 'public/uploads'] as $d) {
    $p = "$root/$d";
    row("Writable: $d", is_dir($p) && is_writable($p), is_dir($p) ? '' : 'Directory missing');
}

// ---------------------------------------------------------------- Bootstrap + DB
$bootOk = false;
try {
    require_once __DIR__ . '/app/config/bootstrap.php';
    $bootOk = true;
    row('Application bootstrap loads', true);
} catch (\Throwable $e) {
    row('Application bootstrap loads', false, $e->getMessage());
}

$basePath = '(unknown)';
if ($bootOk) {
    row('APP_KEY is set', (string) config('app.key', '') !== '', 'Needed to encrypt API keys / SMTP password');
    row('APP_DEBUG is off', config('app.debug') === false, config('app.debug') ? 'Set APP_DEBUG=false for production' : '');

    try {
        $pdo = Database::connection();
        row('Database connection', true, 'Connected to ' . config('db.database'));

        $tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
        row('Database tables imported', $tables >= 81, "Found {$tables} tables (expected 81)");

        $plans = (int) $pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
        row('Seed data imported', $plans > 0, "Found {$plans} subscription plans");

        // Timezone consistency (this silently broke password resets before)
        $dbNow = (string) $pdo->query("SELECT NOW()")->fetchColumn();
        $drift = abs(strtotime($dbNow) - time());
        row('PHP/MySQL clock in sync', $drift <= 5 ? true : false,
            "MySQL: {$dbNow} | PHP: " . date('Y-m-d H:i:s') . " | drift {$drift}s");
    } catch (\Throwable $e) {
        row('Database connection', false, $e->getMessage());
    }

    $basePath = Url::basePath();
    row('Detected base path', true, $basePath === '' ? '(domain root)' : $basePath);
}

// ---------------------------------------------------------------- Asset reachability
// Fetch our own CSS over HTTP the same way a browser would.
$assetUrl = ($bootOk ? Url::asset('css/marketing.css') : '/public/assets/css/marketing.css');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$absAsset = $scheme . '://' . $host . $assetUrl;

$assetStatus = 'warn';
$assetDetail = 'Could not self-test (cURL unavailable)';
if (function_exists('curl_init')) {
    $ch = curl_init($absAsset);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($code === 200 && str_contains($ctype, 'css')) {
        $assetStatus = true;
        $assetDetail = "HTTP 200, {$ctype}, " . strlen($body) . ' bytes';
    } elseif ($code === 200) {
        $assetStatus = false;
        $assetDetail = "HTTP 200 but wrong type ({$ctype}) — the 404 page is being served instead of the stylesheet";
    } else {
        $assetStatus = false;
        $assetDetail = "HTTP {$code} — stylesheet not reachable at {$assetUrl}";
    }
}
row('Stylesheet reachable over HTTP', $assetStatus, $assetDetail);
row('mod_rewrite available', in_array('mod_rewrite', function_exists('apache_get_modules') ? apache_get_modules() : [], true) ? true : 'warn',
    function_exists('apache_get_modules') ? '' : 'Cannot detect (not running under mod_php) — assets still work via the built-in fallback');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation Diagnostic — BharatAI Business OS</title>
<style>
    body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f5f6fb;margin:0;color:#1e1b2e;}
    .wrap{max-width:860px;margin:40px auto;padding:0 20px;}
    .card{background:#fff;border-radius:16px;padding:28px;box-shadow:0 4px 24px rgba(30,27,46,.08);}
    h1{font-size:22px;margin:0 0 6px;} .sub{color:#5b5870;margin:0 0 22px;font-size:14px;}
    table{width:100%;border-collapse:collapse;font-size:14px;}
    td{padding:9px 8px;border-bottom:1px solid #f0f0f5;vertical-align:top;}
    td.s{width:70px;font-weight:700;text-align:center;}
    .ok{color:#16803c;} .no{color:#b91c1c;} .wn{color:#b45309;}
    .detail{color:#8b889c;font-size:12.5px;}
    .banner{padding:14px 16px;border-radius:10px;margin-bottom:20px;font-size:14px;}
    .banner.good{background:#ecfdf3;color:#16803c;} .banner.bad{background:#fef2f2;color:#b91c1c;}
    code{background:#f5f6fb;padding:1px 5px;border-radius:4px;font-size:12.5px;}
    .foot{margin-top:20px;font-size:13px;color:#8b889c;}
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Installation Diagnostic</h1>
        <p class="sub">BharatAI Business OS — server health check</p>

        <?php if ($fail === 0): ?>
            <div class="banner good"><strong>All critical checks passed.</strong> Your installation looks healthy<?= $warnCount ? " ({$warnCount} warning" . ($warnCount > 1 ? 's' : '') . ')' : '' ?>. Please delete <code>diagnose.php</code> now.</div>
        <?php else: ?>
            <div class="banner bad"><strong><?= $fail ?> check<?= $fail > 1 ? 's' : '' ?> failed.</strong> See the red rows below — each includes what to fix.</div>
        <?php endif; ?>

        <table>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td class="s <?= $r['status'] === true ? 'ok' : ($r['status'] === 'warn' ? 'wn' : 'no') ?>">
                    <?= $r['status'] === true ? 'OK' : ($r['status'] === 'warn' ? 'WARN' : 'FAIL') ?>
                </td>
                <td>
                    <?= htmlspecialchars($r['label'], ENT_QUOTES) ?>
                    <?php if ($r['detail'] !== ''): ?><div class="detail"><?= htmlspecialchars($r['detail'], ENT_QUOTES) ?></div><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="foot">
            <strong>Environment</strong><br>
            Document root: <code><?= htmlspecialchars((string) ($_SERVER['DOCUMENT_ROOT'] ?? 'n/a')) ?></code><br>
            Project directory: <code><?= htmlspecialchars(__DIR__) ?></code><br>
            Detected base path: <code><?= htmlspecialchars($basePath === '' ? '(domain root)' : $basePath) ?></code><br>
            Stylesheet URL tested: <code><?= htmlspecialchars($assetUrl) ?></code><br>
            Server software: <code><?= htmlspecialchars((string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown')) ?></code>
        </div>
    </div>
</div>
</body>
</html>
