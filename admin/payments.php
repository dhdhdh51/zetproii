<?php
$pageTitle = 'Payments';
$activePage = 'payments';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments — Admin | BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">Payments</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Business</th><th>Gateway</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="5"><div class="skeleton" style="height:200px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
function statusBadge(s) {
    const map = { success: 'green', created: 'gray', pending: 'yellow', failed: 'red', refunded: 'blue' };
    return `<span class="badge badge-${map[s] || 'gray'}">${s}</span>`;
}
async function load(page = 1) {
    const json = await Api.call('' + window.__BASE__ + '/api/admin/payments.php?page=' + page);
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="5"><div class="empty-state">No payments found.</div></td></tr>'
        : json.data.items.map(p => `<tr><td>${p.business_name}</td><td>${p.gateway}</td><td>₹${Number(p.amount).toLocaleString()}</td><td>${statusBadge(p.status)}</td><td>${new Date(p.created_at).toLocaleDateString()}</td></tr>`).join('');
    const pg = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${pg.page} of ${pg.total_pages || 1}</span>
        <div style="display:flex;gap:6px;"><button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page<=1?'disabled':''} onclick="load(${pg.page-1})">Prev</button>
        <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page>=pg.total_pages?'disabled':''} onclick="load(${pg.page+1})">Next</button></div>`;
}
load(1);
</script>
</body>
</html>
