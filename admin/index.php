<?php
$pageTitle = 'Admin Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">Platform Overview</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body">
            <div id="metrics-grid" class="grid grid-4" style="margin-bottom:20px;">
                <?php for ($i = 0; $i < 8; $i++): ?><div class="card"><div class="skeleton" style="height:60px;"></div></div><?php endfor; ?>
            </div>
            <div class="grid grid-2">
                <div class="card"><div class="card-title">New Signups (30 days)</div><canvas id="chart-signups" height="180"></canvas></div>
                <div class="card"><div class="card-title">Plan Distribution</div><canvas id="chart-plans" height="180"></canvas></div>
            </div>
        </div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
async function loadDashboard() {
    const json = await Api.call('' + window.__BASE__ + '/api/admin/dashboard.php');
    if (!json.success) { Toast.error(json.message); return; }
    const d = json.data;
    document.getElementById('metrics-grid').innerHTML = [
        ['Total Users', d.total_users], ['Total Businesses', d.total_businesses],
        ['Active Subscriptions', d.active_subscriptions], ['Total Revenue', '₹' + Number(d.total_revenue).toLocaleString()],
        ['AI Requests Today', d.ai_requests_today], ['Open Support Tickets', d.open_tickets],
        ['New Signups (7d)', d.new_signups_7d], ['Failed AI Requests (24h)', d.failed_ai_requests_24h],
    ].map(([t, v]) => `<div class="card"><div class="card-title">${t}</div><p class="card-value">${v}</p></div>`).join('');

    new Chart(document.getElementById('chart-signups'), {
        type: 'line',
        data: { labels: d.signups_over_time.map(r => r.d), datasets: [{ label: 'Signups', data: d.signups_over_time.map(r => r.c), borderColor: '#4f46e5' }] },
        options: { plugins: { legend: { display: false } } },
    });
    new Chart(document.getElementById('chart-plans'), {
        type: 'doughnut',
        data: { labels: d.plan_distribution.map(r => r.name), datasets: [{ data: d.plan_distribution.map(r => r.c), backgroundColor: ['#4f46e5','#06b6d4','#22c55e','#f59e0b','#ef4444'] }] },
    });
}
loadDashboard();
</script>
</body>
</html>
