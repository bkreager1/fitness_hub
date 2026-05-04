<?php // app/views/errors/404.php — Phase 9 ?>
<section class="error-shell">
    <article class="error-card">
        <span class="eyebrow">Page not found</span>
        <p class="error-code" aria-hidden="true">404</p>
        <h1>This page took a rest day.</h1>
        <p class="error-lede">
            The URL you followed doesn't lead anywhere here. It may have
            moved, or there's a typo somewhere. Either way, let's get you
            back on track.
        </p>
        <div class="error-actions">
            <a class="btn" href="<?= url('') ?>">Back to home</a>
            <?php if (is_logged_in()): ?>
                <a class="btn btn-secondary btn-inline" href="<?= url('dashboard') ?>">Open dashboard</a>
            <?php else: ?>
                <a class="btn btn-secondary btn-inline" href="<?= url('about') ?>">About this app</a>
            <?php endif; ?>
        </div>
    </article>
</section>
