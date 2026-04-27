<?php
// ============================================================
// app/controllers/ContactController.php
// Handles the public Contact form.
//
// Routes:
//   POST /contact  → submit()
// (GET /contact is rendered by PagesController::contact().)
// ============================================================

class ContactController extends Controller {

    // POST /contact
    public function submit(): void {
        csrf_verify();

        // ----- Read + trim input ----------------------------------
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $message = trim($_POST['message'] ?? '');

        // ----- Server-side validation -----------------------------
        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Name must be 100 characters or fewer.';
        }

        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 150) {
            $errors[] = 'Email must be 150 characters or fewer.';
        }

        if ($message === '') {
            $errors[] = 'Message is required.';
        } elseif (mb_strlen($message) < 10) {
            $errors[] = 'Message must be at least 10 characters.';
        } elseif (mb_strlen($message) > 2000) {
            $errors[] = 'Message must be 2000 characters or fewer.';
        }

        // ----- Failure path: re-show the form with what they typed
        if ($errors) {
            save_old(['name' => $name, 'email' => $email, 'message' => $message]);
            flash('errors', implode("\n", $errors));
            $this->redirect('contact');
        }

        // ----- Success path ---------------------------------------
        Contact::create($name, $email, $message);

        flash('success', 'Thanks! Your message has been sent.');
        $this->redirect('contact');
    }
}
