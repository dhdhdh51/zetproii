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
                run_sql_file($pdo, $schemaPath);
                run_sql_file($pdo, $seedPath);
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

            // Write the real .env file.
            $appKey = bin2hex(random_bytes(32));
            $cronSecret = bin2hex(random_bytes(24));
            write_env_file($envPath, $dbInfo, $appKey, $cronSecret);

            // Lock the installer so it can't be run again.
            @mkdir(dirname($lockPath), 0755, true);
            file_put_contents($lockPath, "Installed on " . date('c') . "\n");

            unset($_SESSION['install_db']);
            $step = 'done';
        } catch (\Throwable $e) {
            $errors[] = 'Failed to finalize installation: ' . $e->getMessage();
            $step = '3';
        }
    }
}

// =======================================================================
// Helper functions
// =======================================================================

function generate_uuid4(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Runs a .sql file against $pdo, splitting on statement-terminating
 * semicolons while respecting string literals, so multi-statement files
 * (schema.sql / seed.sql) work with PDO's single-statement exec().
 * This script never issues CREATE DATABASE or DROP DATABASE - it only
 * creates tables/rows inside the database you already provided.
 */
function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    $statements = split_sql_statements($sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '' || str_starts_with($statement, '--')) {
            continue;
        }
        $pdo->exec($statement);
    }
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

function write_env_file(string $envPath, array $db, string $appKey, string $cronSecret): void
{
    $examplePath = dirname($envPath) . '/.env.example';
    $template = is_file($examplePath) ? (string) file_get_contents($examplePath) : '';

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
    ];

    if ($template !== '') {
        $content = preg_replace(array_keys($replacements), array_values($replacements), $template);
    } else {
        // .env.example missing - write a minimal working .env directly.
        $content = "APP_NAME=\"BharatAI Business OS\"\n" .
            "APP_ENV=production\nAPP_DEBUG=false\nAPP_URL={$db['app_url']}\nAPP_KEY={$appKey}\n\n" .
            "DB_HOST={$db['host']}\nDB_PORT={$db['port']}\nDB_DATABASE={$db['name']}\n" .
            "DB_USERNAME={$db['user']}\nDB_PASSWORD={$db['pass']}\nDB_CHARSET=utf8mb4\n\n" .
            "SESSION_SECURE_COOKIE=true\nCRON_SECRET={$cronSecret}\n";
    }

    file_put_contents($envPath, $content);
    @chmod($envPath, 0640);
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
        '<p><a class="btn" href="/">Go to homepage</a></p>' .
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
            <?php $checks = check_requirements(); $allOk = !in_array(false, $checks, true); ?>
            <?php foreach ($checks as $label => $ok): ?>
                <div class="check"><span><?= htmlspecialchars($label) ?></span><span class="<?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? 'OK' : 'FAIL' ?></span></div>
            <?php endforeach; ?>
            <?php if (!$allOk): ?>
                <p class="help">Please resolve the failed checks above (or delete an existing .env file if this is a fresh install) before continuing.</p>
            <?php else: ?>
                <a class="btn" href="?step=2">Continue &rarr;</a>
            <?php endif; ?>

        <?php elseif ($step === '2'): ?>
            <h1>Step 2 of 3 — Database Connection</h1>
            <p class="help"><strong>Create the database first</strong> using your host's control panel (e.g. cPanel &gt; MySQL Databases) before continuing. This installer will only import tables into a database that already exists — it will never try to create one for you.</p>
            <form method="POST" action="install.php">
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
            <p>Database tables imported successfully. Now set up your own administrator login (this replaces the default seeded account).</p>
            <form method="POST" action="install.php">
                <input type="hidden" name="step" value="3">
                <label>Your Name</label>
                <input type="text" name="admin_name" required>
                <label>Admin Email</label>
                <input type="email" name="admin_email" required>
                <label>Admin Password <span class="help">(min. 8 characters)</span></label>
                <input type="password" name="admin_password" minlength="8" required>
                <button class="btn" type="submit">Finish Installation</button>
            </form>

        <?php elseif ($step === 'done'): ?>
            <h1>🎉 Installation Complete!</h1>
            <p>BharatAI Business OS has been installed successfully. Your <code>.env</code> file was generated automatically, and your admin account is ready.</p>
            <p><strong>Important:</strong> For security, please delete <code>install.php</code> from your server now.</p>
            <a class="btn" href="/auth/login.php">Go to Login &rarr;</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
