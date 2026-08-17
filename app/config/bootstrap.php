<?php
/**
 * Application bootstrap.
 * Loaded by every entry point (public/index.php, api/*.php, admin/*.php,
 * dashboard/*.php, cron/*.php) to set up config, autoloading, error
 * handling, and secure sessions.
 */

declare(strict_types=1);

error_reporting(E_ALL);

// ---- Config (also loads .env) ----
require_once __DIR__ . '/config.php';

$isDebug = (bool) config('app.debug', false);
ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');

// ---- Simple PSR-4-ish autoloader for /app classes ----
spl_autoload_register(function (string $class): void {
    $baseDirs = [
        dirname(__DIR__), // /app
    ];
    $subdirs = ['config', 'controllers', 'models', 'services', 'middleware',
                'helpers', 'repositories', 'validators', 'ai', 'mail'];
    foreach ($baseDirs as $base) {
        foreach ($subdirs as $sub) {
            $path = $base . '/' . $sub . '/' . $class . '.php';
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    }
});

require_once __DIR__ . '/Database.php';

// Url.php defines the global asset()/url() helper FUNCTIONS used by every
// view. PHP's autoloader only fires for classes, never for functions, so
// this file must be required explicitly - without it every page would hit
// "Call to undefined function asset()" and render with no styling at all.
require_once dirname(__DIR__) . '/helpers/Url.php';

// ---- Global error/exception handling (never leak stack traces in prod) ----
set_exception_handler(function (\Throwable $e) use ($isDebug): void {
    Logger::system($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $isDebug ? $e->getTraceAsString() : null,
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'success' => false,
        'data'    => null,
        'message' => $isDebug ? $e->getMessage() : 'Something went wrong. Please try again later.',
        'errors'  => $isDebug ? [$e->getTraceAsString()] : [],
    ]);
    exit;
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    Logger::system($message, ['file' => $file, 'line' => $line, 'severity' => $severity]);
    return true;
});

// ---- Secure session configuration ----
if (session_status() === PHP_SESSION_NONE) {
    $secure = (bool) config('session.secure_cookie', true);
    $lifetimeSeconds = (int) config('session.lifetime', 120) * 60;

    session_set_cookie_params([
        'lifetime' => $lifetimeSeconds,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.sid_length', '48');
    session_name('bharatai_session');
    session_start();

    // Regenerate the session id periodically to mitigate fixation attacks.
    if (empty($_SESSION['_last_regen'])) {
        $_SESSION['_last_regen'] = time();
    } elseif (time() - $_SESSION['_last_regen'] > 900) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }
}

// Common security headers for every response.
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
}
