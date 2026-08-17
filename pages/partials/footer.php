<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <div class="brand"><i data-lucide="sparkles"></i><span>BharatAI <strong>Business OS</strong></span></div>
            <p>AI-powered business automation for small businesses, agencies and freelancers.</p>
        </div>
        <div>
            <h4>Product</h4>
            <a href="<?= url('features') ?>">Features</a>
            <a href="<?= url('pricing') ?>">Pricing</a>
            <a href="<?= url('about') ?>">About</a>
        </div>
        <div>
            <h4>Company</h4>
            <a href="<?= url('about') ?>">About Us</a>
            <a href="<?= url('contact') ?>">Contact</a>
            <a href="<?= url('blog') ?>">Blog</a>
        </div>
        <div>
            <h4>Legal</h4>
            <a href="<?= url('privacy') ?>">Privacy Policy</a>
            <a href="<?= url('terms') ?>">Terms of Service</a>
            <a href="<?= url('refund-policy') ?>">Refund Policy</a>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> BharatAI Business OS. All rights reserved.</p>
    </div>
</footer>
<script src="<?= asset('js/marketing.js') ?>" defer></script>
<script>if (window.lucide) { document.addEventListener('DOMContentLoaded', () => lucide.createIcons()); }</script>
