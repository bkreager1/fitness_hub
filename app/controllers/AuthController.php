<?php
// ============================================================
// app/controllers/AuthController.php
// Handles: register, login, logout, forgot-password, reset-password.
// ============================================================

class AuthController extends Controller {

    // ============ REGISTER ============

    public function showRegister(): void {
        $this->requireGuest();
        $this->view('auth/register', ['title' => 'Create an Account']);
    }

    public function register(): void {
        $this->requireGuest();
        csrf_verify();

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        // Remember the safe fields so the form can re-render them on error.
        // Never save passwords in old-input.
        save_old(['name' => $name, 'email' => $email]);

        // Field-level errors — keyed by input name so the view can
        // render each one directly under the input it belongs to.
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
        }

        if ($passErr = validate_password_rules($password)) {
            $errors['password'] = $passErr;
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        // Only hit the DB for uniqueness if nothing else is broken.
        if (empty($errors) && User::findByEmail($email)) {
            $errors['email'] = 'An account with that email already exists.';
        }

        if ($errors) {
            set_errors($errors);
            $this->redirect('register');
            return;
        }

        $userId = User::create($name, $email, $password);
        $user   = User::find($userId);

        // Soft email verification: issue a token, email a link, but let
        // the user into the app immediately — a sitewide banner nudges
        // them until they confirm.
        $this->sendVerificationEmail($user);

        $this->startUserSession($user, false);

        flash('success', 'Welcome, ' . $user['name'] . '! Check your inbox for a verification link.');
        $this->redirect('dashboard');
    }

    // ============ EMAIL VERIFICATION ============

    // Clicked from the verification email.
    public function verifyEmail(): void {
        $token = $_GET['token'] ?? '';

        if ($token === '') {
            flash('errors', 'This verification link is missing its token.');
            $this->redirect(is_logged_in() ? 'dashboard' : 'login');
            return;
        }

        $user = User::findByVerificationToken($token);
        if (!$user) {
            flash('errors', 'This verification link is invalid or has expired. Request a new one below.');
            $this->redirect(is_logged_in() ? 'dashboard' : 'login');
            return;
        }

        User::markEmailVerified((int) $user['id']);

        // If the verifier is the currently-logged-in user, refresh the
        // session copy so the header banner disappears on the next page.
        if (is_logged_in() && (int) current_user_id() === (int) $user['id']) {
            $_SESSION['user']['email_verified_at'] = date('Y-m-d H:i:s');
        }

        flash('success', 'Email verified — thanks!');
        $this->redirect(is_logged_in() ? 'dashboard' : 'login');
    }

    // Posted from the unverified-banner "Resend" button.
    public function resendVerification(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = (int) current_user_id();
        $user   = User::find($userId);

        if ($user && $user['email_verified_at'] === null) {
            $this->sendVerificationEmail($user);
            flash('success', 'Verification email sent. Check your inbox.');
        } else {
            // Already verified — just refresh the session.
            if ($user) {
                $_SESSION['user']['email_verified_at'] = $user['email_verified_at'];
            }
        }

        // Bounce back to wherever they were. Fall back to dashboard.
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        if ($back !== '' && parse_url($back, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
            header('Location: ' . $back);
            exit;
        }
        $this->redirect('dashboard');
    }

    // Mint a fresh token + send the link. Used by register() and resend.
    private function sendVerificationEmail(array $user): void {
        $rawToken = bin2hex(random_bytes(32));
        User::setVerificationToken((int) $user['id'], $rawToken);

        $link = $this->absoluteUrl('verify-email?token=' . $rawToken);
        $body = "Hi " . $user['name'] . ",\n\n"
              . "Confirm your email so we can recover your account if you "
              . "ever forget your password. Click the link below — it's "
              . "valid for 24 hours.\n\n"
              . $link . "\n\n"
              . "If you didn't sign up for Rock County Fitness Hub, you "
              . "can safely ignore this message.\n\n"
              . "— Rock County Fitness Hub";

        Mailer::send($user['email'], 'Confirm your Rock County Fitness Hub email', $body);
    }

    // ============ LOGIN ============

    public function showLogin(): void {
        $this->requireGuest();
        $this->view('auth/login', ['title' => 'Log In']);
    }

    public function login(): void {
        $this->requireGuest();
        csrf_verify();

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        save_old(['email' => $email, 'remember' => $remember ? '1' : '']);

        // Throttle: 5 failures from this IP in the last 15 minutes locks
        // the form for that window. Counted BEFORE we touch the DB for
        // the user lookup, so a flood of attempts gets cheap rejections.
        $ip = $this->clientIp();
        $failures = LoginAttempt::recentFailuresByIp($ip, LoginAttempt::WINDOW_SECONDS);
        if ($failures >= LoginAttempt::MAX_FAILURES) {
            set_errors(['password' => 'Too many failed attempts from this device. Please wait 15 minutes and try again.']);
            $this->redirect('login');
            return;
        }

        $user = User::findByEmail($email);

        // Use a generic error for both "no such user" and "wrong password" —
        // never leak which one failed.
        if (!$user || !User::checkPassword($password, $user['password'])) {
            LoginAttempt::record($ip, $email, false);
            // Generic on purpose: don't reveal which field was wrong.
            // Shown under the password input — by convention, where
            // users look first when re-entering credentials.
            set_errors(['password' => 'Invalid email or password.']);
            $this->redirect('login');
            return;
        }

        LoginAttempt::record($ip, $email, true);
        $this->startUserSession($user, $remember);
        flash('success', 'Welcome back, ' . $user['name'] . '!');
        $this->redirect('dashboard');
    }

    // Best-guess client IP. We deliberately don't trust X-Forwarded-For
    // here — on Hostinger the request hits PHP directly without a proxy
    // we control, so REMOTE_ADDR is the right source.
    private function clientIp(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    // ============ LOGOUT ============

    public function logout(): void {
        csrf_verify();

        $_SESSION = [];

        // Nuke the session cookie in the browser as well.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }
        session_destroy();

        redirect('');
    }

    // ============ FORGOT PASSWORD ============

    public function showForgotPassword(): void {
        $this->requireGuest();
        $this->view('auth/forgot', ['title' => 'Forgot Password']);
    }

    public function sendResetLink(): void {
        $this->requireGuest();
        csrf_verify();

        $email = trim($_POST['email'] ?? '');
        save_old(['email' => $email]);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_errors(['email' => 'Please enter a valid email address.']);
            $this->redirect('forgot-password');
            return;
        }

        $user = User::findByEmail($email);

        // Do the real work only if the email matches an account — but always
        // return the same response, so an attacker can't enumerate which
        // emails are registered.
        if ($user) {
            $rawToken = bin2hex(random_bytes(32));
            PasswordReset::invalidateAllForUser((int) $user['id']);
            PasswordReset::create((int) $user['id'], $rawToken);

            $link = $this->absoluteUrl('reset-password?token=' . $rawToken);
            $body = "Hi " . $user['name'] . ",\n\n"
                  . "We received a request to reset your password. Click the "
                  . "link below to set a new one — it's valid for 1 hour.\n\n"
                  . $link . "\n\n"
                  . "If you didn't request this, you can safely ignore this "
                  . "message; your password won't change.\n\n"
                  . "— Rock County Fitness Hub";
            Mailer::send($user['email'], 'Reset your Rock County Fitness Hub password', $body);
        }

        $devHint = APP_ENV === 'prod' ? '' : ' (Dev mode: check storage/mail.log)';
        flash(
            'success',
            'If an account exists for that email, a reset link has been sent.' . $devHint
        );
        $this->redirect('forgot-password');
    }

    // ============ RESET PASSWORD ============

    public function showResetPassword(): void {
        $this->requireGuest();

        $token = $_GET['token'] ?? '';
        $reset = $token !== '' ? PasswordReset::findValidToken($token) : null;

        if (!$reset) {
            flash('errors', 'This reset link is invalid or has expired. Please request a new one.');
            $this->redirect('forgot-password');
            return;
        }

        $this->view('auth/reset', [
            'title' => 'Set a New Password',
            'token' => $token,
        ]);
    }

    public function resetPassword(): void {
        $this->requireGuest();
        csrf_verify();

        $token    = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        $reset = $token !== '' ? PasswordReset::findValidToken($token) : null;
        if (!$reset) {
            flash('errors', 'This reset link is invalid or has expired. Please request a new one.');
            $this->redirect('forgot-password');
            return;
        }

        $errors = [];
        if ($passErr = validate_password_rules($password)) {
            $errors['password'] = $passErr;
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        if ($errors) {
            set_errors($errors);
            $this->redirect('reset-password?token=' . urlencode($token));
            return;
        }

        $userId = (int) $reset['user_id'];
        User::updatePassword($userId, $password);

        // Burn every outstanding token for this user — both the one just used
        // and any still-unexpired duplicates.
        PasswordReset::invalidateAllForUser($userId);

        flash('success', 'Your password has been reset. Please log in with your new password.');
        $this->redirect('login');
    }

    // ============ INTERNAL HELPERS ============

    // Sign a user in: rotate the session id (to block session fixation),
    // stash identity in the session, and optionally extend the cookie lifetime.
    private function startUserSession(array $user, bool $remember): void {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user'] = [
            'id'                 => (int) $user['id'],
            'name'               => $user['name'],
            'email'              => $user['email'],
            'profile_image_path' => $user['profile_image_path'] ?? null,
            'email_verified_at'  => $user['email_verified_at']  ?? null,
        ];

        if ($remember) {
            $lifetime = 30 * 24 * 60 * 60;   // 30 days
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                session_id(),
                [
                    'expires'  => time() + $lifetime,
                    'path'     => $p['path']   ?: '/',
                    'domain'   => $p['domain'] ?? '',
                    'secure'   => (bool) ($p['secure']   ?? false),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }
    }

    // Build a fully-qualified URL (scheme + host + path) from a relative path.
    private function absoluteUrl(string $path): string {
        $https  = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') == 443;
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . url($path);
    }

}
