<?php // app/views/stub.php — placeholder for routes not yet built ?>
<div class="stub-wrap">
    <div class="stub-card">
        <span class="stub-badge">Phase <?= (int) $phase ?></span>
        <h1><?= e($what) ?></h1>
        <p>This screen will be built during Phase <?= (int) $phase ?> of the project. The router is wired up and this URL is reachable.</p>
        <a href="<?= url('') ?>">&larr; Back to Home</a>
    </div>
</div>
