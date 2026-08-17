<?php
$pageTitle = 'Analytics';
$activePage = 'analytics';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics — BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" async></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">
            <div id="widgets-grid" class="grid grid-4" style="margin-bottom:20px;">
                <?php for ($i = 0; $i < 8; $i++): ?><div class="card"><div class="skeleton" style="height:60px;"></div></div><?php endfor; ?>
            </div>
            <div class="grid grid-2" style="margin-bottom:20px;">
                <div class="card"><div class="card-title">AI Usage Over Time (14 days)</div><canvas id="chart-ai" height="180"></canvas></div>
                <div class="card"><div class="card-title">Lead Sources</div><canvas id="chart-sources" height="180"></canvas></div>
            </div>
            <div class="card">
                <div class="card-title" style="margin-bottom:12px;">Recent Activity</div>
                <div id="activity-list"><div class="skeleton" style="height:140px;"></div></div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

async function load() {
    const json = await Api.call(appBase() + '/api/analytics/dashboard.php?business_id=' + businessId);
    if (!json.success) { Toast.error(json.message); return; }
    const d = json.data;
    const w = d.widgets;

    document.getElementById('widgets-grid').innerHTML = [
        ['Total Leads', w.total_leads], ['New Leads (7d)', w.new_leads], ['Qualified Leads', w.qualified_leads], ['Customers', w.total_customers],
        ['Conversion Rate', w.conversion_rate + '%'], ['AI Tokens Used', Number(w.ai_usage_tokens).toLocaleString()], ['Emails Sent', w.emails_sent], ['Revenue', '₹' + Number(w.revenue).toLocaleString()],
    ].map(([t, v]) => `<div class="card"><div class="card-title">${t}</div><p class="card-value">${v}</p></div>`).join('');

    new Chart(document.getElementById('chart-ai'), {
        type: 'line',
        data: { labels: d.ai_usage_over_time.map(r => r.d), datasets: [{ label: 'Tokens', data: d.ai_usage_over_time.map(r => r.tokens), borderColor: '#4f46e5' }] },
        options: { plugins: { legend: { display: false } } },
    });
    new Chart(document.getElementById('chart-sources'), {
        type: 'doughnut',
        data: { labels: d.lead_sources.map(r => r.name), datasets: [{ data: d.lead_sources.map(r => r.c), backgroundColor: ['#4f46e5','#06b6d4','#22c55e','#f59e0b','#ef4444','#8b5cf6'] }] },
    });

    const activityList = document.getElementById('activity-list');
    activityList.innerHTML = d.recent_activity.length === 0
        ? '<div class="empty-state">No recent activity yet.</div>'
        : '<div class="table-wrap"><table class="data-table"><thead><tr><th>Type</th><th>Details</th><th>When</th></tr></thead><tbody>' +
          d.recent_activity.map(a => `<tr><td><span class="badge badge-blue">${a.type.replace('_',' ')}</span></td><td>${a.title || ''}</td><td>${new Date(a.occurred_at).toLocaleString()}</td></tr>`).join('') +
          '</tbody></table></div>';
}

load();
</script>
</body>
</html>
