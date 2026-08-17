<?php
$pageTitle = 'Support Tickets';
$activePage = '';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support — BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" async></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;">
                <h3 style="margin-top:0;">Submit a Support Request</h3>
                <form id="ticket-form">
                    <div class="grid grid-2">
                        <div class="form-group"><label for="t-subject">Subject</label><input id="t-subject" class="form-control" required></div>
                        <div class="form-group"><label for="t-priority">Priority</label>
                            <select id="t-priority" class="form-control"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select>
                        </div>
                    </div>
                    <div class="form-group"><label for="t-desc">Description</label><textarea id="t-desc" class="form-control" rows="3" required></textarea></div>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Submit Ticket</button>
                </form>
            </div>
            <div class="card">
                <h3 style="margin-top:0;">Your Tickets</h3>
                <div id="tickets-list"><div class="skeleton" style="height:140px;"></div></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="ticket-detail-modal"><div class="modal-box" id="ticket-detail-content"></div></div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

function statusBadge(s) {
    const map = { open: 'blue', in_progress: 'yellow', resolved: 'green', closed: 'gray' };
    return `<span class="badge badge-${map[s] || 'gray'}">${(s || '').replace('_',' ')}</span>`;
}

async function loadTickets() {
    const json = await Api.call(appBase() + '/api/business/support-tickets.php');
    const list = document.getElementById('tickets-list');
    if (!json.success || json.data.length === 0) { list.innerHTML = '<div class="empty-state">No support tickets yet.</div>'; return; }
    list.innerHTML = json.data.map(t => `
        <div class="card" style="margin-bottom:10px;cursor:pointer;" onclick="openTicket(${t.id})">
            <div style="display:flex;justify-content:space-between;"><strong>${t.subject}</strong>${statusBadge(t.status)}</div>
            <p style="font-size:13px;color:var(--text-muted);margin:6px 0 0;">${new Date(t.created_at).toLocaleString()}</p>
        </div>
    `).join('');
}

document.getElementById('ticket-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call(appBase() + '/api/business/support-tickets.php', {
        method: 'POST',
        body: { business_id: businessId, subject: document.getElementById('t-subject').value, description: document.getElementById('t-desc').value, priority: document.getElementById('t-priority').value },
    });
    if (json.success) { Toast.success('Ticket submitted.'); e.target.reset(); loadTickets(); } else { Toast.error(json.message); }
});

async function openTicket(id) {
    const json = await Api.call(appBase() + '/api/business/support-ticket-detail.php?id=' + id);
    if (!json.success) { Toast.error(json.message); return; }
    const t = json.data;
    document.getElementById('ticket-detail-content').innerHTML = `
        <h2>${t.subject}</h2>
        <p>${statusBadge(t.status)}</p>
        <p style="font-size:14px;">${t.description}</p>
        <hr style="margin:14px 0;border-color:var(--border);">
        <div>${t.replies.map(r => `<div style="padding:8px 0;border-bottom:1px solid var(--border);"><strong>${r.is_admin_reply ? 'Support Team' : 'You'}:</strong> ${r.message}<br><small style="color:var(--text-muted);">${new Date(r.created_at).toLocaleString()}</small></div>`).join('') || '<p style="color:var(--text-muted);font-size:13px;">No replies yet.</p>'}</div>
        <div class="form-group" style="margin-top:12px;"><textarea id="reply-text" class="form-control" rows="2" placeholder="Add a reply..." aria-label="Add a reply"></textarea></div>
        <button class="btn btn-secondary" onclick="sendReply(${t.id})">Send Reply</button>
        <button class="btn btn-secondary" style="margin-top:8px;" onclick="document.getElementById('ticket-detail-modal').classList.remove('open')">Close</button>
    `;
    document.getElementById('ticket-detail-modal').classList.add('open');
}

async function sendReply(ticketId) {
    const message = document.getElementById('reply-text').value.trim();
    if (!message) return;
    const json = await Api.call(appBase() + '/api/business/support-ticket-detail.php', { method: 'POST', body: { id: ticketId, message } });
    if (json.success) { Toast.success('Reply sent.'); openTicket(ticketId); } else { Toast.error(json.message); }
}

loadTickets();
</script>
</body>
</html>
