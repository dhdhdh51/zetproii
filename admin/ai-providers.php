<?php
$pageTitle = 'AI Providers';
$activePage = 'ai_providers';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Providers — Admin | BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" async></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle menu" aria-expanded="false"><i data-lucide="menu"></i></button>
                <h1>AI Providers</h1>
            </div>
            <button class="theme-toggle" type="button" aria-label="Switch between light and dark theme"><i data-lucide="sun-moon"></i></button>
        </header>
        <div class="page-body" id="providers-container">
            <div class="skeleton" style="height:300px;"></div>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
async function loadProviders() {
    const json = await Api.call(appBase() + '/api/admin/ai-providers.php');
    const container = document.getElementById('providers-container');
    if (!json.success) { container.innerHTML = '<div class="empty-state">Failed to load providers.</div>'; return; }

    container.innerHTML = json.data.map(p => `
        <div class="card" style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="margin:0;">${p.name} ${p.has_api_key ? '<span class="badge badge-green">Key Set</span>' : '<span class="badge badge-yellow">No Key</span>'}</h3>
                <label><input type="checkbox" data-provider="${p.id}" class="provider-enabled" ${p.is_enabled ? 'checked' : ''}> Enabled</label>
            </div>
            <div class="grid grid-4">
                <div class="form-group"><label>Base URL</label><input class="form-control provider-base-url" data-provider="${p.id}" value="${p.base_url || ''}"></div>
                <div class="form-group"><label>API Key</label><input type="password" class="form-control provider-api-key" data-provider="${p.id}" placeholder="${p.has_api_key ? '••••••••' : 'Enter API key'}"></div>
                <div class="form-group"><label>Priority</label><input type="number" class="form-control provider-priority" data-provider="${p.id}" value="${p.priority}"></div>
                <div class="form-group"><label>Timeout (s)</label><input type="number" class="form-control provider-timeout" data-provider="${p.id}" value="${p.timeout_seconds}"></div>
            </div>
            <button class="btn btn-primary" style="width:auto;" onclick="saveProvider(${p.id})">Save Provider</button>

            <h4 style="margin-top:20px;">Models</h4>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Display</th><th>Max Tokens</th><th>Temp</th><th>Default</th><th>Fallback</th><th>Enabled</th></tr></thead>
                    <tbody>
                        ${p.models.map(m => `<tr>
                            <td>${m.name}</td><td>${m.display_name}</td><td>${m.max_tokens}</td><td>${m.temperature}</td>
                            <td>${m.is_default ? '<span class="badge badge-blue">Default</span>' : ''}</td>
                            <td>${m.is_fallback ? '<span class="badge badge-gray">Fallback</span>' : ''}</td>
                            <td>${m.is_enabled ? '✅' : '❌'}</td>
                        </tr>`).join('') || '<tr><td colspan="7"><div class="empty-state">No models configured.</div></td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>
    `).join('');
    if (window.lucide) lucide.createIcons();
}

async function saveProvider(id) {
    const payload = {
        id,
        base_url: document.querySelector(`.provider-base-url[data-provider="${id}"]`).value,
        priority: document.querySelector(`.provider-priority[data-provider="${id}"]`).value,
        timeout_seconds: document.querySelector(`.provider-timeout[data-provider="${id}"]`).value,
        is_enabled: document.querySelector(`.provider-enabled[data-provider="${id}"]`).checked,
    };
    const apiKey = document.querySelector(`.provider-api-key[data-provider="${id}"]`).value;
    if (apiKey) payload.api_key = apiKey;

    const json = await Api.call(appBase() + '/api/admin/ai-providers.php', { method: 'POST', body: payload });
    if (json.success) { Toast.success('Provider updated.'); loadProviders(); } else { Toast.error(json.message); }
}

loadProviders();
</script>
</body>
</html>
