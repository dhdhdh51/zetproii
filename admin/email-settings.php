<?php
$pageTitle = 'Email / SMTP Settings';
$activePage = 'email_settings';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email Settings — Admin | BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" async></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle menu" aria-expanded="false"><i data-lucide="menu"></i></button><h1>Email / SMTP Settings</h1></div>
            <button class="theme-toggle" type="button" aria-label="Switch between light and dark theme"><i data-lucide="sun-moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card" style="max-width:640px;">
                <form id="smtp-form">
                    <div class="form-group" style="padding-bottom:14px;border-bottom:1px solid var(--border);margin-bottom:18px;">
                        <label class="checkbox-row" style="font-weight:600;">
                            <input type="checkbox" id="email_enabled" checked>
                            Email sending is on
                        </label>
                        <span class="form-help">
                            Untick to stop all outbound email without deleting the settings below.
                            While it is off, new sign-ups are activated immediately instead of waiting
                            for a verification email, and password-reset links cannot be sent —
                            reset a password from <strong>Users</strong> instead. Every blocked attempt
                            is still recorded, with the reason, in the email log.
                        </span>
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group"><label for="smtp_host">SMTP Host</label><input id="smtp_host" class="form-control"></div>
                        <div class="form-group"><label for="smtp_port">SMTP Port</label><input id="smtp_port" type="number" class="form-control" value="587"></div>
                        <div class="form-group"><label for="smtp_username">SMTP Username</label><input id="smtp_username" class="form-control"></div>
                        <div class="form-group"><label for="smtp_password">SMTP Password</label><input id="smtp_password" type="password" class="form-control"></div>
                        <div class="form-group"><label for="smtp_encryption">Encryption</label>
                            <select id="smtp_encryption" class="form-control"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select>
                        </div>
                        <div class="form-group"><label for="smtp_from_name">From Name</label><input id="smtp_from_name" class="form-control"></div>
                        <div class="form-group"><label for="smtp_from_address">From Email</label><input id="smtp_from_address" type="email" class="form-control"></div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Save Email Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
async function loadSettings() {
    const json = await Api.call(appBase() + '/api/admin/settings.php');
    if (!json.success) return;
    const d = json.data;
    document.getElementById('smtp_host').value = d.smtp_host || '';
    document.getElementById('smtp_port').value = d.smtp_port || 587;
    document.getElementById('smtp_username').value = d.smtp_username || '';
    document.getElementById('smtp_encryption').value = d.smtp_encryption || 'tls';
    document.getElementById('smtp_from_name').value = d.smtp_from_name || '';
    document.getElementById('smtp_from_address').value = d.smtp_from_address || '';
    // Absent means "not disabled", so only an explicit '1' unticks the box.
    document.getElementById('email_enabled').checked = String(d.email_disabled || '0') !== '1';
}

document.getElementById('smtp-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        smtp_host: document.getElementById('smtp_host').value, smtp_port: document.getElementById('smtp_port').value,
        smtp_username: document.getElementById('smtp_username').value, smtp_encryption: document.getElementById('smtp_encryption').value,
        smtp_from_name: document.getElementById('smtp_from_name').value, smtp_from_address: document.getElementById('smtp_from_address').value,
        // The real switch. (A 'smtp_configured' key used to be written here and
        // was never read by anything, which implied a toggle that didn't exist.)
        email_disabled: document.getElementById('email_enabled').checked ? '0' : '1',
    };
    const pw = document.getElementById('smtp_password').value;
    if (pw) payload.smtp_password = pw;

    const json = await Api.call(appBase() + '/api/admin/settings.php', { method: 'POST', body: payload });
    if (json.success) Toast.success('Email settings saved.'); else Toast.error(json.message);
});

loadSettings();
</script>
</body>
</html>
