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
// Load config FIRST so APP_ENV / APP_ROOT are defined before we touch
// session cookies or error handling below. The order change (vs. the
// earlier session_start-first version) is safe — config doesn't open
// the DB connection at load time, only when db() is first called.
require __DIR__ . '/../config/database.php';

// Load helper functions (url, e, flash, csrf_token, etc.)
require __DIR__ . '/../app/core/helpers.php';

// Load the base Controller class (all controllers extend it)
require __DIR__ . '/../app/core/Controller.php';

// ----- Error display posture (env-aware) -----------------------------
// Prod hides every detail from end users and writes them to a log file
// instead. Dev keeps full backtraces visible so we can debug locally.
if (APP_ENV === 'prod') {
    ini_set('display_errors',         '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors',             '1');
    if (!is_dir(APP_ROOT . '/storage')) @mkdir(APP_ROOT . '/storage', 0775, true);
    ini_set('error_log', APP_ROOT . '/storage/php_errors.log');
} else {
    ini_set('display_errors',         '1');
    ini_set('display_startup_errors', '1');
}
error_reporting(E_ALL);

// ----- Session cookie hardening --------------------------------------
// Must run BEFORE session_start() — once the cookie is set, these
// params no longer apply. HttpOnly blocks JS access (XSS mitigation),
// SameSite=Lax blocks cross-site CSRF on top of our token check, and
// Secure forces HTTPS-only transport whenever we're on HTTPS.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => APP_ENV === 'prod' ? true : $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Start the session AFTER cookie params + error posture are set.
session_start();

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
        'GET calorie/intake/edit'    => (new CalorieController())->editIntake(),
        'POST calorie/intake/update' => (new CalorieController())->updateIntake(),
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
        'GET strength/edit'    => (new StrengthController())->edit(),
        'POST strength/update' => (new StrengthController())->update(),
        'POST strength/delete' => (new StrengthController())->delete(),

        // ----- Cardio tracker -----
        'GET cardio'         => (new CardioController())->index(),
        'POST cardio'        => (new CardioController())->save(),
        'GET cardio/edit'    => (new CardioController())->edit(),
        'POST cardio/update' => (new CardioController())->update(),
        'POST cardio/delete' => (new CardioController())->delete(),

        // ----- Profile -----
        'GET profile'               => (new ProfileController())->index(),
        'POST profile'              => (new ProfileController())->update(),
        'POST profile/password'     => (new ProfileController())->password(),
        'POST profile/goals'        => (new ProfileController())->goals(),
        'POST profile/image'        => (new ProfileController())->uploadImage(),
        'POST profile/image/delete' => (new ProfileController())->deleteImage(),
        'POST profile/delete'       => (new ProfileController())->delete(),

        // ----- Data export (one CSV per tracker) -----
        'GET calorie/export'  => (new CalorieController())->exportCsv(),
        'GET weight/export'   => (new WeightController())->exportCsv(),
        'GET strength/export' => (new StrengthController())->exportCsv(),
        'GET cardio/export'   => (new CardioController())->exportCsv(),

        // ----- Anything else → 404 -----
        default => (new PagesController())->notFound(),
    };
} catch (Throwable $e) {
    http_response_code(500);

    // Always write the full trace to the log so we can debug after
    // the fact — both in dev and in prod.
    error_log(
        '[' . date('Y-m-d H:i:s') . '] '
        . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
        . PHP_EOL . $e->getTraceAsString()
    );

    if (APP_ENV === 'prod') {
        // Render the styled 500 view through the normal layout so the
        // page still looks like the rest of the site. Falls back to a
        // bare-bones page if even the layout is broken.
        try {
            (new PagesController())->serverError();
        } catch (Throwable $_) {
            echo '<!DOCTYPE html><html><head><title>Something went wrong</title></head>'
               . '<body style="font-family:system-ui;background:#0e1424;color:#e8e8f0;padding:2rem">'
               . '<h1>Something went wrong on our end.</h1>'
               . '<p>Please refresh the page or try again in a moment.</p>'
               . '</body></html>';
        }
    } else {
        echo '<!DOCTYPE html><html><head><title>Error</title>';
        echo '<style>body{font-family:system-ui;background:#1a1a2e;color:#e8e8f0;padding:2rem;}';
        echo 'pre{background:#22223b;padding:1rem;border-radius:8px;overflow:auto;font-size:.85rem;}';
        echo 'h1{color:#f87171}</style></head><body>';
        echo '<h1>Something went wrong</h1>';
        echo '<p>' . e($e->getMessage()) . '</p>';
        echo '<pre>' . e($e->getTraceAsString()) . '</pre>';
        echo '</body></html>';
    }
}
