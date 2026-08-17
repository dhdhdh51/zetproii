<?php
/**
 * BharatAI Business OS - Web Installer
 * =====================================================================
 * A simple, self-contained installation wizard that:
 *   1. Checks PHP version + required extensions
 *   2. Tests the MySQL database connection you provide
 *      (does NOT create the database - you must create the database
 *      yourself first, e.g. via cPanel "MySQL Databases", and provide
 *      its name/user/password here. This script only imports tables
 *      INTO that existing database.)
 *   3. Imports database/schema.sql then database/seed.sql
 *   4. Generates a real .env file for you (with a random APP_KEY)
 *   5. Lets you set your own admin email/password instead of using the
 *      default seeded bootstrap account
 *
 * SECURITY: After installation succeeds, DELETE this file (install.php)
 * from your server, or it will refuse to run again anyway once
 * storage/.installed is present - but deleting it is still recommended.
 *
 * Usage: upload the whole project, then visit https://yourdomain.com/install.php
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

$rootDir = __DIR__;

/**
 * Base path of this installation as seen from the browser.
 *
 * install.php always lives in the project root, so the directory part of
 * SCRIPT_NAME *is* the base path. Works for a root install ("" ) and for a
 * subfolder install such as /zetpro-main ("/zetpro-main").
 */
function install_base(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/install.php'));
    return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
}

/** Build a browser-usable URL for a path relative to the project root. */
function install_url(string $path = ''): string
{
    $p = ltrim($path, '/');
    return install_base() . '/' . $p;
}

$envPath = $rootDir . '/.env';
$lockPath = $rootDir . '/storage/.installed';
$schemaPath = $rootDir . '/database/schema.sql';
$seedPath = $rootDir . '/database/seed.sql';

// ---------------------------------------------------------------------
// Guard: refuse to run again once already installed.
// ---------------------------------------------------------------------
if (is_file($lockPath)) {
    render_locked_page();
    exit;
}

$step = $_POST['step'] ?? ($_GET['step'] ?? '1');
$errors = [];
$success = null;

// ---------------------------------------------------------------------
// Step 2: test DB connection + import schema/seed
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '2') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $appUrl = trim($_POST['app_url'] ?? '');

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
        $errors[] = 'Database host, name, and username are required.';
    }
    if ($appUrl === '') {
        $errors[] = 'Application URL is required.';
    }

    $pdo = null;
    if (empty($errors)) {
        try {
            // Connect to the database the user ALREADY created (e.g. via
            // cPanel MySQL Databases). We never issue CREATE DATABASE here.
            $pdo = new PDO(
                "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Throwable $e) {
            $errors[] = 'Could not connect to the database: ' . $e->getMessage() .
                ' Make sure the database already exists (create it first via your host\'s '.
                'control panel, e.g. cPanel > MySQL Databases) and that the credentials are correct.';
        }
    }

    if (empty($errors) && $pdo !== null) {
        if (!is_file($schemaPath) || !is_file($seedPath)) {
            $errors[] = 'database/schema.sql or database/seed.sql could not be found on the server.';
        } else {
            try {
                // Both files are idempotent (schema uses CREATE TABLE IF NOT
                // EXISTS, seed guards every insert), so re-running after a
                // partially-failed install is safe and won't duplicate data.
                $alreadyHadTables = database_has_our_tables($pdo);

                run_sql_file($pdo, $schemaPath);
                run_sql_file($pdo, $seedPath);

                if ($alreadyHadTables) {
                    $_SESSION['install_reused_existing'] = true;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Failed to import the database: ' . $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        // Store connection details in session for step 3 (admin account) +
        // step 4 (.env write), so the user isn't asked twice.
        $_SESSION['install_db'] = [
            'host' => $dbHost, 'port' => $dbPort, 'name' => $dbName,
            'user' => $dbUser, 'pass' => $dbPass, 'app_url' => rtrim($appUrl, '/'),
        ];
        $step = '3';
    } else {
        $step = '2';
    }
}

// ---------------------------------------------------------------------
// Step 3: create the real admin account + write .env + lock installer
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '3') {
    $dbInfo = $_SESSION['install_db'] ?? null;
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    if ($dbInfo === null) {
        $errors[] = 'Session expired. Please start over from Step 2.';
        $step = '2';
    } elseif ($adminName === '' || $adminEmail === '' || strlen($adminPassword) < 8) {
        $errors[] = 'Please provide a name, valid email, and a password of at least 8 characters.';
        $step = '3';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$dbInfo['host']};port={$dbInfo['port']};dbname={$dbInfo['name']};charset=utf8mb4",
                $dbInfo['user'], $dbInfo['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Replace the seeded bootstrap admin with the real admin's details.
            $hash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare(
                "UPDATE users SET name = ?, email = ?, password_hash = ?, email_verified_at = NOW()
                 WHERE email = 'admin@bharatai.example'"
            );
            $stmt->execute([$adminName, $adminEmail, $hash]);

            if ($stmt->rowCount() === 0) {
                // Bootstrap row wasn't found (e.g. seed.sql was customized) - insert fresh.
                $uuid = generate_uuid4();
                $pdo->prepare(
                    "INSERT INTO users (uuid, name, email, password_hash, role, status, email_verified_at, created_at)
                     VALUES (?, ?, ?, ?, 'SUPER_ADMIN', 'active', NOW(), NOW())"
                )->execute([$uuid, $adminName, $adminEmail, $hash]);
            }

            // Build the .env contents and attempt to write it.
            $appKey = bin2hex(random_bytes(32));
            $cronSecret = bin2hex(random_bytes(24));
            $envContents = build_env_contents($rootDir, $dbInfo, $appKey, $cronSecret);

            $envWritten = write_env_file($envPath, $envContents);

            if (!$envWritten) {
                // The web server user can't write to the project root (common
                // on hardened/VPS setups). Do NOT pretend the install
                // succeeded and do NOT create the lock file - instead show
                // the user the exact file contents to paste in manually, so
                // they can finish the install and re-run this final step.
                $_SESSION['install_env_contents'] = $envContents;
                $errors[] = 'Your database and admin account were set up successfully, but the installer '
                    . 'could not write the .env file (the web server does not have write permission on '
                    . 'the project root). Please create the .env file manually using the contents shown below, '
                    . 'then reload this page.';
                $step = 'manual_env';
            } else {
                // Only lock the installer once EVERYTHING succeeded.
                @mkdir(dirname($lockPath), 0755, true);
                if (@file_put_contents($lockPath, "Installed on " . date('c') . "\n") === false) {
                    // Non-fatal: install is complete, we just couldn't drop the
                    // lock marker. Warn the user to delete install.php manually.
                    $_SESSION['install_lock_warning'] = true;
                }
                unset($_SESSION['install_db'], $_SESSION['install_env_contents']);
                $step = 'done';
            }
        } catch (\Throwable $e) {
            $errors[] = 'Failed to finalize installation: ' . $e->getMessage();
            $step = '3';
        }
    }
}

// =======================================================================
// Helper functions
// =======================================================================

/**
 * Detects whether this database already contains BharatAI tables, so the
 * installer can tell the user it's reusing/repairing an existing install
 * rather than silently doing something unexpected.
 */
function database_has_our_tables(PDO $pdo): bool
{
    try {
        $count = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name IN ('users','plans','businesses')"
        )->fetchColumn();
        return $count > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

function generate_uuid4(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Runs a .sql file against $pdo, stripping comments and splitting on
 * statement-terminating semicolons while respecting string literals,
 * so multi-statement files (schema.sql / seed.sql) work with PDO's
 * single-statement exec(). This script never issues CREATE DATABASE
 * or DROP DATABASE - it only creates tables/rows inside the database
 * you already provided.
 */
function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    $sql = strip_sql_comments($sql);
    $statements = split_sql_statements($sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (\Throwable $e) {
            $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 120);
            throw new RuntimeException("SQL error near: \"{$preview}...\" - " . $e->getMessage(), 0, $e);
        }
    }
}

/**
 * Removes `-- line comments` and `/* block comments *\/` that live
 * OUTSIDE of string literals, without disturbing semicolons, quotes,
 * or comment-like text that happens to appear inside a string value.
 * This runs BEFORE statement splitting so a comment block sitting
 * between two statements (e.g. a "-- SECTION 3: ..." banner comment
 * directly above a CREATE TABLE) can never get glued onto the next
 * statement and cause it to be skipped.
 */
function strip_sql_comments(string $sql): string
{
    $result = '';
    $len = strlen($sql);
    $inString = false;
    $stringChar = '';

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inString) {
            $result .= $char;
            if ($char === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
            }
            continue;
        }

        if ($char === "'" || $char === '"') {
            $inString = true;
            $stringChar = $char;
            $result .= $char;
            continue;
        }

        // Line comment: -- ... (to end of line)
        if ($char === '-' && $next === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $result .= "\n";
            continue;
        }

        // Block comment: /* ... */
        if ($char === '/' && $next === '*') {
            $i += 2;
            while ($i < $len && !($sql[$i] === '*' && ($i + 1 < $len && $sql[$i + 1] === '/'))) {
                $i++;
            }
            $i++; // consume the trailing '/'
            $result .= ' ';
            continue;
        }

        $result .= $char;
    }

    return $result;
}

/** @return string[] */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $current .= $char;

        if ($inString) {
            if ($char === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
            }
            continue;
        }

        if ($char === "'" || $char === '"') {
            $inString = true;
            $stringChar = $char;
            continue;
        }

        if ($char === ';') {
            $statements[] = $current;
            $current = '';
        }
    }

    if (trim($current) !== '') {
        $statements[] = $current;
    }

    return $statements;
}

/**
 * Builds the full .env file contents (based on .env.example when
 * available, so any keys added to the example are preserved).
 */
function build_env_contents(string $rootDir, array $db, string $appKey, string $cronSecret): string
{
    $examplePath = $rootDir . '/.env.example';
    $template = is_file($examplePath) ? (string) file_get_contents($examplePath) : '';

    // If the site is being served over HTTPS, enable the secure-cookie flag.
    $isHttps = str_starts_with(strtolower($db['app_url']), 'https://');

    $replacements = [
        '/^APP_ENV=.*/m' => 'APP_ENV=production',
        '/^APP_DEBUG=.*/m' => 'APP_DEBUG=false',
        '/^APP_URL=.*/m' => 'APP_URL=' . $db['app_url'],
        '/^APP_KEY=.*/m' => 'APP_KEY=' . $appKey,
        '/^DB_HOST=.*/m' => 'DB_HOST=' . $db['host'],
        '/^DB_PORT=.*/m' => 'DB_PORT=' . $db['port'],
        '/^DB_DATABASE=.*/m' => 'DB_DATABASE=' . $db['name'],
        '/^DB_USERNAME=.*/m' => 'DB_USERNAME=' . $db['user'],
        '/^DB_PASSWORD=.*/m' => 'DB_PASSWORD=' . $db['pass'],
        '/^CRON_SECRET=.*/m' => 'CRON_SECRET=' . $cronSecret,
        '/^SESSION_SECURE_COOKIE=.*/m' => 'SESSION_SECURE_COOKIE=' . ($isHttps ? 'true' : 'false'),
    ];

    if ($template !== '') {
        return (string) preg_replace(array_keys($replacements), array_values($replacements), $template);
    }

    // .env.example missing - build a minimal working .env directly.
    return "APP_NAME=\"BharatAI Business OS\"\n" .
        "APP_ENV=production\nAPP_DEBUG=false\nAPP_URL={$db['app_url']}\nAPP_KEY={$appKey}\n" .
        "APP_TIMEZONE=Asia/Kolkata\nAPP_LOCALE=en\n\n" .
        "DB_HOST={$db['host']}\nDB_PORT={$db['port']}\nDB_DATABASE={$db['name']}\n" .
        "DB_USERNAME={$db['user']}\nDB_PASSWORD={$db['pass']}\nDB_CHARSET=utf8mb4\n\n" .
        "SESSION_SECURE_COOKIE=" . ($isHttps ? 'true' : 'false') . "\n" .
        "SESSION_LIFETIME=120\nCSRF_TOKEN_TTL=3600\n" .
        "LOGIN_MAX_ATTEMPTS=5\nLOGIN_LOCKOUT_MINUTES=15\n\n" .
        "CRON_SECRET={$cronSecret}\n\n" .
        "MAX_UPLOAD_SIZE_MB=25\nUPLOAD_PATH=storage/uploads\n";
}

/**
 * Writes the .env file, returning false (rather than emitting a warning
 * and silently continuing) if the web server lacks write permission on
 * the project root - which is common on hardened VPS setups where the
 * document root is owned by a different user than the PHP process.
 */
function write_env_file(string $envPath, string $contents): bool
{
    $bytes = @file_put_contents($envPath, $contents);
    if ($bytes === false || $bytes === 0) {
        return false;
    }
    @chmod($envPath, 0640);
    return true;
}

function check_requirements(): array
{
    $checks = [];
    $checks['PHP >= 8.2'] = version_compare(PHP_VERSION, '8.2.0', '>=');
    foreach (['pdo_mysql', 'curl', 'openssl', 'fileinfo', 'json', 'mbstring'] as $ext) {
        $checks["Extension: {$ext}"] = extension_loaded($ext);
    }
    $checks['storage/ is writable'] = is_writable(__DIR__ . '/storage') || @mkdir(__DIR__ . '/storage', 0755, true);
    $checks['public/uploads/ is writable'] = is_writable(__DIR__ . '/public/uploads') || is_dir(__DIR__ . '/public/uploads');
    $checks['.env does not already exist'] = !is_file(__DIR__ . '/.env');
    // If the project root isn't writable we can't generate .env automatically.
    // Not fatal (the installer falls back to showing the contents for manual
    // creation), so this is reported separately as a warning-style check.
    $checks['Project root is writable (for .env)'] = is_writable(__DIR__);
    return $checks;
}

function render_locked_page(): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Already Installed</title>' .
        install_styles() . '</head><body><div class="wrap"><div class="card">' .
        '<h1>✅ Already Installed</h1>' .
        '<p>BharatAI Business OS has already been installed on this server.</p>' .
        '<p>For security, please <strong>delete <code>install.php</code></strong> from your server now.</p>' .
        '<p><a class="btn" href="' . install_url() . '">Go to homepage</a></p>' .
        '</div></div></body></html>';
}

function install_styles(): string
{
    return '<style>
        body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f5f6fb;margin:0;color:#1e1b2e;}
        .wrap{max-width:640px;margin:60px auto;padding:0 20px;}
        .card{background:#fff;border-radius:16px;padding:32px;box-shadow:0 4px 24px rgba(30,27,46,0.08);}
        h1{font-size:22px;margin-top:0;} p{color:#5b5870;line-height:1.6;}
        .brand{color:#4f46e5;font-weight:700;font-size:18px;margin-bottom:20px;}
        label{display:block;font-weight:600;font-size:13.5px;margin:14px 0 6px;}
        input,select{width:100%;padding:10px 12px;border:1px solid #e6e4f0;border-radius:8px;font-size:14px;box-sizing:border-box;}
        .btn{display:inline-block;margin-top:20px;padding:12px 22px;background:linear-gradient(135deg,#4f46e5,#06b6d4);
             color:#fff;border:none;border-radius:8px;font-weight:600;font-size:15px;cursor:pointer;text-decoration:none;}
        .steps{display:flex;gap:6px;margin-bottom:24px;}
        .steps .dot{flex:1;height:5px;border-radius:99px;background:#e6e4f0;}
        .steps .dot.active{background:#4f46e5;}
        .error{background:#fee;color:#b91c1c;padding:12px 14px;border-radius:8px;margin-bottom:14px;font-size:14px;}
        .check{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f5;font-size:14px;}
        .ok{color:#16803c;font-weight:700;} .fail{color:#b91c1c;font-weight:700;}
        .help{font-size:12.5px;color:#8b889c;margin-top:4px;}
    </style>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install — BharatAI Business OS</title>
<?= install_styles() ?>
</head>
<body>
<div class="wrap">
    <div class="brand">✨ BharatAI Business OS — Installer</div>
    <div class="card">
        <div class="steps">
            <div class="dot <?= in_array($step, ['1','2','3','done']) ? 'active' : '' ?>"></div>
            <div class="dot <?= in_array($step, ['2','3','done']) ? 'active' : '' ?>"></div>
            <div class="dot <?= in_array($step, ['3','done']) ? 'active' : '' ?>"></div>
            <div class="dot <?= $step === 'done' ? 'active' : '' ?>"></div>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="error"><?= htmlspecialchars($err, ENT_QUOTES) ?></div>
        <?php endforeach; ?>

        <?php if ($step === '1'): ?>
            <h1>Step 1 of 3 — System Requirements</h1>
            <p>Checking that your server meets the requirements to run BharatAI Business OS.</p>
            <?php
            $checks = check_requirements();
            // This one is only a warning - the installer can still finish and
            // will show you the .env contents to create manually instead.
            $warnOnly = ['Project root is writable (for .env)'];
            $blockers = array_filter($checks, fn ($ok, $label) => !$ok && !in_array($label, $warnOnly, true), ARRAY_FILTER_USE_BOTH);
            $allOk = count($blockers) === 0;
            ?>
            <?php foreach ($checks as $label => $ok): ?>
                <?php $isWarn = in_array($label, $warnOnly, true); ?>
                <div class="check">
                    <span><?= htmlspecialchars($label) ?></span>
                    <span class="<?= $ok ? 'ok' : ($isWarn ? '' : 'fail') ?>" <?= (!$ok && $isWarn) ? 'style="color:#b45309;font-weight:700;"' : '' ?>>
                        <?= $ok ? 'OK' : ($isWarn ? 'WARN' : 'FAIL') ?>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if (!$checks['Project root is writable (for .env)']): ?>
                <p class="help">⚠️ The project root isn't writable, so the installer won't be able to create <code>.env</code> automatically. That's fine — it will show you the exact contents to paste into a <code>.env</code> file yourself at the end.</p>
            <?php endif; ?>
            <?php if (!$allOk): ?>
                <p class="help">Please resolve the <strong>FAIL</strong> items above (or delete an existing .env file if this is a fresh install) before continuing.</p>
            <?php else: ?>
                <a class="btn" href="?step=2">Continue &rarr;</a>
            <?php endif; ?>

        <?php elseif ($step === '2'): ?>
            <h1>Step 2 of 3 — Database Connection</h1>
            <p class="help"><strong>Create the database first</strong> using your host's control panel (e.g. cPanel &gt; MySQL Databases) before continuing. This installer will only import tables into a database that already exists — it will never try to create one for you.</p>
            <form method="POST" action="<?= install_url('install.php') ?>">
                <input type="hidden" name="step" value="2">
                <label>Database Host</label>
                <input type="text" name="db_host" value="localhost" required>
                <label>Database Port</label>
                <input type="text" name="db_port" value="3306" required>
                <label>Database Name <span class="help">(the one you already created)</span></label>
                <input type="text" name="db_name" placeholder="e.g. username_bharatai" required>
                <label>Database Username</label>
                <input type="text" name="db_user" required>
                <label>Database Password</label>
                <input type="password" name="db_pass">
                <label>Your Site URL</label>
                <input type="text" name="app_url" placeholder="https://yourdomain.com" required>
                <button class="btn" type="submit">Test Connection & Import Tables</button>
            </form>

        <?php elseif ($step === '3'): ?>
            <h1>Step 3 of 3 — Create Your Admin Account</h1>
            <?php if (!empty($_SESSION['install_reused_existing'])): ?>
                <p class="help" style="background:#eef2ff;border-radius:8px;padding:10px 12px;color:#4338ca;">
                    ℹ️ This database already contained BharatAI tables (likely from an earlier install attempt).
                    Nothing was duplicated or lost — existing tables and data were kept, and anything missing was added.
                </p>
            <?php endif; ?>
            <p>Database tables imported successfully. Now set up your own administrator login (this replaces the default seeded account).</p>
            <form method="POST" action="<?= install_url('install.php') ?>">
                <input type="hidden" name="step" value="3">
                <label>Your Name</label>
                <input type="text" name="admin_name" required>
                <label>Admin Email</label>
                <input type="email" name="admin_email" required>
                <label>Admin Password <span class="help">(min. 8 characters)</span></label>
                <input type="password" name="admin_password" minlength="8" required>
                <button class="btn" type="submit">Finish Installation</button>
            </form>

        <?php elseif ($step === 'manual_env'): ?>
            <h1>Almost there — create your <code>.env</code> file</h1>
            <p>Your database tables and admin account are ready. The only remaining step is the <code>.env</code> configuration file, which the installer couldn't write automatically due to file permissions.</p>
            <p><strong>Create a file named <code>.env</code> in the project root</strong> (the same folder as <code>install.php</code>) with exactly these contents:</p>
            <textarea readonly style="width:100%;height:280px;font-family:monospace;font-size:12px;border:1px solid #e6e4f0;border-radius:8px;padding:12px;"><?= htmlspecialchars($_SESSION['install_env_contents'] ?? '', ENT_QUOTES) ?></textarea>
            <p class="help">In cPanel File Manager: enable <em>Settings → Show Hidden Files</em>, click <em>+ File</em>, name it <code>.env</code>, then edit it and paste the above. Keep this page open until you've saved it — the <code>APP_KEY</code> above is unique and won't be shown again.</p>
            <p class="help"><strong>Then:</strong> delete <code>install.php</code> from your server and go to the login page.</p>
            <a class="btn" href="<?= install_url('auth/login.php') ?>">I've created .env — Go to Login &rarr;</a>

        <?php elseif ($step === 'done'): ?>
            <h1>🎉 Installation Complete!</h1>
            <p>BharatAI Business OS has been installed successfully. Your <code>.env</code> file was generated automatically, and your admin account is ready.</p>
            <?php if (!empty($_SESSION['install_lock_warning'])): ?>
                <div class="error">Note: the installer couldn't create its lock file in <code>storage/</code>. Please make sure you delete <code>install.php</code> manually, since it can't self-disable.</div>
            <?php endif; ?>
            <p><strong>Important:</strong> For security, please delete <code>install.php</code> from your server now.</p>
            <a class="btn" href="<?= install_url('auth/login.php') ?>">Go to Login &rarr;</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
