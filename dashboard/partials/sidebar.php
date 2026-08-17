<?php
/** @var string $activePage */
$activePage = $activePage ?? '';
$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'href' => '/dashboard/index.php'],
    ['key' => 'leads', 'label' => 'Leads', 'icon' => 'target', 'href' => '/dashboard/leads.php'],
    ['key' => 'customers', 'label' => 'Customers', 'icon' => 'users', 'href' => '/dashboard/customers.php'],
    ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'check-square', 'href' => '/dashboard/tasks.php'],
    ['key' => 'ai_assistant', 'label' => 'AI Assistant', 'icon' => 'bot', 'href' => '/dashboard/ai-assistant.php'],
    ['key' => 'chatbot', 'label' => 'Chatbot', 'icon' => 'message-circle', 'href' => '/dashboard/chatbot.php'],
    ['key' => 'knowledge', 'label' => 'Knowledge Base', 'icon' => 'book-open', 'href' => '/dashboard/knowledge.php'],
    ['key' => 'proposals', 'label' => 'Proposals', 'icon' => 'file-text', 'href' => '/dashboard/proposals.php'],
    ['key' => 'quotations', 'label' => 'Quotations', 'icon' => 'file-spreadsheet', 'href' => '/dashboard/quotations.php'],
    ['key' => 'invoices', 'label' => 'Invoices', 'icon' => 'receipt', 'href' => '/dashboard/invoices.php'],
    ['key' => 'campaigns', 'label' => 'Campaigns', 'icon' => 'send', 'href' => '/dashboard/campaigns.php'],
    ['key' => 'automations', 'label' => 'Automations', 'icon' => 'workflow', 'href' => '/dashboard/automations.php'],
    ['key' => 'content', 'label' => 'AI Content Tools', 'icon' => 'pen-tool', 'href' => '/dashboard/content-tools.php'],
    ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'bar-chart-3', 'href' => '/dashboard/analytics.php'],
    ['key' => 'team', 'label' => 'Team', 'icon' => 'user-cog', 'href' => '/dashboard/team.php'],
    ['key' => 'billing', 'label' => 'Billing', 'icon' => 'credit-card', 'href' => '/dashboard/billing.php'],
    ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'href' => '/dashboard/settings.php'],
];
?>
<aside class="sidebar" id="app-sidebar">
    <a href="/dashboard/index.php" class="sidebar-brand"><i data-lucide="sparkles"></i> BharatAI OS</a>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
        <a href="<?= View::e($item['href']) ?>" class="<?= $activePage === $item['key'] ? 'active' : '' ?>">
            <i data-lucide="<?= View::e($item['icon']) ?>" style="width:18px;height:18px;"></i>
            <span><?= View::e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
        <div class="nav-section">Support</div>
        <a href="/dashboard/support.php"><i data-lucide="life-buoy" style="width:18px;height:18px;"></i><span>Support Tickets</span></a>
        <a href="/dashboard/api-keys.php"><i data-lucide="key-round" style="width:18px;height:18px;"></i><span>API & Webhooks</span></a>
    </nav>
</aside>
