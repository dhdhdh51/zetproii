<?php
$pageTitle = 'Platform Settings';
$activePage = 'settings';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Platform Settings — Admin | BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">Platform Settings</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card" style="max-width:640px;margin-bottom:16px;">
                <h3 style="margin-top:0;">General</h3>
                <form id="general-form">
                    <div class="form-group"><label>Platform Name</label><input id="platform_name" class="form-control"></div>
                    <div class="form-group"><label>Support Email</label><input id="platform_support_email" type="email" class="form-control"></div>
                    <div class="grid grid-2">
                        <div class="form-group"><label>Default Currency</label><input id="default_currency" class="form-control"></div>
                        <div class="form-group"><label>Trial Days</label><input id="trial_days" type="number" class="form-control"></div>
                    </div>
                    <label><input type="checkbox" id="maintenance_mode"> Maintenance Mode</label>
                    <div style="margin-top:12px;"><button type="submit" class="btn btn-primary" style="width:auto;">Save</button></div>
                </form>
            </div>

            <div class="card" style="max-width:640px;">
                <h3 style="margin-top:0;">Google OAuth</h3>
                <form id="google-form">
                    <label><input type="checkbox" id="google_oauth_enabled"> Enable Google OAuth login</label>
                    <div class="form-group" style="margin-top:10px;"><label>Client ID</label><input id="google_client_id" class="form-control"></div>
                    <div class="form-group"><label>Client Secret</label><input id="google_client_secret" type="password" class="form-control"></div>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Save</button>
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
    document.getElementById('platform_name').value = d.platform_name || '';
    document.getElementById('platform_support_email').value = d.platform_support_email || '';
    document.getElementById('default_currency').value = d.default_currency || 'INR';
    document.getElementById('trial_days').value = d.trial_days || 14;
    document.getElementById('maintenance_mode').checked = d.maintenance_mode === '1';
    document.getElementById('google_oauth_enabled').checked = d.google_oauth_enabled === '1';
    document.getElementById('google_client_id').value = d.google_client_id || '';
}

document.getElementById('general-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call(appBase() + '/api/admin/settings.php', {
        method: 'POST',
        body: {
            platform_name: document.getElementById('platform_name').value, platform_support_email: document.getElementById('platform_support_email').value,
            default_currency: document.getElementById('default_currency').value, trial_days: document.getElementById('trial_days').value,
            maintenance_mode: document.getElementById('maintenance_mode').checked ? '1' : '0',
        },
    });
    if (json.success) Toast.success('Settings saved.'); else Toast.error(json.message);
});

document.getElementById('google-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        google_oauth_enabled: document.getElementById('google_oauth_enabled').checked ? '1' : '0',
        google_client_id: document.getElementById('google_client_id').value,
    };
    const secret = document.getElementById('google_client_secret').value;
    if (secret) payload.google_client_secret = secret;
    const json = await Api.call(appBase() + '/api/admin/settings.php', { method: 'POST', body: payload });
    if (json.success) Toast.success('Saved.'); else Toast.error(json.message);
});

loadSettings();
</script>
</body>
</html>
