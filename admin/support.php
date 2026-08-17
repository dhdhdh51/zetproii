<?php
$pageTitle = 'Support Tickets';
$activePage = 'support';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support Tickets — Admin | BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" async></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle menu" aria-expanded="false"><i data-lucide="menu"></i></button><h1>Support Tickets</h1></div>
            <button class="theme-toggle" type="button" aria-label="Switch between light and dark theme"><i data-lucide="sun-moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;">
                <select id="f-status" class="form-control" style="max-width:200px;" aria-label="Filter by status">
                    <option value="">All statuses</option><option value="open">Open</option><option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option><option value="closed">Closed</option>
                </select>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Subject</th><th>User</th><th>Priority</th><th>Status</th><th>Created</th><th></th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="6"><div class="skeleton" style="height:200px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="ticket-modal"><div class="modal-box" id="ticket-modal-content"></div></div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
function statusBadge(s) {
    const map = { open: 'blue', in_progress: 'yellow', resolved: 'green', closed: 'gray' };
    return `<span class="badge badge-${map[s] || 'gray'}">${(s || '').replace('_',' ')}</span>`;
}

async function load(page = 1) {
    const params = new URLSearchParams({ page, status: document.getElementById('f-status').value });
    const json = await Api.call(appBase() + '/api/admin/support-tickets.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="6"><div class="empty-state">No tickets found.</div></td></tr>'
        : json.data.items.map(t => `<tr>
            <td><a href="#" onclick="openTicket(${t.id});return false;">${t.subject}</a></td>
            <td>${t.user_name || '-'}<br><small>${t.business_name || ''}</small></td>
            <td><span class="badge badge-gray">${t.priority}</span></td><td>${statusBadge(t.status)}</td>
            <td>${new Date(t.created_at).toLocaleDateString()}</td><td></td>
        </tr>`).join('');

    const p = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${p.page} of ${p.total_pages || 1}</span>
        <div style="display:flex;gap:6px;"><button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page<=1?'disabled':''} onclick="load(${p.page-1})">Prev</button>
        <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page>=p.total_pages?'disabled':''} onclick="load(${p.page+1})">Next</button></div>`;
}

document.getElementById('f-status').addEventListener('change', () => load(1));

async function openTicket(id) {
    const json = await Api.call(appBase() + '/api/admin/support-tickets.php?id=' + id);
    if (!json.success) { Toast.error(json.message); return; }
    const t = json.data;
    document.getElementById('ticket-modal-content').innerHTML = `
        <h2>${t.subject}</h2>
        <p>${statusBadge(t.status)} <span class="badge badge-gray">${t.priority}</span></p>
        <p style="font-size:14px;">${t.description}</p>
        <hr style="margin:14px 0;border-color:var(--border);">
        <div>${t.replies.map(r => `<div style="padding:8px 0;border-bottom:1px solid var(--border);"><strong>${r.is_admin_reply ? 'Support Team' : (r.user_name||'User')}:</strong> ${r.message}</div>`).join('') || '<p style="color:var(--text-muted);font-size:13px;">No replies yet.</p>'}</div>
        <div class="form-group" style="margin-top:12px;"><textarea id="reply-text" class="form-control" rows="2" placeholder="Reply to customer..." aria-label="Reply to customer"></textarea></div>
        <select id="status-select" class="form-control" style="margin-bottom:10px;" aria-label="Status select">
            <option value="open" ${t.status==='open'?'selected':''}>Open</option><option value="in_progress" ${t.status==='in_progress'?'selected':''}>In Progress</option>
            <option value="resolved" ${t.status==='resolved'?'selected':''}>Resolved</option><option value="closed" ${t.status==='closed'?'selected':''}>Closed</option>
        </select>
        <button class="btn btn-primary" onclick="saveTicket(${t.id})">Save</button>
        <button class="btn btn-secondary" style="margin-top:8px;" onclick="document.getElementById('ticket-modal').classList.remove('open')">Close</button>
    `;
    document.getElementById('ticket-modal').classList.add('open');
}

async function saveTicket(id) {
    const json = await Api.call(appBase() + '/api/admin/support-tickets.php', {
        method: 'POST',
        body: { id, reply: document.getElementById('reply-text').value, status: document.getElementById('status-select').value },
    });
    if (json.success) { Toast.success('Ticket updated.'); document.getElementById('ticket-modal').classList.remove('open'); load(); } else { Toast.error(json.message); }
}

load(1);
</script>
</body>
</html>
