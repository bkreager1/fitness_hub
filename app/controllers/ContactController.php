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

        // ----- Field-level validation -----------------------------
        // Keyed by field name so the view can render each error
        // right under the input it belongs to.
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Name must be 100 characters or fewer.';
        }

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 150) {
            $errors['email'] = 'Email must be 150 characters or fewer.';
        }

        if ($message === '') {
            $errors['message'] = 'Message is required.';
        } elseif (mb_strlen($message) < 10) {
            $errors['message'] = 'Message must be at least 10 characters.';
        } elseif (mb_strlen($message) > 2000) {
            $errors['message'] = 'Message must be 2000 characters or fewer.';
        }

        // ----- Failure path: re-show the form with what they typed
        if ($errors) {
            save_old(['name' => $name, 'email' => $email, 'message' => $message]);
            set_errors($errors);
            $this->redirect('contact');
        }

        // ----- Success path ---------------------------------------
        Contact::create($name, $email, $message);

        // Use a contact-scoped flash key so the header's global
        // success banner ignores it — the view renders this one
        // inline, right above the form.
        flash('contact_success', 'Thanks! Your message has been sent.');
        $this->redirect('contact');
    }
}
