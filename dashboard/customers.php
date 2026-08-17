<?php
$pageTitle = 'Customers';
$activePage = 'customers';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customers — BharatSEO</title>
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
                    <input id="f-search" class="form-control" placeholder="Search customers..." style="max-width:260px;">
                    <button class="btn btn-primary" style="width:auto;" id="btn-new-customer"><i data-lucide="plus" style="width:16px;height:16px;"></i> Add Customer</button>
                </div>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Total Spent</th><th>Created</th><th></th></tr></thead>
                        <tbody id="customers-tbody"><tr><td colspan="7"><div class="skeleton" style="height:200px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="customer-modal">
    <div class="modal-box">
        <h2 id="customer-modal-title">Add Customer</h2>
        <form id="customer-form">
            <input type="hidden" id="customer-id">
            <div class="grid grid-2">
                <div class="form-group"><label>Name *</label><input id="c-name" class="form-control" required></div>
                <div class="form-group"><label>Company</label><input id="c-company" class="form-control"></div>
                <div class="form-group"><label>Email</label><input id="c-email" type="email" class="form-control"></div>
                <div class="form-group"><label>Phone</label><input id="c-phone" class="form-control"></div>
                <div class="form-group"><label>City</label><input id="c-city" class="form-control"></div>
                <div class="form-group"><label>State</label><input id="c-state" class="form-control"></div>
            </div>
            <div class="form-group"><label>Address</label><input id="c-address" class="form-control"></div>
            <div style="display:flex;gap:10px;margin-top:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeCustomerModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="customer-detail-modal">
    <div class="modal-box" style="max-width:680px;">
        <div id="customer-detail-content"></div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;
let currentPage = 1;

async function loadCustomers(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({ business_id: businessId, page, search: document.getElementById('f-search').value });
    const json = await Api.call(appBase() + '/api/crm/customers.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }

    const tbody = document.getElementById('customers-tbody');
    if (json.data.items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">No customers yet. Convert a lead or add one manually.</div></td></tr>';
    } else {
        tbody.innerHTML = json.data.items.map(c => `
            <tr>
                <td><a href="#" onclick="openCustomerDetail(${c.id});return false;"><strong>${c.name}</strong></a></td>
                <td>${c.company || '-'}</td>
                <td>${c.email || '-'}</td>
                <td>${c.phone || '-'}</td>
                <td>₹${Number(c.total_spent).toLocaleString()}</td>
                <td>${new Date(c.created_at).toLocaleDateString()}</td>
                <td><button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick='editCustomer(${JSON.stringify(c)})'><i data-lucide="pencil" style="width:14px;height:14px;"></i></button></td>
            </tr>
        `).join('');
    }
    if (window.lucide) lucide.createIcons();

    const p = json.data.pagination;
    document.getElementById('pagination').innerHTML = `
        <span>Showing page ${p.page} of ${p.total_pages || 1} (${p.total} customers)</span>
        <div style="display:flex;gap:6px;">
            <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page <= 1 ? 'disabled' : ''} onclick="loadCustomers(${p.page - 1})">Prev</button>
            <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page >= p.total_pages ? 'disabled' : ''} onclick="loadCustomers(${p.page + 1})">Next</button>
        </div>`;
}

document.getElementById('f-search').addEventListener('keyup', (e) => { if (e.key === 'Enter') loadCustomers(1); });

function openCustomerModal() { document.getElementById('customer-modal').classList.add('open'); }
function closeCustomerModal() {
    document.getElementById('customer-modal').classList.remove('open');
    document.getElementById('customer-form').reset();
    document.getElementById('customer-id').value = '';
    document.getElementById('customer-modal-title').textContent = 'Add Customer';
}
document.getElementById('btn-new-customer').addEventListener('click', openCustomerModal);

function editCustomer(c) {
    document.getElementById('customer-modal-title').textContent = 'Edit Customer';
    document.getElementById('customer-id').value = c.id;
    document.getElementById('c-name').value = c.name || '';
    document.getElementById('c-company').value = c.company || '';
    document.getElementById('c-email').value = c.email || '';
    document.getElementById('c-phone').value = c.phone || '';
    document.getElementById('c-city').value = c.city || '';
    document.getElementById('c-state').value = c.state || '';
    document.getElementById('c-address').value = c.address || '';
    openCustomerModal();
}

document.getElementById('customer-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const id = document.getElementById('customer-id').value;
    const payload = {
        business_id: businessId,
        name: document.getElementById('c-name').value,
        company: document.getElementById('c-company').value,
        email: document.getElementById('c-email').value,
        phone: document.getElementById('c-phone').value,
        city: document.getElementById('c-city').value,
        state: document.getElementById('c-state').value,
        address: document.getElementById('c-address').value,
    };
    const url = id ? appBase() + '/api/crm/customer-detail.php' : appBase() + '/api/crm/customers.php';
    if (id) payload.id = id;
    const json = await Api.call(url, { method: id ? 'PUT' : 'POST', body: payload });
    if (json.success) { Toast.success(json.message); closeCustomerModal(); loadCustomers(currentPage); }
    else { Toast.error(json.message || 'Failed to save customer.'); }
});

async function openCustomerDetail(id) {
    const json = await Api.call(appBase() + '/api/crm/customer-detail.php?business_id=' + businessId + '&id=' + id);
    if (!json.success) { Toast.error(json.message); return; }
    const c = json.data;
    document.getElementById('customer-detail-content').innerHTML = `
        <h2>${c.name}</h2>
        <p style="color:var(--text-muted);">${c.email || ''} ${c.phone ? '· ' + c.phone : ''}</p>
        <div class="grid grid-3" style="margin:14px 0;">
            <div class="card"><div class="card-title">Proposals</div><p class="card-value">${c.proposals.length}</p></div>
            <div class="card"><div class="card-title">Quotations</div><p class="card-value">${c.quotations.length}</p></div>
            <div class="card"><div class="card-title">Invoices</div><p class="card-value">${c.invoices.length}</p></div>
        </div>
        <h3 style="font-size:15px;">Notes</h3>
        <div id="customer-notes-list">${(c.notes || []).map(n => `<div class="card" style="margin-bottom:8px;padding:12px;"><p style="margin:0;font-size:14px;">${n.note}</p><small style="color:var(--text-muted);">${n.user_name || ''} · ${new Date(n.created_at).toLocaleString()}</small></div>`).join('') || '<p style="color:var(--text-muted);font-size:14px;">No notes yet.</p>'}</div>
        <div class="form-group" style="margin-top:10px;"><textarea id="new-customer-note" class="form-control" placeholder="Add a note..." rows="2"></textarea></div>
        <button class="btn btn-secondary" onclick="addCustomerNote(${c.id})">Add Note</button>
        <h3 style="font-size:15px;margin-top:16px;">Activity Timeline</h3>
        <div>${(c.activities || []).map(a => `<div style="padding:6px 0;font-size:13px;border-bottom:1px solid var(--border);">${a.activity_type} <span style="color:var(--text-muted);">· ${new Date(a.created_at).toLocaleString()}</span></div>`).join('') || '<p style="color:var(--text-muted);font-size:14px;">No activity yet.</p>'}</div>
        <button class="btn btn-secondary" style="margin-top:16px;" onclick="document.getElementById('customer-detail-modal').classList.remove('open')">Close</button>
    `;
    document.getElementById('customer-detail-modal').classList.add('open');
}

async function addCustomerNote(customerId) {
    const note = document.getElementById('new-customer-note').value.trim();
    if (!note) return;
    const json = await Api.call(appBase() + '/api/crm/customer-notes.php', { method: 'POST', body: { business_id: businessId, customer_id: customerId, note } });
    if (json.success) { Toast.success('Note added.'); openCustomerDetail(customerId); } else { Toast.error(json.message); }
}

loadCustomers(1);
</script>
</body>
</html>
