<?php
$pageTitle = 'Team';
$activePage = 'team';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Team — BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;">
                <h3 style="margin-top:0;">Invite Team Member</h3>
                <p style="font-size:13px;color:var(--color-text-muted);">The user must already have a BharatAI account with this email.</p>
                <form id="invite-form" style="display:flex;gap:10px;flex-wrap:wrap;">
                    <input id="inv-email" type="email" class="form-control" placeholder="team@example.com" style="max-width:240px;" required>
                    <select id="inv-role" class="form-control" style="max-width:180px;">
                        <option value="MANAGER">Manager</option><option value="STAFF">Staff</option>
                    </select>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Invite</button>
                </form>
            </div>
            <div class="card">
                <h3 style="margin-top:0;">Team Members</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="5"><div class="skeleton" style="height:120px;"></div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

async function load() {
    const json = await Api.call('' + window.__BASE__ + '/api/business/team.php?business_id=' + businessId);
    if (!json.success) { Toast.error(json.message); return; }
    const rows = [`<tr><td>${json.data.owner.name}</td><td>${json.data.owner.email}</td><td><span class="badge badge-blue">Owner</span></td><td><span class="badge badge-green">active</span></td><td></td></tr>`]
        .concat(json.data.members.map(m => `<tr>
            <td>${m.name}</td><td>${m.email}</td><td><span class="badge badge-blue">${m.role}</span></td><td><span class="badge badge-green">${m.status}</span></td>
            <td><button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick="removeMember(${m.id})"><i data-lucide="user-minus" style="width:14px;height:14px;"></i></button></td>
        </tr>`));
    document.getElementById('tbody').innerHTML = rows.join('');
    if (window.lucide) lucide.createIcons();
}

document.getElementById('invite-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call('' + window.__BASE__ + '/api/business/team.php', {
        method: 'POST',
        body: { business_id: businessId, email: document.getElementById('inv-email').value, role: document.getElementById('inv-role').value },
    });
    if (json.success) { Toast.success('Team member added.'); e.target.reset(); load(); } else { Toast.error(json.message); }
});

async function removeMember(id) {
    if (!confirm('Remove this team member?')) return;
    const json = await Api.call('' + window.__BASE__ + '/api/business/team.php', { method: 'DELETE', body: { business_id: businessId, member_id: id } });
    if (json.success) { Toast.success('Removed.'); load(); } else { Toast.error(json.message); }
}

load();
</script>
</body>
</html>
