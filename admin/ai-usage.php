<?php
$pageTitle = 'AI Usage';
$activePage = 'ai_usage';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Usage — Admin | BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">AI Usage</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div id="totals-grid" class="grid grid-3" style="margin-bottom:20px;">
                <div class="card"><div class="skeleton" style="height:60px;"></div></div>
                <div class="card"><div class="skeleton" style="height:60px;"></div></div>
                <div class="card"><div class="skeleton" style="height:60px;"></div></div>
            </div>
            <div class="grid grid-2">
                <div class="card">
                    <div class="card-title">By Feature</div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Feature</th><th>Requests</th><th>Tokens</th><th>Cost</th></tr></thead><tbody id="feature-tbody"></tbody></table></div>
                </div>
                <div class="card">
                    <div class="card-title">By Provider</div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Provider</th><th>Requests</th><th>Failures</th><th>Cost</th></tr></thead><tbody id="provider-tbody"></tbody></table></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
async function load() {
    const json = await Api.call('' + window.__BASE__ + '/api/admin/ai-usage.php?days=30');
    if (!json.success) { Toast.error(json.message); return; }
    const d = json.data;
    document.getElementById('totals-grid').innerHTML = `
        <div class="card"><div class="card-title">Total Requests (30d)</div><p class="card-value">${d.totals.requests || 0}</p></div>
        <div class="card"><div class="card-title">Total Tokens</div><p class="card-value">${Number(d.totals.tokens || 0).toLocaleString()}</p></div>
        <div class="card"><div class="card-title">Estimated Cost</div><p class="card-value">$${Number(d.totals.cost || 0).toFixed(2)}</p></div>
    `;
    document.getElementById('feature-tbody').innerHTML = d.by_feature.map(f => `<tr><td>${f.feature}</td><td>${f.requests}</td><td>${Number(f.tokens).toLocaleString()}</td><td>$${Number(f.cost).toFixed(4)}</td></tr>`).join('') || '<tr><td colspan="4"><div class="empty-state">No AI usage yet.</div></td></tr>';
    document.getElementById('provider-tbody').innerHTML = d.by_provider.map(p => `<tr><td>${p.name || 'Unknown'}</td><td>${p.requests}</td><td>${p.failures}</td><td>$${Number(p.cost).toFixed(4)}</td></tr>`).join('') || '<tr><td colspan="4"><div class="empty-state">No AI usage yet.</div></td></tr>';
}
load();
</script>
</body>
</html>
