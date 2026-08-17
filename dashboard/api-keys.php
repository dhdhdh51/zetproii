<?php
$pageTitle = 'API & Webhooks';
$activePage = '';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API & Webhooks — BharatAI Business OS</title>
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
            <div class="card" style="margin-bottom:16px;">
                <h3 style="margin-top:0;">API Keys</h3>
                <form id="key-form" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
                    <input id="k-name" class="form-control" placeholder="Key name (e.g. Zapier integration)" style="max-width:260px;" required>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Generate Key</button>
                </form>
                <div id="new-key-display" style="display:none;margin-bottom:16px;" class="alert alert-success show"></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Prefix</th><th>Last Used</th><th>Status</th><th></th></tr></thead>
                        <tbody id="keys-tbody"><tr><td colspan="5"><div class="skeleton" style="height:100px;"></div></td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top:0;">Webhooks</h3>
                <form id="webhook-form" style="margin-bottom:16px;">
                    <div class="form-group"><label>Target URL (HTTPS)</label><input id="w-url" class="form-control" placeholder="https://yourapp.com/webhook" required></div>
                    <div class="form-group"><label>Events</label>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                            <?php foreach (['lead.created','lead.updated','lead.qualified','lead.won','customer.created','proposal.created','payment.completed','subscription.updated','chat.lead_created'] as $ev): ?>
                            <label style="font-size:13px;"><input type="checkbox" class="webhook-event" value="<?= $ev ?>"> <?= $ev ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Create Webhook</button>
                </form>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>URL</th><th>Events</th><th>Active</th><th></th></tr></thead>
                        <tbody id="webhooks-tbody"><tr><td colspan="4"><div class="skeleton" style="height:100px;"></div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

async function loadKeys() {
    const json = await Api.call('/api/business/api-keys.php?business_id=' + businessId);
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('keys-tbody').innerHTML = json.data.length === 0
        ? '<tr><td colspan="5"><div class="empty-state">No API keys yet.</div></td></tr>'
        : json.data.map(k => `<tr>
            <td>${k.name}</td><td><code>${k.key_prefix}...</code></td>
            <td>${k.last_used_at ? new Date(k.last_used_at).toLocaleString() : 'Never'}</td>
            <td>${k.revoked_at ? '<span class="badge badge-red">Revoked</span>' : '<span class="badge badge-green">Active</span>'}</td>
            <td>${!k.revoked_at ? `<button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick="revokeKey(${k.id})">Revoke</button>` : ''}</td>
        </tr>`).join('');
}

document.getElementById('key-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call('/api/business/api-keys.php', { method: 'POST', body: { business_id: businessId, name: document.getElementById('k-name').value } });
    if (json.success) {
        const box = document.getElementById('new-key-display');
        box.style.display = 'block';
        box.innerHTML = `<strong>Your new API key (copy it now, it won't be shown again):</strong><br><code>${json.data.raw_key}</code>`;
        e.target.reset();
        loadKeys();
    } else { Toast.error(json.message); }
});

async function revokeKey(id) {
    if (!confirm('Revoke this API key? This cannot be undone.')) return;
    const json = await Api.call('/api/business/api-keys.php', { method: 'DELETE', body: { business_id: businessId, id } });
    if (json.success) { Toast.success('Key revoked.'); loadKeys(); } else { Toast.error(json.message); }
}

async function loadWebhooks() {
    const json = await Api.call('/api/business/webhooks.php?business_id=' + businessId);
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('webhooks-tbody').innerHTML = json.data.length === 0
        ? '<tr><td colspan="4"><div class="empty-state">No webhooks configured.</div></td></tr>'
        : json.data.map(w => `<tr>
            <td>${w.target_url}</td><td>${JSON.parse(w.events).join(', ')}</td>
            <td>${w.is_active ? '✅' : '❌'}</td>
            <td><button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick="deleteWebhook(${w.id})"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button></td>
        </tr>`).join('');
    if (window.lucide) lucide.createIcons();
}

document.getElementById('webhook-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const events = Array.from(document.querySelectorAll('.webhook-event:checked')).map(el => el.value);
    if (events.length === 0) { Toast.error('Select at least one event.'); return; }
    const json = await Api.call('/api/business/webhooks.php', { method: 'POST', body: { business_id: businessId, target_url: document.getElementById('w-url').value, events } });
    if (json.success) { Toast.success('Webhook created.'); e.target.reset(); loadWebhooks(); } else { Toast.error(json.message); }
});

async function deleteWebhook(id) {
    if (!confirm('Delete this webhook?')) return;
    const json = await Api.call('/api/business/webhooks.php', { method: 'DELETE', body: { business_id: businessId, id } });
    if (json.success) { Toast.success('Deleted.'); loadWebhooks(); } else { Toast.error(json.message); }
}

loadKeys();
loadWebhooks();
</script>
</body>
</html>
