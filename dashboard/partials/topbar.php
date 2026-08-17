<?php
/**
 * Dashboard topbar. Layout and colour come from app.css (.topbar and friends)
 * rather than inline styles, so a theme change needs no template edits.
 *
 * @var array  $user
 * @var string $pageTitle
 */
$initial = strtoupper(substr((string) ($user['name'] ?? 'U'), 0, 1));
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle menu" aria-expanded="false">
            <i data-lucide="menu"></i>
        </button>
        <h2><?= View::e($pageTitle ?? 'Dashboard') ?></h2>
    </div>

    <div class="topbar-right">
        <button class="theme-toggle" type="button" aria-label="Switch between light and dark theme">
            <i data-lucide="sun-moon"></i>
        </button>

        <div id="business-switcher" style="position:relative;"></div>

        <span class="topbar-avatar" title="<?= View::e($user['name'] ?? '') ?>"><?= View::e($initial) ?></span>

        <button id="logout-btn" class="btn btn-secondary btn-sm">
            <i data-lucide="log-out"></i> Log out
        </button>
    </div>
</header>

<script>
document.getElementById('logout-btn')?.addEventListener('click', async function () {
    await Api.call(appBase() + '/api/auth/logout.php', { method: 'POST' });
    window.location.href = appBase() + '/auth/login.php';
});
</script>
