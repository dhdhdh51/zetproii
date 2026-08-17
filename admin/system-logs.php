<?php
$pageTitle = 'System Logs';
$activePage = 'system_logs';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Logs — Admin | BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">System Logs</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;">
                <select id="f-channel" class="form-control" style="max-width:200px;">
                    <option value="">All channels</option>
                    <option value="app">App</option><option value="system">System</option><option value="ai">AI</option>
                    <option value="email">Email</option><option value="payment">Payment</option><option value="webhook">Webhook</option>
                    <option value="security">Security</option><option value="cron">Cron</option>
                </select>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Channel</th><th>Level</th><th>Message</th><th>When</th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="4"><div class="skeleton" style="height:200px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
function levelBadge(l) {
    const map = { error: 'red', warning: 'yellow', info: 'blue' };
    return `<span class="badge badge-${map[l] || 'gray'}">${l}</span>`;
}
async function load(page = 1) {
    const params = new URLSearchParams({ page, channel: document.getElementById('f-channel').value });
    const json = await Api.call('' + window.__BASE__ + '/api/admin/system-logs.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="4"><div class="empty-state">No logs found.</div></td></tr>'
        : json.data.items.map(l => `<tr><td><span class="badge badge-gray">${l.channel}</span></td><td>${levelBadge(l.level)}</td><td style="max-width:400px;overflow:hidden;text-overflow:ellipsis;">${l.message}</td><td>${new Date(l.created_at).toLocaleString()}</td></tr>`).join('');
    const p = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${p.page} of ${p.total_pages || 1}</span>
        <div style="display:flex;gap:6px;"><button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page<=1?'disabled':''} onclick="load(${p.page-1})">Prev</button>
        <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page>=p.total_pages?'disabled':''} onclick="load(${p.page+1})">Next</button></div>`;
}
document.getElementById('f-channel').addEventListener('change', () => load(1));
load(1);
</script>
</body>
</html>
