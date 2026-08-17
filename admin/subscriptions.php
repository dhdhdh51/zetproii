<?php
$pageTitle = 'Subscriptions';
$activePage = 'subscriptions';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscriptions — Admin | BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" async></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle menu" aria-expanded="false"><i data-lucide="menu"></i></button><h1>Subscriptions</h1></div>
            <button class="theme-toggle" type="button" aria-label="Switch between light and dark theme"><i data-lucide="sun-moon"></i></button>
        </header>
        <div class="page-body">
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Business</th><th>Plan</th><th>Cycle</th><th>Status</th><th>Period End</th></tr></thead>
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
    const map = { active: 'green', trialing: 'blue', past_due: 'yellow', cancelled: 'gray', expired: 'red' };
    return `<span class="badge badge-${map[s] || 'gray'}">${s}</span>`;
}
async function load(page = 1) {
    const json = await Api.call(appBase() + '/api/admin/subscriptions.php?page=' + page);
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="5"><div class="empty-state">No subscriptions found.</div></td></tr>'
        : json.data.items.map(s => `<tr><td>${s.business_name}</td><td>${s.plan_name}</td><td>${s.billing_cycle}</td><td>${statusBadge(s.status)}</td><td>${s.current_period_end ? new Date(s.current_period_end).toLocaleDateString() : '-'}</td></tr>`).join('');
    const p = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${p.page} of ${p.total_pages || 1}</span>
        <div style="display:flex;gap:6px;"><button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page<=1?'disabled':''} onclick="load(${p.page-1})">Prev</button>
        <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${p.page>=p.total_pages?'disabled':''} onclick="load(${p.page+1})">Next</button></div>`;
}
load(1);
</script>
</body>
</html>
