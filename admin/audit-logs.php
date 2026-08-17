<?php
$pageTitle = 'Audit Logs';
$activePage = 'audit_logs';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Logs — Admin | BharatAI Business OS</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">Audit Logs</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>User</th><th>Action</th><th>IP</th><th>When</th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="4"><div class="skeleton" style="height:200px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/app.js"></script>
<script>
async function load(page = 1) {
    const json = await Api.call('/api/admin/audit-logs.php?page=' + page);
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="4"><div class="empty-state">No audit logs yet.</div></td></tr>'
        : json.data.items.map(l => `<tr><td>${l.user_name || 'System'}</td><td><span class="badge badge-blue">${l.action}</span></td><td>${l.ip_address || '-'}</td><td>${new Date(l.created_at).toLocaleString()}</td></tr>`).join('');
    const p = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${p.page} of ${p.total_pages || 1}</span>
        <div style="display:flex;gap:6px;"><button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page<=1?'disabled':''} onclick="load(${p.page-1})">Prev</button>
        <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page>=p.total_pages?'disabled':''} onclick="load(${p.page+1})">Next</button></div>`;
}
load(1);
</script>
</body>
</html>
