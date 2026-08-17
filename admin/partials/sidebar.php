<?php
$activePage = $activePage ?? '';
$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => '/admin/index.php'],
    ['key' => 'users', 'label' => 'Users', 'icon' => 'users', 'href' => '/admin/users.php'],
    ['key' => 'businesses', 'label' => 'Businesses', 'icon' => 'building-2', 'href' => '/admin/businesses.php'],
    ['key' => 'plans', 'label' => 'Plans', 'icon' => 'package', 'href' => '/admin/plans.php'],
    ['key' => 'subscriptions', 'label' => 'Subscriptions', 'icon' => 'credit-card', 'href' => '/admin/subscriptions.php'],
    ['key' => 'payments', 'label' => 'Payments', 'icon' => 'banknote', 'href' => '/admin/payments.php'],
    ['key' => 'ai_providers', 'label' => 'AI Providers', 'icon' => 'cpu', 'href' => '/admin/ai-providers.php'],
    ['key' => 'ai_usage', 'label' => 'AI Usage', 'icon' => 'bar-chart-3', 'href' => '/admin/ai-usage.php'],
    ['key' => 'email_settings', 'label' => 'Email / SMTP', 'icon' => 'mail', 'href' => '/admin/email-settings.php'],
    ['key' => 'coupons', 'label' => 'Coupons', 'icon' => 'ticket', 'href' => '/admin/coupons.php'],
    ['key' => 'support', 'label' => 'Support Tickets', 'icon' => 'life-buoy', 'href' => '/admin/support.php'],
    ['key' => 'audit_logs', 'label' => 'Audit Logs', 'icon' => 'shield', 'href' => '/admin/audit-logs.php'],
    ['key' => 'system_logs', 'label' => 'System Logs', 'icon' => 'terminal', 'href' => '/admin/system-logs.php'],
    ['key' => 'settings', 'label' => 'Platform Settings', 'icon' => 'settings', 'href' => '/admin/settings.php'],
];
?>
<aside class="sidebar" id="app-sidebar">
    <a href="/admin/index.php" class="sidebar-brand"><i data-lucide="shield-check"></i> Admin Panel</a>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= View::e($item['href']) ?>" class="<?= $activePage === $item['key'] ? 'active' : '' ?>">
            <i data-lucide="<?= View::e($item['icon']) ?>" style="width:18px;height:18px;"></i>
            <span><?= View::e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
        <div class="nav-section">Back to App</div>
        <a href="/dashboard/index.php"><i data-lucide="arrow-left" style="width:18px;height:18px;"></i><span>Business Dashboard</span></a>
    </nav>
</aside>
