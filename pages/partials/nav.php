<header class="site-header">
    <div class="container header-inner">
        <a href="<?= url() ?>" class="brand">
            <i data-lucide="sparkles"></i>
            <span>BharatAI <strong>Business OS</strong></span>
        </a>
        <nav class="main-nav" id="main-nav">
            <a href="<?= url('features') ?>">Features</a>
            <a href="<?= url('pricing') ?>">Pricing</a>
            <a href="<?= url('about') ?>">About</a>
            <a href="<?= url('blog') ?>">Blog</a>
            <a href="<?= url('contact') ?>">Contact</a>
        </nav>
        <div class="header-actions">
            <a href="<?= url('auth/login.php') ?>" class="btn btn-ghost">Log in</a>
            <a href="<?= url('auth/register.php') ?>" class="btn btn-primary">Start Free</a>
        </div>
        <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle menu" aria-expanded="false">
            <i data-lucide="menu"></i>
        </button>
    </div>
    <div class="mobile-nav" id="mobile-nav">
        <a href="<?= url('features') ?>">Features</a>
        <a href="<?= url('pricing') ?>">Pricing</a>
        <a href="<?= url('about') ?>">About</a>
        <a href="<?= url('blog') ?>">Blog</a>
        <a href="<?= url('contact') ?>">Contact</a>
        <hr>
        <a href="<?= url('auth/login.php') ?>">Log in</a>
        <a href="<?= url('auth/register.php') ?>" class="btn btn-primary">Start Free</a>
    </div>
</header>
