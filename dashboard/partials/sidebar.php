<?php
/** @var string $activePage */
$activePage = $activePage ?? '';
$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => url('dashboard/index.php')],
    ['key' => 'leads', 'label' => 'Leads', 'icon' => 'target', 'href' => url('dashboard/leads.php')],
    ['key' => 'customers', 'label' => 'Customers', 'icon' => 'users', 'href' => url('dashboard/customers.php')],
    ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'check-square', 'href' => url('dashboard/tasks.php')],
    ['key' => 'ai_assistant', 'label' => 'AI Assistant', 'icon' => 'bot', 'href' => url('dashboard/ai-assistant.php')],
    ['key' => 'chatbot', 'label' => 'Chatbot', 'icon' => 'message-circle', 'href' => url('dashboard/chatbot.php')],
    ['key' => 'knowledge', 'label' => 'Knowledge Base', 'icon' => 'book-open', 'href' => url('dashboard/knowledge.php')],
    ['key' => 'proposals', 'label' => 'Proposals', 'icon' => 'file-text', 'href' => url('dashboard/proposals.php')],
    ['key' => 'quotations', 'label' => 'Quotations', 'icon' => 'file-spreadsheet', 'href' => url('dashboard/quotations.php')],
    ['key' => 'invoices', 'label' => 'Invoices', 'icon' => 'receipt', 'href' => url('dashboard/invoices.php')],
    ['key' => 'campaigns', 'label' => 'Campaigns', 'icon' => 'send', 'href' => url('dashboard/campaigns.php')],
    ['key' => 'automations', 'label' => 'Automations', 'icon' => 'workflow', 'href' => url('dashboard/automations.php')],
    ['key' => 'content', 'label' => 'AI Content Tools', 'icon' => 'pen-tool', 'href' => url('dashboard/content-tools.php')],
    ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'bar-chart-3', 'href' => url('dashboard/analytics.php')],
    ['key' => 'team', 'label' => 'Team', 'icon' => 'user-cog', 'href' => url('dashboard/team.php')],
    ['key' => 'billing', 'label' => 'Billing', 'icon' => 'credit-card', 'href' => url('dashboard/billing.php')],
    ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'href' => url('dashboard/settings.php')],
];
?>
<aside class="sidebar" id="app-sidebar">
    <a href="<?= url('dashboard/index.php') ?>" class="sidebar-brand"><i data-lucide="trending-up"></i> BharatSEO</a>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= View::e($item['href']) ?>" class="<?= $activePage === $item['key'] ? 'active' : '' ?>">
            <i data-lucide="<?= View::e($item['icon']) ?>" style="width:18px;height:18px;"></i>
            <span><?= View::e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
        <div class="nav-section">Support</div>
        <a href="<?= url('dashboard/support.php') ?>"><i data-lucide="life-buoy" style="width:18px;height:18px;"></i><span>Support Tickets</span></a>
        <a href="<?= url('dashboard/api-keys.php') ?>"><i data-lucide="key-round" style="width:18px;height:18px;"></i><span>API & Webhooks</span></a>

        <?php
        /*
         * The admin panel had no entry point in the UI at all: an administrator
         * had to know to type /admin/index.php. Shown only to platform admins,
         * and the role is read from the server-side $currentUser record rather
         * than from anything the client can influence - /admin still enforces
         * the check itself, so this link is convenience, never authorisation.
         */
        $isPlatformAdmin = in_array($currentUser['role'] ?? '', ['ADMIN', 'SUPER_ADMIN'], true);
        ?>
        <?php if ($isPlatformAdmin): ?>
        <div class="nav-section">Platform</div>
        <a href="<?= url('admin/index.php') ?>"><i data-lucide="shield-check" style="width:18px;height:18px;"></i><span>Admin Panel</span></a>
        <?php endif; ?>
    </nav>
</aside>
