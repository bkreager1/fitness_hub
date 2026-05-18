<?php // app/views/errors/500.php — generic prod error page ?>
<section class="error-shell">
    <article class="error-card">
        <span class="eyebrow">Server hiccup</span>
        <p class="error-code" aria-hidden="true">500</p>
        <h1>Something went wrong on our end.</h1>
        <p class="error-lede">
            A temporary error stopped this page from loading. We've logged
            the details and will look into it. Try refreshing, or head back
            to a page that's known to work.
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
