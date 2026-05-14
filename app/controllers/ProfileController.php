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

        $userId = (int) current_user_id();
        $user   = User::find($userId);

        // Read-only summary shown above the edit forms — gives the page
        // a "view your profile" feel without duplicating the dashboard.
        $summary = [
            'weight_logs'    => WeightLog::countForUser($userId),
            'calorie_days'   => CalorieIntake::countForUser($userId),
            'strength_sets'  => StrengthLog::countForUser($userId),
        ];

        $this->view('profile/index', [
            'title'   => 'Profile',
            'active'  => 'profile',
            'user'    => $user,
            'summary' => $summary,
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

    // ============ UPDATE GOALS ============
    // Target weight + target bench/squat/deadlift. All four fields
    // are optional — submitting blank values clears the goal so the
    // dashboard hides the corresponding progress bar.
    //
    // Display unit comes from the user's preferred unit on the form
    // (lbs or kg). We convert to canonical kg before persisting so
    // every weight in the DB lives on the same scale.
    public function goals(): void {
        $this->requireLogin();
        csrf_verify();

        $userId = (int) current_user_id();
        $unit   = ($_POST['goals_unit'] ?? 'lbs') === 'kg' ? 'kg' : 'lbs';

        // Field map: form name → DB column. Same shape for all four so
        // the validation loop stays compact.
        $fields = [
            'target_weight'   => ['column' => 'target_weight_kg',   'label' => 'Target weight'],
            'target_bench'    => ['column' => 'target_bench_kg',    'label' => 'Target bench'],
            'target_squat'    => ['column' => 'target_squat_kg',    'label' => 'Target squat'],
            'target_deadlift' => ['column' => 'target_deadlift_kg', 'label' => 'Target deadlift'],
        ];

        $errors  = [];
        $goalsKg = [];
        $oldVals = ['goals_unit' => $unit];

        foreach ($fields as $name => $meta) {
            $raw = trim($_POST[$name] ?? '');
            $oldVals[$name] = $raw;

            if ($raw === '') {
                $goalsKg[$meta['column']] = null;
                continue;
            }

            if (!is_numeric($raw)) {
                $errors[$name] = $meta['label'] . ' must be a number.';
                continue;
            }

            $val = (float) $raw;
            // Sensible bounds in the display unit before conversion.
            $min = $unit === 'lbs' ? 1   : 0.5;
            $max = $unit === 'lbs' ? 2000 : 900;
            if ($val < $min || $val > $max) {
                $errors[$name] = $meta['label'] . ' is outside the allowed range.';
                continue;
            }

            // Convert to canonical kg. Round to 2 decimals to match the
            // DECIMAL(_, 2) column precision.
            $kg = $unit === 'kg' ? $val : $val / 2.2046226218;
            $goalsKg[$meta['column']] = round($kg, 2);
        }

        save_old($oldVals);

        if ($errors) {
            set_errors($errors);
            $this->redirect('profile');
            return;
        }

        User::updateGoals($userId, $goalsKg);

        flash('goals_success', 'Goals updated.');
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
