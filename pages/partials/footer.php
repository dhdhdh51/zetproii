<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <div class="brand">
                <span class="brand-mark"><i data-lucide="trending-up"></i></span>
                <span>Bharat<strong>SEO</strong></span>
            </div>
            <p>The AI SEO and growth workspace for Indian businesses — rankings, content, leads and follow-ups in one place.</p>
        </div>
        <div>
            <h4>Product</h4>
            <a href="<?= url('features') ?>">Features</a>
            <a href="<?= url('pricing') ?>">Pricing</a>
            <a href="<?= url('auth/register.php') ?>">Start free</a>
        </div>
        <div>
            <h4>Company</h4>
            <a href="<?= url('about') ?>">About us</a>
            <a href="<?= url('contact') ?>">Contact</a>
            <a href="<?= url('blog') ?>">Blog</a>
        </div>
        <div>
            <h4>Legal</h4>
            <a href="<?= url('privacy') ?>">Privacy policy</a>
            <a href="<?= url('terms') ?>">Terms of service</a>
            <a href="<?= url('refund-policy') ?>">Refund policy</a>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> BharatSEO. All rights reserved.</p>
        <p>Made in India 🇮🇳</p>
    </div>
</footer>

<?php
/*
 * Icon building lives in marketing.js, which is deferred - so it runs only
 * after the deferred icon library has executed.
 *
 * This was previously an inline script here that tested for the library before
 * registering its callback. Inline scripts execute during parsing, long before
 * a deferred script in <head> has run, so that test always failed and the icons
 * were never built: every icon on the marketing site silently disappeared.
 */
?>
<script src="<?= asset('js/marketing.js') ?>" defer></script>
