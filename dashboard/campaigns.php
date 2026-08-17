<?php
$pageTitle = 'Campaigns';
$activePage = 'campaigns';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campaigns — BharatAI Business OS</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;display:flex;justify-content:flex-end;">
                <button class="btn btn-primary" style="width:auto;" id="btn-new"><i data-lucide="plus" style="width:15px;height:15px;"></i> New Campaign</button>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Type</th><th>Recipients</th><th>Status</th><th>Created</th><th></th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="6"><div class="skeleton" style="height:180px;"></div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="campaign-modal">
    <div class="modal-box">
        <h2>New Campaign</h2>
        <form id="campaign-form">
            <div class="form-group"><label>Campaign name</label><input id="c-name" class="form-control" required></div>
            <div class="form-group"><label>Recipients</label>
                <select id="c-recipients" class="form-control"><option value="customers">Customers</option><option value="leads">Leads</option></select>
            </div>
            <div class="form-group"><label>Subject</label><input id="c-subject" class="form-control"></div>
            <div class="form-group"><label>Message</label><textarea id="c-body" class="form-control" rows="4"></textarea></div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('campaign-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Campaign</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

function statusBadge(s) {
    const map = { draft: 'gray', scheduled: 'blue', sending: 'yellow', sent: 'green', cancelled: 'red' };
    return `<span class="badge badge-${map[s] || 'gray'}">${s}</span>`;
}

async function load() {
    const json = await Api.call('/api/business/campaigns.php?business_id=' + businessId);
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.length === 0
        ? '<tr><td colspan="6"><div class="empty-state">No campaigns yet.</div></td></tr>'
        : json.data.map(c => `<tr>
            <td>${c.name}</td><td>${c.type}</td><td>${c.recipient_count}</td><td>${statusBadge(c.status)}</td>
            <td>${new Date(c.created_at).toLocaleDateString()}</td>
            <td>${c.status === 'draft' ? `<button class="btn btn-primary" style="width:auto;padding:6px 12px;font-size:12px;" onclick="sendCampaign(${c.id})">Send Now</button>` : ''}</td>
        </tr>`).join('');
}

document.getElementById('btn-new').addEventListener('click', () => document.getElementById('campaign-modal').classList.add('open'));

document.getElementById('campaign-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call('/api/business/campaigns.php', {
        method: 'POST',
        body: { business_id: businessId, name: document.getElementById('c-name').value, recipient_type: document.getElementById('c-recipients').value, subject: document.getElementById('c-subject').value, body: document.getElementById('c-body').value },
    });
    if (json.success) { Toast.success('Campaign created.'); document.getElementById('campaign-modal').classList.remove('open'); e.target.reset(); load(); }
    else { Toast.error(json.message); }
});

async function sendCampaign(id) {
    if (!confirm('Send this campaign now to all recipients?')) return;
    Toast.success('Sending campaign...');
    const json = await Api.call('/api/business/campaign-send.php', { method: 'POST', body: { business_id: businessId, campaign_id: id } });
    if (json.success) { Toast.success(`Campaign sent to ${json.data.sent_count} recipients.`); load(); } else { Toast.error(json.message); }
}

load();
</script>
</body>
</html>
