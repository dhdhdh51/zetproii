<?php
$pageTitle = 'Plans';
$activePage = 'plans';
require_once __DIR__ . '/_init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plans — Admin | BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left"><button class="sidebar-toggle" id="sidebar-toggle"><i data-lucide="menu"></i></button><h2 style="font-size:17px;margin:0;">Plans</h2></div>
            <button class="theme-toggle"><i data-lucide="moon"></i></button>
        </header>
        <div class="page-body" id="plans-container"><div class="skeleton" style="height:300px;"></div></div>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
async function loadPlans() {
    const json = await Api.call('' + window.__BASE__ + '/api/admin/plans.php');
    const container = document.getElementById('plans-container');
    if (!json.success) { container.innerHTML = '<div class="empty-state">Failed to load plans.</div>'; return; }
    container.innerHTML = '<div class="grid grid-3">' + json.data.map(p => `
        <div class="card">
            <div style="display:flex;justify-content:space-between;"><h3 style="margin:0;">${p.name}</h3><span class="badge badge-blue">${p.subscriber_count} subscribers</span></div>
            <div class="form-group" style="margin-top:10px;"><label>Monthly Price</label><input type="number" class="form-control plan-price-m" data-id="${p.id}" value="${p.price_monthly}"></div>
            <div class="form-group"><label>Yearly Price</label><input type="number" class="form-control plan-price-y" data-id="${p.id}" value="${p.price_yearly}"></div>
            <label><input type="checkbox" class="plan-active" data-id="${p.id}" ${p.is_active ? 'checked' : ''}> Active</label>
            <div style="margin-top:10px;font-size:12.5px;color:var(--color-text-muted);">
                ${p.features.map(f => `${f.feature_key}: <strong>${f.feature_value}</strong>`).join(' · ')}
            </div>
            <button class="btn btn-primary" style="margin-top:10px;" onclick="savePlan(${p.id}, '${p.name}')">Save</button>
        </div>
    `).join('') + '</div>';
}

async function savePlan(id, name) {
    const price_monthly = document.querySelector(`.plan-price-m[data-id="${id}"]`).value;
    const price_yearly = document.querySelector(`.plan-price-y[data-id="${id}"]`).value;
    const is_active = document.querySelector(`.plan-active[data-id="${id}"]`).checked;
    const json = await Api.call('' + window.__BASE__ + '/api/admin/plans.php', { method: 'POST', body: { id, name, price_monthly, price_yearly, is_active } });
    if (json.success) { Toast.success('Plan updated.'); loadPlans(); } else { Toast.error(json.message); }
}

loadPlans();
</script>
</body>
</html>
