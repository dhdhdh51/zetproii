<?php
/**
 * Central security helpers: CSRF, XSS escaping, input sanitization,
 * secure random tokens, and simple AES-256-GCM encryption for secrets
 * (API keys, SMTP passwords) stored in the database.
 */
final class Security
{
    // ---------------- CSRF ----------------

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > config('session.csrf_ttl', 3600)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || $token === null || $token === '') {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? null;

        if (!self::verifyCsrfToken($token)) {
            Logger::security('CSRF token validation failed', [
                'ip'  => self::clientIp(),
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            Response::forbidden('Invalid or missing CSRF token');
        }
    }

    // ---------------- XSS / Output escaping ----------------

    public static function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ---------------- Input sanitization ----------------

    public static function cleanString(?string $value): string
    {
        return trim(strip_tags((string) $value));
    }

    public static function cleanEmail(?string $value): string
    {
        $value = trim((string) $value);
        return filter_var($value, FILTER_SANITIZE_EMAIL) ?: '';
    }

    // ---------------- Tokens ----------------

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function generateApiKey(): string
    {
        return 'bak_' . bin2hex(random_bytes(24)); // bharatai key
    }

    // ---------------- Passwords ----------------

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function passwordNeedsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ---------------- Symmetric encryption (for API keys, SMTP passwords) ----------------

    private static function encryptionKey(): string
    {
        $appKey = (string) config('app.key', '');
        if ($appKey === '') {
            // Fail loudly in logs but do not crash - fall back to a
            // per-install derived key so the app still runs, though the
            // admin MUST set APP_KEY in .env for real deployments.
            Logger::system('APP_KEY is not set in .env - encryption is insecure until configured.');
            $appKey = 'insecure-default-key-change-me-now';
        }
        return hash('sha256', $appKey, true);
    }

    public static function encrypt(string $plainText): string
    {
        $key = self::encryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipherText === false) {
            throw new RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $cipherText);
    }

    public static function decrypt(string $encoded): ?string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $key = self::encryptionKey();
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipherText = substr($raw, 28);
        $plain = openssl_decrypt($cipherText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    // ---------------- Misc ----------------

    public static function clientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
    }

    /** Safe filename for uploaded files (no path traversal, no double extensions abuse) */
    public static function safeFilename(string $originalName): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
        return bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
    }

    public static function isExtensionBlocked(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, config('uploads.blocked_extensions', []), true);
    }
}
