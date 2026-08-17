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
<title>Email Settings — Admin | BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">Email / SMTP Settings</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card" style="max-width:640px;">
                <form id="smtp-form">
                    <div class="grid grid-2">
                        <div class="form-group"><label>SMTP Host</label><input id="smtp_host" class="form-control"></div>
                        <div class="form-group"><label>SMTP Port</label><input id="smtp_port" type="number" class="form-control" value="587"></div>
                        <div class="form-group"><label>SMTP Username</label><input id="smtp_username" class="form-control"></div>
                        <div class="form-group"><label>SMTP Password</label><input id="smtp_password" type="password" class="form-control"></div>
                        <div class="form-group"><label>Encryption</label>
                            <select id="smtp_encryption" class="form-control"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select>
                        </div>
                        <div class="form-group"><label>From Name</label><input id="smtp_from_name" class="form-control"></div>
                        <div class="form-group"><label>From Email</label><input id="smtp_from_address" type="email" class="form-control"></div>
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
    const json = await Api.call('' + window.__BASE__ + '/api/admin/settings.php');
    if (!json.success) return;
    const d = json.data;
    document.getElementById('smtp_host').value = d.smtp_host || '';
    document.getElementById('smtp_port').value = d.smtp_port || 587;
    document.getElementById('smtp_username').value = d.smtp_username || '';
    document.getElementById('smtp_encryption').value = d.smtp_encryption || 'tls';
    document.getElementById('smtp_from_name').value = d.smtp_from_name || '';
    document.getElementById('smtp_from_address').value = d.smtp_from_address || '';
}

document.getElementById('smtp-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        smtp_host: document.getElementById('smtp_host').value, smtp_port: document.getElementById('smtp_port').value,
        smtp_username: document.getElementById('smtp_username').value, smtp_encryption: document.getElementById('smtp_encryption').value,
        smtp_from_name: document.getElementById('smtp_from_name').value, smtp_from_address: document.getElementById('smtp_from_address').value,
        smtp_configured: '1',
    };
    const pw = document.getElementById('smtp_password').value;
    if (pw) payload.smtp_password = pw;

    const json = await Api.call('' + window.__BASE__ + '/api/admin/settings.php', { method: 'POST', body: payload });
    if (json.success) Toast.success('Email settings saved.'); else Toast.error(json.message);
});

loadSettings();
</script>
</body>
</html>
