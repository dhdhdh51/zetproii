<?php
$pageTitle = 'Businesses';
$activePage = 'businesses';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Businesses — Admin | BharatAI Business OS</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">Businesses</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;">
                <input id="f-search" class="form-control" placeholder="Search business name..." style="max-width:260px;">
                <select id="f-status" class="form-control" style="max-width:170px;">
                    <option value="">All statuses</option><option value="active">Active</option><option value="trial">Trial</option>
                    <option value="suspended">Suspended</option><option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Business</th><th>Owner</th><th>Plan</th><th>Status</th><th>Created</th><th></th></tr></thead>
                        <tbody id="biz-tbody"><tr><td colspan="6"><div class="skeleton" style="height:200px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/app.js"></script>
<script>
function statusBadge(s) {
    const map = { active: 'green', trial: 'blue', suspended: 'red', cancelled: 'gray' };
    return `<span class="badge badge-${map[s] || 'gray'}">${s}</span>`;
}

async function loadBusinesses(page = 1) {
    const params = new URLSearchParams({ page, search: document.getElementById('f-search').value, status: document.getElementById('f-status').value });
    const json = await Api.call('/api/admin/businesses.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }
    const tbody = document.getElementById('biz-tbody');
    tbody.innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="6"><div class="empty-state">No businesses found.</div></td></tr>'
        : json.data.items.map(b => `
        <tr>
            <td><strong>${b.name}</strong></td>
            <td>${b.owner_name || '-'}<br><small style="color:var(--color-text-muted);">${b.owner_email || ''}</small></td>
            <td>${b.plan_name || '-'}</td>
            <td>${statusBadge(b.status)}</td>
            <td>${new Date(b.created_at).toLocaleDateString()}</td>
            <td>
                <select onchange="setStatus(${b.id}, this.value)" class="form-control" style="width:auto;padding:5px 8px;font-size:12px;">
                    <option value="">Change status</option>
                    <option value="active">Activate</option><option value="suspended">Suspend</option><option value="cancelled">Cancel</option>
                </select>
            </td>
        </tr>`).join('');

    const p = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${p.page} of ${p.total_pages || 1} (${p.total} businesses)</span>
        <div style="display:flex;gap:6px;">
            <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page<=1?'disabled':''} onclick="loadBusinesses(${p.page-1})">Prev</button>
            <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page>=p.total_pages?'disabled':''} onclick="loadBusinesses(${p.page+1})">Next</button>
        </div>`;
}

async function setStatus(id, status) {
    if (!status) return;
    const json = await Api.call('/api/admin/businesses.php', { method: 'POST', body: { id, status } });
    if (json.success) { Toast.success('Business updated.'); loadBusinesses(); } else { Toast.error(json.message); }
}

['f-search','f-status'].forEach(id => document.getElementById(id).addEventListener('change', () => loadBusinesses(1)));
document.getElementById('f-search').addEventListener('keyup', e => { if (e.key === 'Enter') loadBusinesses(1); });
loadBusinesses(1);
</script>
</body>
</html>
