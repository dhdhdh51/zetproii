<?php
/**
 * Central application configuration.
 * Reads from environment (.env) and exposes a simple config() helper.
 * No secrets are hardcoded here - everything sensitive comes from .env
 * or from encrypted values in the database (see EncryptionService).
 */

require_once __DIR__ . '/Env.php';

// Load .env from project root (one level above /app/config)
Env::load(dirname(__DIR__, 2) . '/.env');

$config = [
    'app' => [
        'name'       => Env::get('APP_NAME', 'BharatAI Business OS'),
        'env'        => Env::get('APP_ENV', 'production'),
        'debug'      => Env::getBool('APP_DEBUG', false),
        'url'        => rtrim((string) Env::get('APP_URL', 'http://localhost'), '/'),
        'timezone'   => Env::get('APP_TIMEZONE', 'Asia/Kolkata'),
        'key'        => Env::get('APP_KEY', ''),
        'locale'     => Env::get('APP_LOCALE', 'en'),
        // Optional override for the URL path the app is served under (e.g.
        // "myapp" if it lives at https://domain.com/myapp). Leave blank to
        // auto-detect - see app/helpers/Url.php.
        'base_path'  => Env::get('BASE_PATH', ''),
    ],

    'db' => [
        'host'    => Env::get('DB_HOST', '127.0.0.1'),
        'port'    => Env::getInt('DB_PORT', 3306),
        'database'=> Env::get('DB_DATABASE', 'bharatai'),
        'username'=> Env::get('DB_USERNAME', 'root'),
        'password'=> Env::get('DB_PASSWORD', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    'session' => [
        'secure_cookie'   => Env::getBool('SESSION_SECURE_COOKIE', true),
        'lifetime'        => Env::getInt('SESSION_LIFETIME', 120), // minutes
        'csrf_ttl'        => Env::getInt('CSRF_TOKEN_TTL', 3600),
    ],

    'auth' => [
        'max_login_attempts'      => Env::getInt('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes'         => Env::getInt('LOGIN_LOCKOUT_MINUTES', 15),
        'password_reset_ttl_min'  => Env::getInt('PASSWORD_RESET_TTL_MINUTES', 60),
        'email_verify_ttl_hours'  => Env::getInt('EMAIL_VERIFICATION_TTL_HOURS', 48),
        'google' => [
            'client_id'     => Env::get('GOOGLE_CLIENT_ID', ''),
            'client_secret' => Env::get('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri'  => Env::get('GOOGLE_REDIRECT_URI', ''),
        ],
    ],

    'mail' => [
        'host'       => Env::get('MAIL_HOST', ''),
        'port'       => Env::getInt('MAIL_PORT', 587),
        'username'   => Env::get('MAIL_USERNAME', ''),
        'password'   => Env::get('MAIL_PASSWORD', ''),
        'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
        'from_address' => Env::get('MAIL_FROM_ADDRESS', 'no-reply@localhost'),
        'from_name'    => Env::get('MAIL_FROM_NAME', 'BharatAI Business OS'),
    ],

    'ai' => [
        'openai' => [
            'api_key'  => Env::get('OPENAI_API_KEY', ''),
            'base_url' => Env::get('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'gemini' => [
            'api_key'  => Env::get('GEMINI_API_KEY', ''),
            'base_url' => Env::get('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'anthropic' => [
            'api_key'  => Env::get('ANTHROPIC_API_KEY', ''),
            'base_url' => Env::get('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        ],
    ],

    'payments' => [
        'razorpay' => [
            'key_id'     => Env::get('RAZORPAY_KEY_ID', ''),
            'key_secret' => Env::get('RAZORPAY_KEY_SECRET', ''),
        ],
        'stripe' => [
            'publishable_key' => Env::get('STRIPE_PUBLISHABLE_KEY', ''),
            'secret_key'      => Env::get('STRIPE_SECRET_KEY', ''),
            'webhook_secret'  => Env::get('STRIPE_WEBHOOK_SECRET', ''),
        ],
        'cashfree' => [
            'app_id'      => Env::get('CASHFREE_APP_ID', ''),
            'secret_key'  => Env::get('CASHFREE_SECRET_KEY', ''),
        ],
    ],

    'cron' => [
        'secret' => Env::get('CRON_SECRET', ''),
    ],

    'uploads' => [
        'max_size_mb' => Env::getInt('MAX_UPLOAD_SIZE_MB', 25),
        'path'        => Env::get('UPLOAD_PATH', 'storage/uploads'),
        'blocked_extensions' => [
            'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
            'pht', 'phps', 'cgi', 'pl', 'py', 'sh', 'exe', 'asp',
            'aspx', 'jsp', 'htaccess', 'ini', 'js', 'vbs',
        ],
        'allowed_document_extensions' => ['pdf', 'txt', 'doc', 'docx', 'csv', 'xls', 'xlsx'],
        'allowed_image_extensions'    => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    ],
];

date_default_timezone_set($config['app']['timezone']);

if (!function_exists('config')) {
    /**
     * Dot-notation config accessor. e.g. config('db.host')
     */
    function config(string $key, mixed $default = null): mixed
    {
        global $config;
        $segments = explode('.', $key);
        $value = $config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

return $config;
