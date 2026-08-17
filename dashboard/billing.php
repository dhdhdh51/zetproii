<?php
$pageTitle = 'Billing';
$activePage = 'billing';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Billing — BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;" id="current-plan-card"><div class="skeleton" style="height:80px;"></div></div>

            <div class="card" style="margin-bottom:16px;">
                <h3 style="margin-top:0;">Usage This Period</h3>
                <div id="usage-grid" class="grid grid-3"><div class="skeleton" style="height:60px;"></div></div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <h3 style="margin-top:0;">Available Plans</h3>
                <div id="plans-grid" class="grid grid-4"><div class="skeleton" style="height:200px;"></div></div>
            </div>

            <div class="card">
                <h3 style="margin-top:0;">Payment History</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Amount</th><th>Gateway</th><th>Status</th></tr></thead>
                        <tbody id="payments-tbody"><tr><td colspan="4"><div class="skeleton" style="height:100px;"></div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

async function loadBilling() {
    const json = await Api.call('' + window.__BASE__ + '/api/billing/subscription.php?business_id=' + businessId);
    if (!json.success) { Toast.error(json.message); return; }
    const { subscription, usage, plans } = json.data;

    document.getElementById('current-plan-card').innerHTML = subscription ? `
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div class="card-title">Current Plan</div>
                <p class="card-value">${subscription.plan_name} <span class="badge badge-${subscription.status === 'active' ? 'green' : 'yellow'}">${subscription.status}</span></p>
                <p style="font-size:13px;color:var(--color-text-muted);">Renews: ${subscription.current_period_end ? new Date(subscription.current_period_end).toLocaleDateString() : 'N/A'}</p>
            </div>
        </div>
    ` : '<div class="empty-state">No active subscription.</div>';

    document.getElementById('usage-grid').innerHTML = Object.entries(usage).map(([key, val]) => `
        <div class="card">
            <div class="card-title">${key.replace(/_/g, ' ')}</div>
            <p class="card-value">${val.limit === 'unlimited' ? 'Unlimited' : `${val.used} / ${val.limit}`}</p>
        </div>
    `).join('') || '<p style="color:var(--color-text-muted);">No usage data yet.</p>';

    document.getElementById('plans-grid').innerHTML = plans.map(p => `
        <div class="card" style="${subscription && subscription.plan_slug === p.slug ? 'border-color:var(--color-primary);' : ''}">
            <h3 style="margin-top:0;">${p.name}</h3>
            <p class="card-value">${Number(p.price_monthly) === 0 ? 'Free' : '₹' + Number(p.price_monthly).toLocaleString() + '/mo'}</p>
            <button class="btn ${subscription && subscription.plan_slug === p.slug ? 'btn-secondary' : 'btn-primary'}" style="margin-top:10px;" onclick="choosePlan('${p.slug}', ${p.price_monthly})" ${subscription && subscription.plan_slug === p.slug ? 'disabled' : ''}>
                ${subscription && subscription.plan_slug === p.slug ? 'Current Plan' : 'Switch to this plan'}
            </button>
        </div>
    `).join('');
}

async function choosePlan(slug, priceMonthly) {
    if (Number(priceMonthly) === 0) {
        const json = await Api.call('' + window.__BASE__ + '/api/billing/subscription.php', { method: 'POST', body: { business_id: businessId, plan_slug: slug, billing_cycle: 'monthly' } });
        if (json.success) { Toast.success('Plan updated.'); loadBilling(); } else { Toast.error(json.message); }
        return;
    }

    // Paid plan: create a payment order first. Actual checkout widget
    // integration (Razorpay Checkout.js / Stripe Elements) is loaded
    // conditionally based on which gateway the admin has enabled.
    Toast.success('Preparing checkout...');
    const orderJson = await Api.call('' + window.__BASE__ + '/api/billing/create-payment.php', { method: 'POST', body: { business_id: businessId, plan_slug: slug, billing_cycle: 'monthly' } });
    if (!orderJson.success) { Toast.error(orderJson.message); return; }
    Toast.success('Redirecting to secure checkout...');
    // In production this opens the gateway's checkout UI using orderJson.data
    // (order_id / key_id for Razorpay, client_secret for Stripe, etc).
    console.log('Payment order created:', orderJson.data);
}

function paymentStatusBadge(s) {
    const map = { success: 'green', created: 'gray', pending: 'yellow', failed: 'red', refunded: 'blue' };
    return `<span class="badge badge-${map[s] || 'gray'}">${s}</span>`;
}

async function loadPaymentHistory() {
    const json = await Api.call('' + window.__BASE__ + '/api/billing/payment-history.php?business_id=' + businessId);
    const tbody = document.getElementById('payments-tbody');
    if (!json.success) { tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state">Failed to load payment history.</div></td></tr>'; return; }
    tbody.innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="4"><div class="empty-state">No payments yet.</div></td></tr>'
        : json.data.items.map(p => `<tr>
            <td>${new Date(p.created_at).toLocaleDateString()}</td>
            <td>₹${Number(p.amount).toLocaleString()} ${p.currency}</td>
            <td>${p.gateway}</td>
            <td>${paymentStatusBadge(p.status)}</td>
        </tr>`).join('');
}

loadBilling();
loadPaymentHistory();
</script>
</body>
</html>
