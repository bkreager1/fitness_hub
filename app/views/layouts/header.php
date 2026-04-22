<?php
// ============================================================
// app/views/layouts/header.php
// Shared site header / nav — included on every page.
// Phase 4 version: minimal shell so auth pages render cleanly.
// Phase 5 will replace this with the real sticky nav + design system.
// ============================================================
$pageTitle = $title ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="<?= url('') ?>"><?= e(SITE_NAME) ?></a>
        <nav class="site-nav">
            <?php if (is_logged_in()): ?>
                <span class="nav-hello">Hi, <?= e(current_user('name')) ?></span>
                <form method="post" action="<?= url('logout') ?>" class="nav-logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="nav-link as-button">Log out</button>
                </form>
            <?php else: ?>
                <a class="nav-link" href="<?= url('login') ?>">Log in</a>
                <a class="nav-link nav-cta" href="<?= url('register') ?>">Sign up</a>
            <?php endif; ?>
        </nav>
    </header>

    <?php if ($msg = flash('success')): ?>
        <div class="flash flash-success"><?= e($msg) ?></div>
    <?php endif; ?>

    <main class="site-main">
