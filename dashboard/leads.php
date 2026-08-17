<?php
$pageTitle = 'Leads';
$activePage = 'leads';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leads — BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">

            <div class="card" style="margin-bottom:16px;">
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                    <div style="display:flex;flex-wrap:wrap;gap:10px;flex:1;">
                        <input id="f-search" class="form-control" placeholder="Search leads..." style="max-width:220px;">
                        <select id="f-status" class="form-control" style="max-width:170px;"><option value="">All statuses</option></select>
                        <select id="f-source" class="form-control" style="max-width:170px;"><option value="">All sources</option></select>
                        <select id="f-priority" class="form-control" style="max-width:150px;">
                            <option value="">All priorities</option>
                            <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option>
                        </select>
                        <input type="date" id="f-date-from" class="form-control" style="max-width:150px;">
                        <input type="date" id="f-date-to" class="form-control" style="max-width:150px;">
                    </div>
                    <button class="btn btn-primary" style="width:auto;" id="btn-new-lead"><i data-lucide="plus" style="width:16px;height:16px;"></i> Add Lead</button>
                </div>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table class="data-table" id="leads-table">
                        <thead><tr>
                            <th>Name</th><th>Company</th><th>Status</th><th>Priority</th><th>Source</th>
                            <th>Value</th><th>Assigned</th><th>Created</th><th></th>
                        </tr></thead>
                        <tbody id="leads-tbody"><tr><td colspan="9"><div class="skeleton" style="height:200px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Lead Modal -->
<div class="modal-overlay" id="lead-modal">
    <div class="modal-box">
        <h2 id="lead-modal-title">Add Lead</h2>
        <form id="lead-form">
            <input type="hidden" id="lead-id">
            <div class="grid grid-2">
                <div class="form-group"><label>Name *</label><input id="lead-name" class="form-control" required></div>
                <div class="form-group"><label>Company</label><input id="lead-company" class="form-control"></div>
                <div class="form-group"><label>Email</label><input id="lead-email" type="email" class="form-control"></div>
                <div class="form-group"><label>Phone</label><input id="lead-phone" class="form-control"></div>
                <div class="form-group"><label>Status</label><select id="lead-status" class="form-control"></select></div>
                <div class="form-group"><label>Source</label><select id="lead-source" class="form-control"></select></div>
                <div class="form-group"><label>Priority</label>
                    <select id="lead-priority" class="form-control">
                        <option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="form-group"><label>Value</label><input id="lead-value" type="number" class="form-control"></div>
                <div class="form-group"><label>Assigned to</label><select id="lead-assigned" class="form-control"><option value="">Unassigned</option></select></div>
                <div class="form-group"><label>Next follow-up</label><input id="lead-followup" type="datetime-local" class="form-control"></div>
            </div>
            <div class="form-group"><label>Location</label><input id="lead-location" class="form-control"></div>
            <div class="form-group"><label>Requirement</label><textarea id="lead-requirement" class="form-control" rows="2"></textarea></div>
            <div class="form-group"><label>Budget</label><input id="lead-budget" class="form-control"></div>
            <div style="display:flex;gap:10px;margin-top:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeLeadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="lead-submit-btn">Save Lead</button>
            </div>
        </form>
    </div>
</div>

<!-- Lead Detail Drawer -->
<div class="modal-overlay" id="lead-detail-modal">
    <div class="modal-box" style="max-width:640px;">
        <div id="lead-detail-content"></div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;
let meta = { statuses: [], sources: [], tags: [], team_members: [] };
let currentPage = 1;

async function loadMeta() {
    const json = await Api.call(appBase() + '/api/leads/meta.php?business_id=' + businessId);
    if (!json.success) return;
    meta = json.data;
    const statusOpts = meta.statuses.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    document.getElementById('f-status').innerHTML += statusOpts;
    document.getElementById('lead-status').innerHTML = statusOpts;
    const sourceOpts = meta.sources.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    document.getElementById('f-source').innerHTML += sourceOpts;
    document.getElementById('lead-source').innerHTML = sourceOpts;
    document.getElementById('lead-assigned').innerHTML += meta.team_members.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
}

function statusBadge(s) {
    if (!s) return '<span class="badge badge-gray">-</span>';
    return `<span class="badge" style="background:${s.status_color}22;color:${s.status_color}">${s.status_name || ''}</span>`;
}

function priorityBadge(p) {
    const map = { low: 'gray', medium: 'blue', high: 'yellow', urgent: 'red' };
    return `<span class="badge badge-${map[p] || 'gray'}">${p}</span>`;
}

async function loadLeads(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({
        business_id: businessId, page,
        search: document.getElementById('f-search').value,
        status_id: document.getElementById('f-status').value,
        source_id: document.getElementById('f-source').value,
        priority: document.getElementById('f-priority').value,
        date_from: document.getElementById('f-date-from').value,
        date_to: document.getElementById('f-date-to').value,
    });
    const json = await Api.call(appBase() + '/api/leads/index.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }

    const tbody = document.getElementById('leads-tbody');
    if (json.data.items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state">No leads found. Try adjusting filters or add your first lead.</div></td></tr>';
    } else {
        tbody.innerHTML = json.data.items.map(l => `
            <tr>
                <td><a href="#" onclick="openLeadDetail(${l.id});return false;"><strong>${l.name}</strong></a><br><span style="font-size:12px;color:var(--text-muted)">${l.email || l.phone || ''}</span></td>
                <td>${l.company || '-'}</td>
                <td>${statusBadge(l)}</td>
                <td>${priorityBadge(l.priority)}</td>
                <td>${l.source_name || '-'}</td>
                <td>${l.value ? '₹' + Number(l.value).toLocaleString() : '-'}</td>
                <td>${l.assigned_user_name || '-'}</td>
                <td>${new Date(l.created_at).toLocaleDateString()}</td>
                <td><button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick='editLead(${JSON.stringify(l)})'><i data-lucide="pencil" style="width:14px;height:14px;"></i></button></td>
            </tr>
        `).join('');
    }
    if (window.lucide) lucide.createIcons();

    const p = json.data.pagination;
    document.getElementById('pagination').innerHTML = `
        <span>Showing page ${p.page} of ${p.total_pages || 1} (${p.total} leads)</span>
        <div style="display:flex;gap:6px;">
            <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page <= 1 ? 'disabled' : ''} onclick="loadLeads(${p.page - 1})">Prev</button>
            <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page >= p.total_pages ? 'disabled' : ''} onclick="loadLeads(${p.page + 1})">Next</button>
        </div>`;
}

['f-search','f-status','f-source','f-priority','f-date-from','f-date-to'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => loadLeads(1));
});
document.getElementById('f-search').addEventListener('keyup', (e) => { if (e.key === 'Enter') loadLeads(1); });

function openLeadModal() {
    document.getElementById('lead-modal').classList.add('open');
}
function closeLeadModal() {
    document.getElementById('lead-modal').classList.remove('open');
    document.getElementById('lead-form').reset();
    document.getElementById('lead-id').value = '';
    document.getElementById('lead-modal-title').textContent = 'Add Lead';
}
document.getElementById('btn-new-lead').addEventListener('click', openLeadModal);

function editLead(lead) {
    document.getElementById('lead-modal-title').textContent = 'Edit Lead';
    document.getElementById('lead-id').value = lead.id;
    document.getElementById('lead-name').value = lead.name || '';
    document.getElementById('lead-company').value = lead.company || '';
    document.getElementById('lead-email').value = lead.email || '';
    document.getElementById('lead-phone').value = lead.phone || '';
    document.getElementById('lead-status').value = lead.status_id || '';
    document.getElementById('lead-source').value = lead.source_id || '';
    document.getElementById('lead-priority').value = lead.priority || 'medium';
    document.getElementById('lead-value').value = lead.value || '';
    document.getElementById('lead-assigned').value = lead.assigned_user_id || '';
    document.getElementById('lead-location').value = lead.location || '';
    document.getElementById('lead-requirement').value = lead.requirement || '';
    document.getElementById('lead-budget').value = lead.budget || '';
    openLeadModal();
}

document.getElementById('lead-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const id = document.getElementById('lead-id').value;
    const payload = {
        business_id: businessId,
        name: document.getElementById('lead-name').value,
        company: document.getElementById('lead-company').value,
        email: document.getElementById('lead-email').value,
        phone: document.getElementById('lead-phone').value,
        status_id: document.getElementById('lead-status').value || null,
        source_id: document.getElementById('lead-source').value || null,
        priority: document.getElementById('lead-priority').value,
        value: document.getElementById('lead-value').value || null,
        assigned_user_id: document.getElementById('lead-assigned').value || null,
        location: document.getElementById('lead-location').value,
        requirement: document.getElementById('lead-requirement').value,
        budget: document.getElementById('lead-budget').value,
    };

    const url = id ? appBase() + '/api/leads/detail.php' : appBase() + '/api/leads/index.php';
    if (id) payload.id = id;
    const json = await Api.call(url, { method: id ? 'PUT' : 'POST', body: payload });
    if (json.success) {
        Toast.success(json.message);
        closeLeadModal();
        loadLeads(currentPage);
    } else {
        Toast.error(json.message || 'Failed to save lead.');
    }
});

async function openLeadDetail(id) {
    const json = await Api.call(appBase() + '/api/leads/detail.php?business_id=' + businessId + '&id=' + id);
    if (!json.success) { Toast.error(json.message); return; }
    const l = json.data;
    document.getElementById('lead-detail-content').innerHTML = `
        <h2>${l.name}</h2>
        <p style="color:var(--text-muted);">${l.email || ''} ${l.phone ? '· ' + l.phone : ''}</p>
        <div style="display:flex;gap:8px;margin:10px 0;">${statusBadge(l)}${priorityBadge(l.priority)}</div>
        <p><strong>Requirement:</strong> ${l.requirement || '-'}</p>
        <p><strong>Budget:</strong> ${l.budget || '-'}</p>
        <div style="display:flex;gap:8px;margin:14px 0;">
            <button class="btn btn-primary" style="width:auto;" onclick="qualifyLead(${l.id})"><i data-lucide="sparkles" style="width:15px;height:15px;"></i> AI Qualify Lead</button>
            <button class="btn btn-secondary" style="width:auto;" onclick="convertLead(${l.id})">Convert to Customer</button>
        </div>
        <h3 style="font-size:15px;">Notes</h3>
        <div id="lead-notes-list">${(l.notes || []).map(n => `<div class="card" style="margin-bottom:8px;padding:12px;"><p style="margin:0;font-size:14px;">${n.note}</p><small style="color:var(--text-muted);">${n.user_name || ''} · ${new Date(n.created_at).toLocaleString()}</small></div>`).join('') || '<p style="color:var(--text-muted);font-size:14px;">No notes yet.</p>'}</div>
        <div class="form-group" style="margin-top:10px;"><textarea id="new-note-text" class="form-control" placeholder="Add a note..." rows="2"></textarea></div>
        <button class="btn btn-secondary" onclick="addLeadNote(${l.id})">Add Note</button>
        <h3 style="font-size:15px;margin-top:16px;">Activity</h3>
        <div>${(l.activities || []).map(a => `<div style="padding:6px 0;font-size:13px;border-bottom:1px solid var(--border);">${a.description || a.activity_type} <span style="color:var(--text-muted);">· ${new Date(a.created_at).toLocaleString()}</span></div>`).join('') || '<p style="color:var(--text-muted);font-size:14px;">No activity yet.</p>'}</div>
        <button class="btn btn-secondary" style="margin-top:16px;" onclick="document.getElementById('lead-detail-modal').classList.remove('open')">Close</button>
    `;
    document.getElementById('lead-detail-modal').classList.add('open');
    if (window.lucide) lucide.createIcons();
}

async function addLeadNote(leadId) {
    const note = document.getElementById('new-note-text').value.trim();
    if (!note) return;
    const json = await Api.call(appBase() + '/api/leads/notes.php', { method: 'POST', body: { business_id: businessId, lead_id: leadId, note } });
    if (json.success) { Toast.success('Note added.'); openLeadDetail(leadId); } else { Toast.error(json.message); }
}

async function convertLead(leadId) {
    const json = await Api.call(appBase() + '/api/leads/convert.php', { method: 'POST', body: { business_id: businessId, lead_id: leadId } });
    if (json.success) { Toast.success('Converted to customer.'); document.getElementById('lead-detail-modal').classList.remove('open'); loadLeads(currentPage); }
    else { Toast.error(json.message); }
}

async function qualifyLead(leadId) {
    Toast.success('Running AI qualification...');
    const json = await Api.call(appBase() + '/api/ai/qualify-lead.php', { method: 'POST', body: { business_id: businessId, lead_id: leadId } });
    if (json.success) { Toast.success('Lead qualified by AI.'); openLeadDetail(leadId); loadLeads(currentPage); }
    else { Toast.error(json.message || 'AI qualification failed.'); }
}

loadMeta().then(() => loadLeads(1));
</script>
</body>
</html>
