<?php
// ============================================================
// app/core/helpers.php
// Small utility functions used throughout the app.
// Loaded once by public/index.php during bootstrap.
// ============================================================

// ----- URL helpers ---------------------------------------------------

// Build a URL that respects BASE_URL.
// Usage: url('login')  →  '/fitness_hub/login'
function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

// Build an asset URL (CSS, JS, uploaded images).
// Usage: asset('css/style.css')  →  '/fitness_hub/css/style.css'
function asset(string $path): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

// Redirect to another URL and stop the script.
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

// ----- Output escaping -----------------------------------------------

// Escape text for safe HTML output (prevents XSS).
// ALWAYS use this when printing user-supplied data inside HTML.
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ----- Flash messages ------------------------------------------------
// "Flash" = a message that survives exactly one redirect, then disappears.
// Great for "Account created!" banners after a successful form submit.

// Set a flash:  flash('success', 'Saved!');
// Get a flash:  $msg = flash('success');   // also removes it
function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

// ----- Old form input ------------------------------------------------
// When a form submission fails validation, we redirect back with the
// original values so the user doesn't have to retype everything.

// Remember submitted form values for the next request.
function save_old(array $data): void {
    $_SESSION['_old'] = $data;
}

// Retrieve a previously-saved form value.
function old(string $key, string $default = ''): string {
    return $_SESSION['_old'][$key] ?? $default;
}

// Clear remembered form values (call after a successful render).
function clear_old(): void {
    unset($_SESSION['_old']);
}

// ----- CSRF protection -----------------------------------------------
// CSRF = Cross-Site Request Forgery. We embed a secret token in every
// form and check it on submit. Prevents other sites from tricking the
// browser into submitting forms to our app.

// Get (or generate) the current session's CSRF token.
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

// Render a hidden input for any form. Drop this inside every <form>.
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

// Check the submitted token on a POST. Aborts the request if missing/wrong.
function csrf_verify(): void {
    $submitted = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $submitted)) {
        http_response_code(419);
        die('CSRF token mismatch. Please refresh and try again.');
    }
}

// ----- Authentication helpers ----------------------------------------

// Is a user currently logged in?
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

// ID of the current logged-in user (or null).
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

// Shortcut: grab a field from the logged-in user's session data.
function current_user(string $field): ?string {
    return $_SESSION['user'][$field] ?? null;
}
