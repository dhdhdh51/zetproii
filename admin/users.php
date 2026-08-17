<?php
$pageTitle = 'Users';
$activePage = 'users';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users — Admin | BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">Users</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;">
                <input id="f-search" class="form-control" placeholder="Search name or email..." style="max-width:240px;">
                <select id="f-role" class="form-control" style="max-width:180px;">
                    <option value="">All roles</option>
                    <option value="SUPER_ADMIN">Super Admin</option><option value="ADMIN">Admin</option>
                    <option value="BUSINESS_OWNER">Business Owner</option><option value="MANAGER">Manager</option>
                    <option value="STAFF">Staff</option><option value="AGENCY_OWNER">Agency Owner</option><option value="AGENCY_STAFF">Agency Staff</option>
                </select>
                <select id="f-status" class="form-control" style="max-width:160px;">
                    <option value="">All statuses</option><option value="active">Active</option><option value="pending">Pending</option>
                    <option value="inactive">Inactive</option><option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Joined</th><th></th></tr></thead>
                        <tbody id="users-tbody"><tr><td colspan="7"><div class="skeleton" style="height:200px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
function statusBadge(s) {
    const map = { active: 'green', pending: 'yellow', inactive: 'gray', suspended: 'red' };
    return `<span class="badge badge-${map[s] || 'gray'}">${s}</span>`;
}

async function loadUsers(page = 1) {
    const params = new URLSearchParams({ page, search: document.getElementById('f-search').value, role: document.getElementById('f-role').value, status: document.getElementById('f-status').value });
    const json = await Api.call('' + window.__BASE__ + '/api/admin/users.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }
    const tbody = document.getElementById('users-tbody');
    tbody.innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="7"><div class="empty-state">No users found.</div></td></tr>'
        : json.data.items.map(u => `
        <tr>
            <td>${u.name}</td><td>${u.email}</td><td><span class="badge badge-blue">${u.role}</span></td><td>${statusBadge(u.status)}</td>
            <td>${u.last_login_at ? new Date(u.last_login_at).toLocaleString() : '-'}</td>
            <td>${new Date(u.created_at).toLocaleDateString()}</td>
            <td>
                <select onchange="setStatus(${u.id}, this.value)" class="form-control" style="width:auto;padding:5px 8px;font-size:12px;">
                    <option value="">Change status</option>
                    <option value="active">Activate</option><option value="inactive">Deactivate</option><option value="suspended">Suspend</option>
                </select>
            </td>
        </tr>`).join('');

    const p = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${p.page} of ${p.total_pages || 1} (${p.total} users)</span>
        <div style="display:flex;gap:6px;">
            <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page<=1?'disabled':''} onclick="loadUsers(${p.page-1})">Prev</button>
            <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page>=p.total_pages?'disabled':''} onclick="loadUsers(${p.page+1})">Next</button>
        </div>`;
}

async function setStatus(id, status) {
    if (!status) return;
    const json = await Api.call('' + window.__BASE__ + '/api/admin/users.php', { method: 'POST', body: { id, status } });
    if (json.success) { Toast.success('User updated.'); loadUsers(); } else { Toast.error(json.message); }
}

['f-search','f-role','f-status'].forEach(id => document.getElementById(id).addEventListener('change', () => loadUsers(1)));
document.getElementById('f-search').addEventListener('keyup', e => { if (e.key === 'Enter') loadUsers(1); });
loadUsers(1);
</script>
</body>
</html>
