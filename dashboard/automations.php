<?php
$pageTitle = 'Automations';
$activePage = 'automations';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Automations — BharatSEO</title>
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
                <h3 style="margin-top:0;">Create Automation Rule</h3>
                <form id="rule-form">
                    <div class="grid grid-2">
                        <div class="form-group"><label for="r-name">Rule name</label><input id="r-name" class="form-control" required></div>
                        <div class="form-group"><label for="r-trigger">Trigger event</label>
                            <select id="r-trigger" class="form-control">
                                <option value="lead.created">New lead created</option>
                                <option value="lead.qualified">Lead becomes qualified</option>
                                <option value="lead.won">Lead won</option>
                                <option value="lead.no_response_2d">No response after 2 days</option>
                                <option value="customer.created">New customer created</option>
                                <option value="payment.completed">Payment completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label for="r-action-type">Action</label>
                        <select id="r-action-type" class="form-control">
                            <option value="send_email">Send welcome email</option>
                            <option value="create_task">Create follow-up task</option>
                            <option value="notify_user">Notify assigned salesperson</option>
                            <option value="create_followup">Create follow-up reminder</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Create Rule</button>
                </form>
            </div>
            <div class="card">
                <h3 style="margin-top:0;">Active Rules</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Trigger</th><th>Runs</th><th>Active</th><th></th></tr></thead>
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
    const json = await Api.call(appBase() + '/api/business/automations.php?business_id=' + businessId);
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.length === 0
        ? '<tr><td colspan="5"><div class="empty-state">No automation rules yet.</div></td></tr>'
        : json.data.map(r => `<tr>
            <td>${r.name}</td><td><span class="badge badge-blue">${r.trigger_event}</span></td><td>${r.run_count}</td>
            <td><input type="checkbox" ${r.is_active ? 'checked' : ''} onchange="toggleRule(${r.id}, this.checked)"></td>
            <td><button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick="deleteRule(${r.id})"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button></td>
        </tr>`).join('');
    if (window.lucide) lucide.createIcons();
}

document.getElementById('rule-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const actionType = document.getElementById('r-action-type').value;
    const actions = [{ type: actionType, title: 'Follow up', due_in_hours: 24, in_hours: 48, template: 'lead_notification' }];
    const json = await Api.call(appBase() + '/api/business/automations.php', {
        method: 'POST',
        body: { business_id: businessId, name: document.getElementById('r-name').value, trigger_event: document.getElementById('r-trigger').value, actions },
    });
    if (json.success) { Toast.success('Rule created.'); e.target.reset(); load(); } else { Toast.error(json.message); }
});

async function toggleRule(id, active) {
    const json = await Api.call(appBase() + '/api/business/automations.php', { method: 'PUT', body: { business_id: businessId, id, is_active: active } });
    if (json.success) { Toast.success('Rule updated.'); } else { Toast.error(json.message); }
}

async function deleteRule(id) {
    if (!confirm('Delete this rule?')) return;
    const json = await Api.call(appBase() + '/api/business/automations.php', { method: 'DELETE', body: { business_id: businessId, id } });
    if (json.success) { Toast.success('Rule deleted.'); load(); } else { Toast.error(json.message); }
}

load();
</script>
</body>
</html>
