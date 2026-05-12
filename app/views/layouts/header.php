<?php
// ============================================================
// app/views/layouts/header.php
// Shared site header — included on every page.
// Phase 5 version: real sticky nav with public/logged-in variants
// and a mobile hamburger toggle (wired in public/js/main.js).
//
// Optional view variables:
//   $title       — <title> tag prefix
//   $description — meta description + og:description override
//                  (defaults to the site-wide elevator pitch)
//   $ogImage     — relative path under /public to use as the social
//                  preview image; defaults to the landing hero photo
//   $active      — which nav link to highlight: 'home' | 'about' |
//                  'contact' | 'login' | 'register' | 'dashboard'
// ============================================================
$pageTitle = $title  ?? SITE_NAME;
$active    = $active ?? '';
$pageDesc  = $description
    ?? 'Track calories, weight, and lifts in one simple dashboard. '
     . 'A fitness app built for Rock County.';

// Build absolute URLs for Open Graph + Twitter Card tags. Social
// platforms fetch og:url and og:image as absolute URLs, so we piece
// together $_SERVER values rather than hard-coding a domain — the
// same code works in XAMPP and on Hostinger once deployed.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https' : 'http';
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$ogUrl  = $origin . ($_SERVER['REQUEST_URI'] ?? '/');

$ogImagePath = $ogImage ?? asset('images/Landinghero.jpg');
$ogImageAbs  = strpos($ogImagePath, '://') === false
    ? $origin . $ogImagePath
    : $ogImagePath;

// Tiny helper: returns the active class if $key matches.
$navClass = static fn(string $key): string =>
    'nav-link' . ($active === $key ? ' is-active' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(SITE_NAME) ?></title>
    <meta name="description" content="<?= e($pageDesc) ?>">

    <!-- Open Graph + Twitter Card — controls how links unfurl when
         shared in Slack, iMessage, Twitter/X, Facebook, etc. -->
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="<?= e(SITE_NAME) ?>">
    <meta property="og:title"       content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:url"         content="<?= e($ogUrl) ?>">
    <meta property="og:image"       content="<?= e($ogImageAbs) ?>">
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDesc) ?>">
    <meta name="twitter:image"       content="<?= e($ogImageAbs) ?>">

    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('images/favicon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>

<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header" id="siteHeader">
    <div class="container nav-row">

        <a class="brand" href="<?= url('') ?>">
            <span class="brand-mark" aria-hidden="true">
                <img src="<?= asset('images/logo3.png') ?>" alt="">
            </span>
            <span><?= e(SITE_NAME) ?></span>
        </a>

        <button type="button" class="nav-toggle" id="navToggle"
                aria-label="Toggle navigation" aria-expanded="false"
                aria-controls="siteNav">
            <svg class="icon-open"  viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" aria-hidden="true">
                <line x1="4"  y1="7"  x2="20" y2="7"/>
                <line x1="4"  y1="12" x2="20" y2="12"/>
                <line x1="4"  y1="17" x2="20" y2="17"/>
            </svg>
            <svg class="icon-close" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" aria-hidden="true">
                <line x1="6"  y1="6"  x2="18" y2="18"/>
                <line x1="18" y1="6"  x2="6"  y2="18"/>
            </svg>
        </button>

        <nav class="site-nav" id="siteNav" aria-label="Primary">
            <a class="<?= $navClass('home') ?>"    href="<?= url('') ?>">Home</a>
            <a class="<?= $navClass('about') ?>"   href="<?= url('about') ?>">About</a>
            <a class="<?= $navClass('contact') ?>" href="<?= url('contact') ?>">Contact</a>

            <?php if (is_logged_in()): ?>
                <a class="<?= $navClass('dashboard') ?>" href="<?= url('dashboard') ?>">Dashboard</a>
                <?php
                    $navAvatarFile = current_user('profile_image_path');
                    $navAvatarSrc  = $navAvatarFile ? asset('uploads/' . $navAvatarFile) : null;
                    $navInitial    = mb_strtoupper(mb_substr(trim((string) current_user('name')), 0, 1)) ?: '?';
                ?>
                <a class="nav-profile <?= $active === 'profile' ? 'is-active' : '' ?>"
                   href="<?= url('profile') ?>"
                   aria-label="My profile">
                    <?php if ($navAvatarSrc): ?>
                        <img class="avatar avatar-sm" src="<?= e($navAvatarSrc) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar avatar-sm avatar-initials" aria-hidden="true">
                            <?= e($navInitial) ?>
                        </span>
                    <?php endif; ?>
                    <span class="nav-profile__name">Hi, <?= e(current_user('name')) ?></span>
                </a>
                <form method="post" action="<?= url('logout') ?>" class="nav-logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="nav-link as-button">Log out</button>
                </form>
            <?php else: ?>
                <a class="<?= $navClass('login') ?>" href="<?= url('login') ?>">Log in</a>
                <a class="nav-link nav-cta"          href="<?= url('register') ?>">Sign up</a>
            <?php endif; ?>
        </nav>

    </div>
</header>

<?php
    // Tracker pages opt out of the global banner ($flashInline = true
    // in their view data) and render the success flash inline next to
    // the form, where the user's eyes already are after a save.
    if (empty($flashInline) && ($msg = flash('success'))):
?>
    <div class="flash flash-success" role="status"><?= e($msg) ?></div>
<?php endif; ?>

<?php
    // Controllers can call flash('skip_page_entry', '1') before a
    // redirect to suppress the page-entry fade for that one render —
    // used by the calorie goal pills so the toggle feels instant.
    $skipPageEntry = (bool) flash('skip_page_entry');
?>
<main class="site-main<?= $skipPageEntry ? ' no-page-entry' : '' ?>" id="main" tabindex="-1">
