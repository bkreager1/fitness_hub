<?php
// ============================================================
// app/core/Controller.php
// Base class that every controller extends.
// Provides: view(), redirect(), requireLogin(), requireGuest(), stub().
// ============================================================

class Controller {

    // ----- Render a view wrapped in the site header and footer -------
    // $name is a path relative to /app/views without the .php.
    // Example:  $this->view('pages/home', ['title' => 'Home'])
    //           →  loads app/views/pages/home.php and makes $title available.
    protected function view(string $name, array $data = []): void {
        // Turn array keys into variables: ['title' => 'Home']  →  $title = 'Home'
        extract($data, EXTR_SKIP);

        $file = VIEW_PATH . '/' . $name . '.php';

        if (!is_file($file)) {
            http_response_code(500);
            die("View not found: {$name}");
        }

        // Header, page, footer — always in this order
        require VIEW_PATH . '/layouts/header.php';
        require $file;
        require VIEW_PATH . '/layouts/footer.php';

        // Forms have already been re-rendered with old() / field_error() —
        // clear both so a refresh doesn't re-display stale state.
        clear_old();
        clear_errors();
    }

    // ----- Render a view WITHOUT the header/footer wrapper -----------
    // Useful for error pages, standalone screens, or JSON endpoints.
    protected function viewRaw(string $name, array $data = []): void {
        extract($data, EXTR_SKIP);

        $file = VIEW_PATH . '/' . $name . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            die("View not found: {$name}");
        }
        require $file;
    }

    // ----- Redirect shorthand usable from inside controllers ---------
    protected function redirect(string $path): void {
        redirect($path);
    }

    // ----- Require that the user is logged in ------------------------
    // Call at the top of any action that should be behind auth.
    //
    // Also verifies the session points at a user that still exists in
    // the database. Without this check, a stale session (DB reset,
    // user deleted, etc.) crashes downstream queries with FK errors.
    protected function requireLogin(): void {
        if (!is_logged_in()) {
            flash('error', 'Please log in to continue.');
            $this->redirect('login');
        }
        if (User::find((int) current_user_id()) === null) {
            // Stale session — clear it and bounce to login.
            $_SESSION = [];
            session_regenerate_id(true);
            flash('error', 'Your session is no longer valid. Please log in again.');
            $this->redirect('login');
        }
    }

    // ----- Require that the user is NOT logged in --------------------
    // For pages like /login and /register — no reason to show them
    // to someone who's already signed in.
    protected function requireGuest(): void {
        if (is_logged_in()) {
            $this->redirect('dashboard');
        }
    }

    // ----- Placeholder page for features built in later phases -------
    // Renders through the normal layout so the nav (and logout button)
    // are available on not-yet-built screens too.
    protected function stub(string $what, int $phase): void {
        $this->view('stub', [
            'title' => $what,
            'what'  => $what,
            'phase' => $phase,
        ]);
    }
}
