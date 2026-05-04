<?php
// ============================================================
// app/controllers/ProfileController.php
// Phase 8 — Account settings: name/email, password, avatar.
//
// All actions require login. Each form posts to its own action so
// success/error state is scoped to the section it came from.
// ============================================================

class ProfileController extends Controller {

    // Avatar upload constraints.
    private const MAX_BYTES = 2 * 1024 * 1024;            // 2 MB
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // ============ INDEX ============

    public function index(): void {
        $this->requireLogin();

        $user = User::find((int) current_user_id());

        $this->view('profile/index', [
            'title'  => 'Profile',
            'active' => 'profile',
            'user'   => $user,
        ]);
    }

    // ============ UPDATE NAME / EMAIL ============

    public function update(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = (int) current_user_id();
        $name   = trim($_POST['name']  ?? '');
        $email  = trim($_POST['email'] ?? '');

        save_old(['name' => $name, 'email' => $email]);

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
        } else {
            // Email must be unique — but it's fine if it still belongs to *this* user.
            $existing = User::findByEmail($email);
            if ($existing && (int) $existing['id'] !== $userId) {
                $errors['email'] = 'An account with that email already exists.';
            }
        }

        if ($errors) {
            set_errors($errors);
            $this->redirect('profile');
            return;
        }

        User::updateProfile($userId, $name, $email);
        $this->refreshSession($userId);

        flash('success', 'Profile updated.');
        $this->redirect('profile');
    }

    // ============ CHANGE PASSWORD ============

    public function password(): void {
        $this->requireLogin();
        csrf_verify();

        $userId  = (int) current_user_id();
        $current = $_POST['current_password']     ?? '';
        $new     = $_POST['new_password']         ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';

        $user   = User::find($userId);
        $errors = [];

        if ($current === '') {
            $errors['current_password'] = 'Enter your current password.';
        } elseif (!User::checkPassword($current, $user['password'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }

        if ($passErr = validate_password_rules($new)) {
            $errors['new_password'] = $passErr;
        }
        if ($new !== $confirm) {
            $errors['new_password_confirm'] = 'Passwords do not match.';
        }

        if ($errors) {
            set_errors($errors);
            $this->redirect('profile');
            return;
        }

        User::updatePassword($userId, $new);

        flash('success', 'Password changed.');
        $this->redirect('profile');
    }

    // ============ UPLOAD AVATAR ============

    public function uploadImage(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = (int) current_user_id();
        $file   = $_FILES['avatar'] ?? null;

        $err = $this->validateUpload($file);
        if ($err !== null) {
            set_errors(['avatar' => $err]);
            $this->redirect('profile');
            return;
        }

        // MIME-sniff the actual bytes — don't trust the client-supplied type
        // or the filename extension. finfo opens the real file and reads its
        // magic bytes.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';

        if (!isset(self::ALLOWED_MIME[$mime])) {
            set_errors(['avatar' => 'Only JPG, PNG, and WebP images are allowed.']);
            $this->redirect('profile');
            return;
        }

        $ext      = self::ALLOWED_MIME[$mime];
        $filename = sprintf('user_%d_%s.%s', $userId, bin2hex(random_bytes(6)), $ext);
        $dest     = APP_ROOT . '/public/uploads/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            set_errors(['avatar' => 'Could not save your image. Please try again.']);
            $this->redirect('profile');
            return;
        }

        // Replacing an existing avatar: delete the old file from disk so we
        // don't leave orphan uploads behind.
        $user = User::find($userId);
        if (!empty($user['profile_image_path'])) {
            $this->deleteAvatarFile($user['profile_image_path']);
        }

        User::updateProfileImage($userId, $filename);
        $this->refreshSession($userId);

        flash('success', 'Profile photo updated.');
        $this->redirect('profile');
    }

    // ============ DELETE AVATAR ============

    public function deleteImage(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = (int) current_user_id();
        $user   = User::find($userId);

        if (!empty($user['profile_image_path'])) {
            $this->deleteAvatarFile($user['profile_image_path']);
            User::updateProfileImage($userId, null);
            $this->refreshSession($userId);
            flash('success', 'Profile photo removed.');
        }

        $this->redirect('profile');
    }

    // ============ INTERNAL HELPERS ============

    // Translate a $_FILES entry's error code + size into a user-facing message.
    // Returns null if the upload itself looks OK (caller still needs to check MIME).
    private function validateUpload(?array $file): ?string {
        if (!$file || !isset($file['error'])) {
            return 'Please choose a file to upload.';
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return 'Please choose a file to upload.';
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'That image is too large. Max size is 2 MB.';
            default:
                return 'Upload failed. Please try again.';
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return 'That image is too large. Max size is 2 MB.';
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return 'Upload failed. Please try again.';
        }
        return null;
    }

    // Best-effort file delete. Constrains the path to /public/uploads so a
    // tampered DB row can't traverse out of that directory.
    private function deleteAvatarFile(string $filename): void {
        $safe = basename($filename);
        $path = APP_ROOT . '/public/uploads/' . $safe;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    // Re-read the user row and overwrite the cached session copy so the nav
    // (avatar, name) reflects the change immediately on the next request.
    private function refreshSession(int $userId): void {
        $user = User::find($userId);
        if (!$user) return;
        $_SESSION['user'] = [
            'id'                 => (int) $user['id'],
            'name'               => $user['name'],
            'email'              => $user['email'],
            'profile_image_path' => $user['profile_image_path'] ?? null,
        ];
    }
}
