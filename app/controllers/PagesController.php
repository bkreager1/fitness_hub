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
        $this->stub('About', 6);
    }

    // GET /contact
    public function contact(): void {
        $this->stub('Contact', 6);
    }

    // Any unknown URL
    public function notFound(): void {
        http_response_code(404);
        $this->stub('Page Not Found (404)', 0);
    }
}
