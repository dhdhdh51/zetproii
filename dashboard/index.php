<?php
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — BharatAI Business OS</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">

            <!-- Quick actions -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-title" style="margin-bottom:12px;">Quick Actions</div>
                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                    <a href="/dashboard/leads.php?action=new" class="btn btn-secondary" style="width:auto;"><i data-lucide="user-plus" style="width:16px;height:16px;"></i> Add Lead</a>
                    <a href="/dashboard/customers.php?action=new" class="btn btn-secondary" style="width:auto;"><i data-lucide="user-plus" style="width:16px;height:16px;"></i> Add Customer</a>
                    <a href="/dashboard/ai-assistant.php" class="btn btn-secondary" style="width:auto;"><i data-lucide="bot" style="width:16px;height:16px;"></i> Start AI Chat</a>
                    <a href="/dashboard/proposals.php?action=new" class="btn btn-secondary" style="width:auto;"><i data-lucide="file-text" style="width:16px;height:16px;"></i> Generate Proposal</a>
                    <a href="/dashboard/quotations.php?action=new" class="btn btn-secondary" style="width:auto;"><i data-lucide="file-spreadsheet" style="width:16px;height:16px;"></i> Generate Quote</a>
                    <a href="/dashboard/campaigns.php?action=new" class="btn btn-secondary" style="width:auto;"><i data-lucide="send" style="width:16px;height:16px;"></i> Create Campaign</a>
                    <a href="/dashboard/knowledge.php?action=new" class="btn btn-secondary" style="width:auto;"><i data-lucide="book-open" style="width:16px;height:16px;"></i> Add Knowledge Source</a>
                </div>
            </div>

            <!-- Widgets -->
            <div id="widgets-grid" class="grid grid-4" style="margin-bottom:20px;">
                <?php for ($i = 0; $i < 8; $i++): ?>
                <div class="card"><div class="skeleton" style="height:60px;"></div></div>
                <?php endfor; ?>
            </div>

            <!-- Charts -->
            <div class="grid grid-2" style="margin-bottom:20px;">
                <div class="card"><div class="card-title">Leads Over Time (30 days)</div><canvas id="chart-leads" height="180"></canvas></div>
                <div class="card"><div class="card-title">Conversion Funnel</div><canvas id="chart-funnel" height="180"></canvas></div>
            </div>
            <div class="grid grid-2" style="margin-bottom:20px;">
                <div class="card"><div class="card-title">Revenue (6 months)</div><canvas id="chart-revenue" height="180"></canvas></div>
                <div class="card"><div class="card-title">Lead Sources</div><canvas id="chart-sources" height="180"></canvas></div>
            </div>

            <!-- Recent activity -->
            <div class="card">
                <div class="card-title" style="margin-bottom:12px;">Recent Activity</div>
                <div id="activity-list"><div class="skeleton" style="height:140px;"></div></div>
            </div>

        </div>
    </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

function formatCurrency(n) {
    return '₹' + Number(n).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function widgetCard(title, value, icon) {
    return `<div class="card">
        <div class="card-title">${title}</div>
        <p class="card-value">${value}</p>
    </div>`;
}

async function loadDashboard() {
    const json = await Api.call('/api/analytics/dashboard.php?business_id=' + businessId);
    if (!json.success) { Toast.error(json.message || 'Failed to load dashboard.'); return; }
    const d = json.data;
    const w = d.widgets;

    document.getElementById('widgets-grid').innerHTML = [
        widgetCard('Total Leads', w.total_leads),
        widgetCard('New Leads (7d)', w.new_leads),
        widgetCard('Qualified Leads', w.qualified_leads),
        widgetCard('Customers', w.total_customers),
        widgetCard('Conversion Rate', w.conversion_rate + '%'),
        widgetCard('AI Tokens Used (mo)', Number(w.ai_usage_tokens).toLocaleString()),
        widgetCard('Emails Sent (30d)', w.emails_sent),
        widgetCard('Revenue', formatCurrency(w.revenue)),
    ].join('');

    // Leads over time
    new Chart(document.getElementById('chart-leads'), {
        type: 'line',
        data: {
            labels: d.leads_over_time.map(r => r.d),
            datasets: [{ label: 'Leads', data: d.leads_over_time.map(r => r.c), borderColor: '#4f46e5', tension: 0.3 }],
        },
        options: { plugins: { legend: { display: false } } },
    });

    // Funnel
    new Chart(document.getElementById('chart-funnel'), {
        type: 'bar',
        data: {
            labels: d.conversion_funnel.map(r => r.name),
            datasets: [{ label: 'Leads', data: d.conversion_funnel.map(r => r.c), backgroundColor: '#06b6d4' }],
        },
        options: { plugins: { legend: { display: false } }, indexAxis: 'y' },
    });

    // Revenue
    new Chart(document.getElementById('chart-revenue'), {
        type: 'bar',
        data: {
            labels: d.revenue_over_time.map(r => r.ym),
            datasets: [{ label: 'Revenue', data: d.revenue_over_time.map(r => r.total), backgroundColor: '#22c55e' }],
        },
        options: { plugins: { legend: { display: false } } },
    });

    // Sources
    new Chart(document.getElementById('chart-sources'), {
        type: 'doughnut',
        data: {
            labels: d.lead_sources.map(r => r.name),
            datasets: [{ data: d.lead_sources.map(r => r.c), backgroundColor: ['#4f46e5','#06b6d4','#22c55e','#f59e0b','#ef4444','#8b5cf6','#f97316','#6b7280'] }],
        },
    });

    // Activity
    const activityList = document.getElementById('activity-list');
    if (d.recent_activity.length === 0) {
        activityList.innerHTML = '<div class="empty-state">No recent activity yet. Start by adding a lead or customer.</div>';
    } else {
        activityList.innerHTML = '<div class="table-wrap"><table class="data-table"><thead><tr><th>Type</th><th>Details</th><th>When</th></tr></thead><tbody>' +
            d.recent_activity.map(a => `<tr><td><span class="badge badge-blue">${a.type.replace('_',' ')}</span></td><td>${a.title || ''}</td><td>${new Date(a.occurred_at).toLocaleString()}</td></tr>`).join('') +
            '</tbody></table></div>';
    }
}

loadDashboard();
</script>
</body>
</html>
