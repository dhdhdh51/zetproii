<?php
$pageTitle = 'Coupons';
$activePage = 'coupons';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Coupons — Admin | BharatAI Business OS</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">Coupons</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;">
                <form id="coupon-form" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                    <div class="form-group" style="margin:0;"><label>Code</label><input id="c-code" class="form-control" placeholder="SAVE20" required></div>
                    <div class="form-group" style="margin:0;"><label>Type</label><select id="c-type" class="form-control"><option value="percent">Percent</option><option value="fixed">Fixed</option></select></div>
                    <div class="form-group" style="margin:0;"><label>Value</label><input id="c-value" type="number" class="form-control" required></div>
                    <div class="form-group" style="margin:0;"><label>Max redemptions</label><input id="c-max" type="number" class="form-control"></div>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Create Coupon</button>
                </form>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Code</th><th>Discount</th><th>Redeemed</th><th>Active</th><th></th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="5"><div class="skeleton" style="height:150px;"></div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/app.js"></script>
<script>
async function load() {
    const json = await Api.call('/api/admin/coupons.php');
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.length === 0
        ? '<tr><td colspan="5"><div class="empty-state">No coupons yet.</div></td></tr>'
        : json.data.map(c => `<tr>
            <td><code>${c.code}</code></td><td>${c.discount_type === 'percent' ? c.discount_value + '%' : '₹' + c.discount_value}</td>
            <td>${c.times_redeemed}${c.max_redemptions ? ' / ' + c.max_redemptions : ''}</td>
            <td>${c.is_active ? '✅' : '❌'}</td>
            <td><button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick="deleteCoupon(${c.id})"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button></td>
        </tr>`).join('');
    if (window.lucide) lucide.createIcons();
}

document.getElementById('coupon-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call('/api/admin/coupons.php', {
        method: 'POST',
        body: { code: document.getElementById('c-code').value, discount_type: document.getElementById('c-type').value, discount_value: document.getElementById('c-value').value, max_redemptions: document.getElementById('c-max').value || null },
    });
    if (json.success) { Toast.success('Coupon created.'); e.target.reset(); load(); } else { Toast.error(json.message); }
});

async function deleteCoupon(id) {
    if (!confirm('Delete this coupon?')) return;
    const json = await Api.call('/api/admin/coupons.php', { method: 'DELETE', body: { id } });
    if (json.success) { Toast.success('Deleted.'); load(); } else { Toast.error(json.message); }
}

load();
</script>
</body>
</html>
