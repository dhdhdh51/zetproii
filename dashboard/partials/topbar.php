<?php
/** @var array $user */
/** @var string $pageTitle */
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle menu"><i data-lucide="menu"></i></button>
        <h2 style="font-size:17px;margin:0;"><?= View::e($pageTitle ?? 'Dashboard') ?></h2>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="theme-toggle" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
        <div id="business-switcher" style="position:relative;"></div>
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:34px;height:34px;border-radius:50%;background:var(--color-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">
                <?= View::e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?>
            </div>
            <button id="logout-btn" class="btn btn-secondary" style="width:auto;padding:8px 14px;font-size:13px;">Log Out</button>
        </div>
    </div>
</header>
<script>
document.getElementById('logout-btn')?.addEventListener('click', async function () {
    await Api.call('/api/auth/logout.php', { method: 'POST' });
    window.location.href = '/auth/login.php';
});
</script>
