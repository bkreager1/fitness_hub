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
            'description' => 'Track your calories, weight, and lifts in one '
                          . 'simple dashboard. No gimmicks, no confusing '
                          . 'spreadsheets — just the numbers that help you '
                          . 'stay consistent and see real progress.',
            'active'      => 'home',
        ]);
    }

    // GET /about
    public function about(): void {
        $this->view('pages/about', [
            'title'       => 'About',
            'description' => 'A beginner-friendly fitness tracker built '
                          . 'for Rock County — three simple trackers in '
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

    // Any unknown URL
    public function notFound(): void {
        http_response_code(404);
        $this->view('errors/404', [
            'title'       => 'Page not found',
            'description' => 'That page doesn\'t exist. Head back to the '
                          . 'home page to find what you were looking for.',
        ]);
    }
}
