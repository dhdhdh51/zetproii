<?php
/**
 * Marketing site header. The brand mark is inline markup rather than an image
 * so it inherits the theme colours and needs no extra request.
 */
?>
<header class="site-header" id="site-header">
    <div class="container header-inner">
        <a href="<?= url() ?>" class="brand" aria-label="BharatSEO home">
            <span class="brand-mark"><i data-lucide="trending-up"></i></span>
            <span>Bharat<strong>SEO</strong></span>
        </a>

        <nav class="main-nav" id="main-nav" aria-label="Main">
            <a href="<?= url('features') ?>">Features</a>
            <a href="<?= url('pricing') ?>">Pricing</a>
            <a href="<?= url('about') ?>">About</a>
            <a href="<?= url('blog') ?>">Blog</a>
            <a href="<?= url('contact') ?>">Contact</a>
        </nav>

        <div class="header-actions">
            <button class="theme-toggle" id="theme-toggle" type="button" aria-label="Switch between light and dark theme">
                <i data-lucide="sun-moon"></i>
            </button>
            <a href="<?= url('auth/login.php') ?>" class="btn btn-ghost">Log in</a>
            <a href="<?= url('auth/register.php') ?>" class="btn btn-primary">Start free</a>
        </div>

        <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-nav">
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
        <a href="<?= url('auth/register.php') ?>" class="btn btn-primary">Start free</a>
    </div>
</header>
