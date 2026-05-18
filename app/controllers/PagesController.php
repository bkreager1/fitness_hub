<?php
// ============================================================
// app/controllers/PagesController.php
// Handles the static marketing pages (home, about, contact)
// and the 404 fallback. Real content lands in Phase 5 & 6.
// ============================================================

class PagesController extends Controller {

    // GET /
    public function home(): void {
        $this->view('pages/home', [
            'title'       => 'Home',
            'description' => 'Track your calories, weight, lifts, and '
                          . 'cardio in one simple dashboard. No gimmicks, '
                          . 'no confusing spreadsheets — just the numbers '
                          . 'that help you stay consistent and see real '
                          . 'progress.',
            'active'      => 'home',
        ]);
    }

    // GET /about
    public function about(): void {
        $this->view('pages/about', [
            'title'       => 'About',
            'description' => 'A beginner-friendly fitness tracker built '
                          . 'for Rock County — four simple trackers in '
                          . 'one dashboard, no jargon, no upsells.',
            'active'      => 'about',
        ]);
    }

    // GET /contact
    public function contact(): void {
        $this->view('pages/contact', [
            'title'       => 'Contact',
            'description' => 'Questions, feedback, or a feature request? '
                          . 'Drop us a line — we read everything that '
                          . 'comes in.',
            'active'      => 'contact',
        ]);
    }

    // GET /privacy
    public function privacy(): void {
        $this->view('pages/privacy', [
            'title'       => 'Privacy',
            'description' => 'What we store, why we store it, and the '
                          . 'controls you have over your data on Rock '
                          . 'County Fitness Hub.',
            'active'      => '',
        ]);
    }

    // GET /terms
    public function terms(): void {
        $this->view('pages/terms', [
            'title'       => 'Terms of use',
            'description' => 'The ground rules for using Rock County '
                          . 'Fitness Hub. Plain language, short read.',
            'active'      => '',
        ]);
    }

    // GET /sitemap.xml — dynamic so the host portion always matches
    // the deploy environment (XAMPP, Hostinger preview, prod domain).
    public function sitemap(): void {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $origin = $scheme . '://' . $host;

        $paths = ['', 'about', 'contact', 'privacy', 'terms', 'login', 'register', 'forgot-password'];

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $today = date('Y-m-d');
        foreach ($paths as $p) {
            $loc = $origin . url($p);
            // Higher priority for the landing + the four marketing pages.
            $priority = $p === '' ? '1.0' : ($p === 'about' || $p === 'contact' ? '0.8' : '0.5');
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . "</loc>\n";
            echo "    <lastmod>{$today}</lastmod>\n";
            echo "    <priority>{$priority}</priority>\n";
            echo "  </url>\n";
        }
        echo '</urlset>' . "\n";
        exit;
    }

    // Any unknown URL
    public function notFound(): void {
        http_response_code(404);
        $this->view('errors/404', [
            'title'       => 'Page not found',
            'description' => 'That page doesn\'t exist. Head back to the '
                          . 'home page to find what you were looking for.',
        ]);
    }

    // Uncaught exception in production — rendered from index.php's
    // top-level try/catch. The error itself has already been logged.
    public function serverError(): void {
        http_response_code(500);
        $this->view('errors/500', [
            'title'       => 'Something went wrong',
            'description' => 'A temporary error stopped the page from loading. '
                          . 'Please try again in a moment.',
        ]);
    }
}
