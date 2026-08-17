<?php
$pageTitle = 'Quotations';
$activePage = 'quotations';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quotations — BharatAI Business OS</title>
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
                <input id="f-search" class="form-control" placeholder="Search quotations..." style="max-width:240px;">
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-secondary" style="width:auto;" id="btn-ai"><i data-lucide="sparkles" style="width:15px;height:15px;"></i> Generate with AI</button>
                    <button class="btn btn-primary" style="width:auto;" id="btn-new"><i data-lucide="plus" style="width:15px;height:15px;"></i> New Quotation</button>
                </div>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Number</th><th>Customer</th><th>Total</th><th>Status</th><th>Created</th><th></th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="6"><div class="skeleton" style="height:180px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="ai-modal">
    <div class="modal-box">
        <h2>Generate Quote with AI</h2>
        <p style="font-size:13px;color:var(--color-text-muted);">Uses your actual product/service catalog for pricing.</p>
        <div class="form-group"><label>Customer requirement</label><textarea id="ai-requirement" class="form-control" rows="4"></textarea></div>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-secondary" onclick="document.getElementById('ai-modal').classList.remove('open')">Cancel</button>
            <button class="btn btn-primary" id="ai-submit">Generate</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="manual-modal">
    <div class="modal-box" style="max-width:680px;">
        <h2>New Quotation</h2>
        <form id="manual-form">
            <div id="items-list"></div>
            <button type="button" class="btn btn-secondary" onclick="addItemRow()" style="margin-bottom:12px;">+ Add Item</button>
            <div class="form-group"><label>Notes</label><textarea id="m-notes" class="form-control" rows="2"></textarea></div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('manual-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Quotation</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;
let itemRowCount = 0;

function statusBadge(s) {
    const map = { draft: 'gray', sent: 'blue', accepted: 'green', rejected: 'red', expired: 'yellow' };
    return `<span class="badge badge-${map[s] || 'gray'}">${s}</span>`;
}

function addItemRow() {
    itemRowCount++;
    const div = document.createElement('div');
    div.className = 'grid grid-4';
    div.style = 'margin-bottom:8px;';
    div.innerHTML = `
        <input class="form-control item-name" placeholder="Item name">
        <input type="number" class="form-control item-qty" placeholder="Qty" value="1">
        <input type="number" class="form-control item-price" placeholder="Unit price" value="0">
        <input type="number" class="form-control item-tax" placeholder="Tax %" value="0">
    `;
    document.getElementById('items-list').appendChild(div);
}

async function load(page = 1) {
    const params = new URLSearchParams({ business_id: businessId, page, search: document.getElementById('f-search').value });
    const json = await Api.call('/api/business/quotations.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="6"><div class="empty-state">No quotations yet.</div></td></tr>'
        : json.data.items.map(q => `<tr>
            <td>${q.quote_number}</td><td>${q.customer_name || '-'}</td><td>₹${Number(q.total).toLocaleString()}</td>
            <td>${statusBadge(q.status)}</td><td>${new Date(q.created_at).toLocaleDateString()}</td>
            <td><a href="/dashboard/print-document.php?business_id=${businessId}&type=quotation&id=${q.id}" target="_blank" class="btn btn-secondary" style="width:auto;padding:6px 10px;display:inline-flex;"><i data-lucide="printer" style="width:14px;height:14px;"></i></a></td>
        </tr>`).join('');
    if (window.lucide) lucide.createIcons();

    const pg = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${pg.page} of ${pg.total_pages || 1}</span>
        <div style="display:flex;gap:6px;"><button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page<=1?'disabled':''} onclick="load(${pg.page-1})">Prev</button>
        <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page>=pg.total_pages?'disabled':''} onclick="load(${pg.page+1})">Next</button></div>`;
}

document.getElementById('f-search').addEventListener('keyup', e => { if (e.key === 'Enter') load(1); });
document.getElementById('btn-ai').addEventListener('click', () => document.getElementById('ai-modal').classList.add('open'));
document.getElementById('btn-new').addEventListener('click', () => { document.getElementById('items-list').innerHTML = ''; addItemRow(); document.getElementById('manual-modal').classList.add('open'); });

document.getElementById('ai-submit').addEventListener('click', async () => {
    const requirement = document.getElementById('ai-requirement').value.trim();
    if (!requirement) return;
    Toast.success('Generating with AI...');
    const json = await Api.call('/api/ai/generate-quote.php', { method: 'POST', body: { business_id: businessId, requirement } });
    if (json.success) { Toast.success('Quote generated!'); document.getElementById('ai-modal').classList.remove('open'); load(1); }
    else { Toast.error(json.message || 'AI generation failed.'); }
});

document.getElementById('manual-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('.item-name').forEach((el, i) => {
        const row = el.closest('div');
        items.push({
            name: el.value,
            quantity: row.querySelector('.item-qty').value,
            unit_price: row.querySelector('.item-price').value,
            tax_percent: row.querySelector('.item-tax').value,
        });
    });
    const json = await Api.call('/api/business/quotations.php', { method: 'POST', body: { business_id: businessId, items, notes: document.getElementById('m-notes').value } });
    if (json.success) { Toast.success('Quotation created.'); document.getElementById('manual-modal').classList.remove('open'); load(1); }
    else { Toast.error(json.message); }
});

load(1);
</script>
</body>
</html>
