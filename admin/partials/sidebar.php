<?php
$activePage = $activePage ?? '';
$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => url('admin/index.php')],
    ['key' => 'users', 'label' => 'Users', 'icon' => 'users', 'href' => url('admin/users.php')],
    ['key' => 'businesses', 'label' => 'Businesses', 'icon' => 'building-2', 'href' => url('admin/businesses.php')],
    ['key' => 'plans', 'label' => 'Plans', 'icon' => 'package', 'href' => url('admin/plans.php')],
    ['key' => 'subscriptions', 'label' => 'Subscriptions', 'icon' => 'credit-card', 'href' => url('admin/subscriptions.php')],
    ['key' => 'payments', 'label' => 'Payments', 'icon' => 'banknote', 'href' => url('admin/payments.php')],
    ['key' => 'ai_providers', 'label' => 'AI Providers', 'icon' => 'cpu', 'href' => url('admin/ai-providers.php')],
    ['key' => 'ai_usage', 'label' => 'AI Usage', 'icon' => 'bar-chart-3', 'href' => url('admin/ai-usage.php')],
    ['key' => 'email_settings', 'label' => 'Email / SMTP', 'icon' => 'mail', 'href' => url('admin/email-settings.php')],
    ['key' => 'coupons', 'label' => 'Coupons', 'icon' => 'ticket', 'href' => url('admin/coupons.php')],
    ['key' => 'support', 'label' => 'Support Tickets', 'icon' => 'life-buoy', 'href' => url('admin/support.php')],
    ['key' => 'audit_logs', 'label' => 'Audit Logs', 'icon' => 'shield', 'href' => url('admin/audit-logs.php')],
    ['key' => 'system_logs', 'label' => 'System Logs', 'icon' => 'terminal', 'href' => url('admin/system-logs.php')],
    ['key' => 'settings', 'label' => 'Platform Settings', 'icon' => 'settings', 'href' => url('admin/settings.php')],
];
?>
<aside class="sidebar" id="app-sidebar">
    <a href="<?= url('admin/index.php') ?>" class="sidebar-brand"><i data-lucide="shield-check"></i> Admin Panel</a>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= View::e($item['href']) ?>" class="<?= $activePage === $item['key'] ? 'active' : '' ?>">
            <i data-lucide="<?= View::e($item['icon']) ?>" style="width:18px;height:18px;"></i>
            <span><?= View::e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
        <div class="nav-section">Back to App</div>
        <a href="<?= url('dashboard/index.php') ?>"><i data-lucide="arrow-left" style="width:18px;height:18px;"></i><span>Business Dashboard</span></a>
    </nav>
</aside>
