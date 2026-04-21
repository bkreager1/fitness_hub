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

        // Forms have already been re-rendered with old() — clear them
        clear_old();
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
    protected function requireLogin(): void {
        if (!is_logged_in()) {
            flash('error', 'Please log in to continue.');
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
    // Lets us render every route end-to-end before the feature exists.
    // NOTE: we don't set a 200 status here — the caller may have set
    // a different status (e.g. 404) that we should preserve.
    protected function stub(string $what, int $phase): void {
        $appName = SITE_NAME;
        $home    = url('');
        $safeWhat = e($what);
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$safeWhat} · {$appName}</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: 'Inter', system-ui, sans-serif;
                    background: #1a1a2e;
                    color: #e8e8f0;
                    min-height: 100vh;
                    display: grid;
                    place-items: center;
                    padding: 2rem;
                }
                .card {
                    background: #22223b;
                    padding: 2.5rem;
                    border-radius: 16px;
                    max-width: 480px;
                    width: 100%;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    border: 1px solid rgba(255,255,255,0.05);
                }
                .badge {
                    display: inline-block;
                    padding: .35rem .8rem;
                    border-radius: 999px;
                    background: linear-gradient(135deg, #818cf8, #a78bfa);
                    color: #fff;
                    font-size: .75rem;
                    font-weight: 600;
                    letter-spacing: .05em;
                    text-transform: uppercase;
                    margin-bottom: 1rem;
                }
                h1 { font-size: 1.75rem; margin-bottom: .5rem; font-weight: 700; }
                p  { color: #b8b8cc; margin-bottom: 1.5rem; line-height: 1.5; }
                a  {
                    color: #a78bfa;
                    text-decoration: none;
                    font-weight: 500;
                }
                a:hover { color: #c4b5fd; }
            </style>
        </head>
        <body>
            <div class="card">
                <span class="badge">Phase {$phase}</span>
                <h1>{$safeWhat}</h1>
                <p>This screen will be built during Phase {$phase} of the project. The router is wired up and this URL is reachable.</p>
                <a href="{$home}">← Back to Home</a>
            </div>
        </body>
        </html>
        HTML;
    }
}
