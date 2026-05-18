<?php
// ============================================================
// app/views/layouts/footer.php
// Shared site footer — included on every page.
// Phase 5 version: quick links + copyright + global JS.
// ============================================================
?>
    </main>

    <footer class="site-footer">
        <div class="container footer-row">
            <small class="footer-copy">
                &copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.
            </small>
            <ul class="footer-links">
                <li><a href="<?= url('') ?>">Home</a></li>
                <li><a href="<?= url('about') ?>">About</a></li>
                <li><a href="<?= url('contact') ?>">Contact</a></li>
                <?php if (is_logged_in()): ?>
                    <li><a href="<?= url('dashboard') ?>">Dashboard</a></li>
                <?php else: ?>
                    <li><a href="<?= url('login') ?>">Log in</a></li>
                    <li><a href="<?= url('register') ?>">Sign up</a></li>
                <?php endif; ?>
                <li><a href="<?= url('privacy') ?>">Privacy</a></li>
                <li><a href="<?= url('terms') ?>">Terms</a></li>
            </ul>
        </div>
    </footer>

    <!-- Back-to-top button. Hidden by default via CSS (opacity 0 +
         visibility hidden + pointer-events none); main.js adds
         .is-visible once the user scrolls past a threshold. -->
    <button type="button" id="backToTop" class="back-to-top"
            aria-label="Back to top">
        <svg viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.4"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="6 14 12 8 18 14"/>
        </svg>
    </button>

    <script src="<?= asset('js/main.js') ?>" defer></script>
    <script src="<?= asset('js/auth.js') ?>" defer></script>
</body>
</html>
