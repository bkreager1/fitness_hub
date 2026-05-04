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
            'title'  => 'Home',
            'active' => 'home',
        ]);
    }

    // GET /about
    public function about(): void {
        $this->view('pages/about', [
            'title'  => 'About',
            'active' => 'about',
        ]);
    }

    // GET /contact
    public function contact(): void {
        $this->view('pages/contact', [
            'title'  => 'Contact',
            'active' => 'contact',
        ]);
    }

    // Any unknown URL
    public function notFound(): void {
        http_response_code(404);
        $this->view('errors/404', [
            'title' => 'Page not found',
        ]);
    }
}
