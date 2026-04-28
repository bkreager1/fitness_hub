<?php
// ============================================================
// public/index.php — Front Controller
//
// Every request to the app passes through this file.
// 1. Bootstrap (session, config, helpers, autoloader)
// 2. Read the URL and HTTP method
// 3. Match them against the route table
// 4. Call the matching controller method
// ============================================================

// ----- 1. Bootstrap --------------------------------------------------
// Start the session BEFORE any output — it sets a cookie header.
session_start();

// Load config (defines constants + the db() function)
require __DIR__ . '/../config/database.php';

// Load helper functions (url, e, flash, csrf_token, etc.)
require __DIR__ . '/../app/core/helpers.php';

// Load the base Controller class (all controllers extend it)
require __DIR__ . '/../app/core/Controller.php';

// Simple autoloader: when a class is referenced, find and require the file.
// Looks in /app/controllers then /app/models.
spl_autoload_register(function (string $class): void {
    $candidates = [
        APP_ROOT . '/app/controllers/' . $class . '.php',
        APP_ROOT . '/app/models/'      . $class . '.php',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

// ----- 2. Parse the request ------------------------------------------
// .htaccess rewrites requests like "/dashboard" to "index.php?url=dashboard"
$path   = trim($_GET['url'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];
$route  = $method . ' ' . $path;   // e.g. "GET dashboard"

// ----- 3. Dispatch ---------------------------------------------------
try {
    match ($route) {

        // ----- Public pages -----
        'GET '         => (new PagesController())->home(),
        'GET about'    => (new PagesController())->about(),
        'GET contact'  => (new PagesController())->contact(),
        'POST contact' => (new ContactController())->submit(),

        // ----- Auth -----
        'GET register'  => (new AuthController())->showRegister(),
        'POST register' => (new AuthController())->register(),
        'GET login'     => (new AuthController())->showLogin(),
        'POST login'    => (new AuthController())->login(),
        'POST logout'   => (new AuthController())->logout(),

        'GET forgot-password'  => (new AuthController())->showForgotPassword(),
        'POST forgot-password' => (new AuthController())->sendResetLink(),
        'GET reset-password'   => (new AuthController())->showResetPassword(),
        'POST reset-password'  => (new AuthController())->resetPassword(),

        // ----- Dashboard -----
        'GET dashboard' => (new DashboardController())->index(),

        // ----- Calorie tracker -----
        'GET calorie'                => (new CalorieController())->index(),
        'POST calorie/targets'       => (new CalorieController())->saveTargets(),
        'POST calorie/intake'        => (new CalorieController())->saveIntake(),
        'POST calorie/intake/delete' => (new CalorieController())->deleteIntake(),
        'POST calorie/goal'          => (new CalorieController())->setGoal(),

        // ----- Weight tracker -----
        'GET weight'         => (new WeightController())->index(),
        'POST weight'        => (new WeightController())->save(),
        'GET weight/edit'    => (new WeightController())->edit(),
        'POST weight/update' => (new WeightController())->update(),
        'POST weight/delete' => (new WeightController())->delete(),

        // ----- Strength tracker -----
        'GET strength'         => (new StrengthController())->index(),
        'POST strength'        => (new StrengthController())->save(),
        'POST strength/update' => (new StrengthController())->update(),
        'POST strength/delete' => (new StrengthController())->delete(),

        // ----- Profile -----
        'GET profile'        => (new ProfileController())->index(),
        'POST profile'       => (new ProfileController())->update(),
        'POST profile/image' => (new ProfileController())->uploadImage(),

        // ----- Anything else → 404 -----
        default => (new PagesController())->notFound(),
    };
} catch (Throwable $e) {
    // Development-friendly error page.
    // In production (Hostinger) we'd log this instead of printing.
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error</title>';
    echo '<style>body{font-family:system-ui;background:#1a1a2e;color:#e8e8f0;padding:2rem;}';
    echo 'pre{background:#22223b;padding:1rem;border-radius:8px;overflow:auto;font-size:.85rem;}';
    echo 'h1{color:#f87171}</style></head><body>';
    echo '<h1>Something went wrong</h1>';
    echo '<p>' . e($e->getMessage()) . '</p>';
    echo '<pre>' . e($e->getTraceAsString()) . '</pre>';
    echo '</body></html>';
}
