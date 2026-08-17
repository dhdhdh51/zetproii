<?php
$pageTitle = 'Invoices';
$activePage = 'invoices';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoices — BharatAI Business OS</title>
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
            <div class="card" style="margin-bottom:16px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <input id="f-search" class="form-control" placeholder="Search invoices..." style="max-width:240px;">
                <button class="btn btn-primary" style="width:auto;" id="btn-new"><i data-lucide="plus" style="width:15px;height:15px;"></i> New Invoice</button>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Number</th><th>Customer</th><th>Total</th><th>Paid</th><th>Status</th><th>Created</th><th></th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="7"><div class="skeleton" style="height:180px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="manual-modal">
    <div class="modal-box" style="max-width:680px;">
        <h2>New Invoice</h2>
        <form id="manual-form">
            <div id="items-list"></div>
            <button type="button" class="btn btn-secondary" onclick="addItemRow()" style="margin-bottom:12px;">+ Add Item</button>
            <div class="form-group"><label>Due Date</label><input id="m-due" type="date" class="form-control"></div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('manual-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="payment-modal">
    <div class="modal-box">
        <h2>Record Payment</h2>
        <input type="hidden" id="payment-invoice-id">
        <div class="form-group"><label>Amount</label><input id="payment-amount" type="number" class="form-control"></div>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-secondary" onclick="document.getElementById('payment-modal').classList.remove('open')">Cancel</button>
            <button class="btn btn-primary" id="payment-submit">Record</button>
        </div>
    </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

function statusBadge(s) {
    const map = { draft: 'gray', sent: 'blue', partially_paid: 'yellow', paid: 'green', overdue: 'red', cancelled: 'gray' };
    return `<span class="badge badge-${map[s] || 'gray'}">${(s || '').replace('_',' ')}</span>`;
}

function addItemRow() {
    const div = document.createElement('div');
    div.className = 'grid grid-4';
    div.style = 'margin-bottom:8px;';
    div.innerHTML = `<input class="form-control item-name" placeholder="Item name">
        <input type="number" class="form-control item-qty" placeholder="Qty" value="1">
        <input type="number" class="form-control item-price" placeholder="Unit price" value="0">
        <input type="number" class="form-control item-tax" placeholder="Tax %" value="0">`;
    document.getElementById('items-list').appendChild(div);
}

async function load(page = 1) {
    const params = new URLSearchParams({ business_id: businessId, page, search: document.getElementById('f-search').value });
    const json = await Api.call('/api/business/invoices.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="7"><div class="empty-state">No invoices yet.</div></td></tr>'
        : json.data.items.map(inv => `<tr>
            <td>${inv.invoice_number}</td><td>${inv.customer_name || '-'}</td><td>₹${Number(inv.total).toLocaleString()}</td>
            <td>₹${Number(inv.amount_paid).toLocaleString()}</td><td>${statusBadge(inv.status)}</td><td>${new Date(inv.created_at).toLocaleDateString()}</td>
            <td style="display:flex;gap:6px;">
                <a href="/dashboard/print-document.php?business_id=${businessId}&type=invoice&id=${inv.id}" target="_blank" class="btn btn-secondary" style="width:auto;padding:6px 10px;display:inline-flex;"><i data-lucide="printer" style="width:14px;height:14px;"></i></a>
                <button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick="openPayment(${inv.id})"><i data-lucide="indian-rupee" style="width:14px;height:14px;"></i></button>
            </td>
        </tr>`).join('');
    if (window.lucide) lucide.createIcons();

    const pg = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${pg.page} of ${pg.total_pages || 1}</span>
        <div style="display:flex;gap:6px;"><button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page<=1?'disabled':''} onclick="load(${pg.page-1})">Prev</button>
        <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page>=pg.total_pages?'disabled':''} onclick="load(${pg.page+1})">Next</button></div>`;
}

document.getElementById('f-search').addEventListener('keyup', e => { if (e.key === 'Enter') load(1); });
document.getElementById('btn-new').addEventListener('click', () => { document.getElementById('items-list').innerHTML = ''; addItemRow(); document.getElementById('manual-modal').classList.add('open'); });

document.getElementById('manual-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('.item-name').forEach((el) => {
        const row = el.closest('div');
        items.push({ name: el.value, quantity: row.querySelector('.item-qty').value, unit_price: row.querySelector('.item-price').value, tax_percent: row.querySelector('.item-tax').value });
    });
    const json = await Api.call('/api/business/invoices.php', { method: 'POST', body: { business_id: businessId, items, due_date: document.getElementById('m-due').value } });
    if (json.success) { Toast.success('Invoice created.'); document.getElementById('manual-modal').classList.remove('open'); load(1); }
    else { Toast.error(json.message); }
});

function openPayment(id) {
    document.getElementById('payment-invoice-id').value = id;
    document.getElementById('payment-modal').classList.add('open');
}
document.getElementById('payment-submit').addEventListener('click', async () => {
    const invoiceId = document.getElementById('payment-invoice-id').value;
    const amount = document.getElementById('payment-amount').value;
    const json = await Api.call('/api/business/invoice-payment.php', { method: 'POST', body: { business_id: businessId, invoice_id: invoiceId, amount } });
    if (json.success) { Toast.success('Payment recorded.'); document.getElementById('payment-modal').classList.remove('open'); load(1); }
    else { Toast.error(json.message); }
});

load(1);
</script>
</body>
</html>
