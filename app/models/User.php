<?php
// ============================================================
// app/models/User.php
// Database logic for the users table.
//
// Columns (Phase 1):
//   id, name, email, password, profile_image_path, created_at
// ============================================================

class User {

    // Find a user by primary key. Returns null if not found.
    public static function find(int $id): ?array {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Find a user by email (case-insensitive because MySQL default collation).
    // Used by login + forgot-password flows.
    public static function findByEmail(string $email): ?array {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Create a new user. Hashes the password with bcrypt before storing.
    // Returns the new row's id.
    public static function create(string $name, string $email, string $password): int {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, password, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$name, $email, $hash]);
        return (int) db()->lastInsertId();
    }

    // Verify a plaintext password against a stored bcrypt hash.
    public static function checkPassword(string $plain, string $hash): bool {
        return password_verify($plain, $hash);
    }

    // Update the profile fields a user can change themselves (Phase 8).
    public static function updateProfile(int $id, string $name, string $email): void {
        $stmt = db()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
        $stmt->execute([$name, $email, $id]);
    }

    // Replace the stored password hash for a user.
    public static function updatePassword(int $id, string $newPassword): void {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);
    }

    // Set (or clear, by passing null) the user's profile-image filename.
    // We store just the bare filename — the URL is reconstructed in views.
    public static function updateProfileImage(int $id, ?string $filename): void {
        $stmt = db()->prepare('UPDATE users SET profile_image_path = ? WHERE id = ?');
        $stmt->execute([$filename, $id]);
    }

    // Set the user's active calorie goal. Caller must validate the
    // value against ALLOWED_GOALS before calling.
    public const ALLOWED_GOALS = ['cut', 'maintain', 'bulk'];

    public static function updateGoal(int $id, string $goal): void {
        $stmt = db()->prepare('UPDATE users SET current_goal = ? WHERE id = ?');
        $stmt->execute([$goal, $id]);
    }

    // Update the goal columns:
    //   - target_weight_kg + target_bench/squat/deadlift_kg
    //     (canonical kg, nullable — caller validates ranges + converts
    //     from the user's display unit)
    //   - weekly_workout_target + weekly_cardio_target
    //     (whole-number days/sessions per week, nullable — caller
    //     validates 1..7 range)
    public static function updateGoals(int $id, array $goals): void {
        $stmt = db()->prepare(
            'UPDATE users
             SET target_weight_kg      = ?,
                 target_bench_kg       = ?,
                 target_squat_kg       = ?,
                 target_deadlift_kg    = ?,
                 weekly_workout_target = ?,
                 weekly_cardio_target  = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $goals['target_weight_kg']      ?? null,
            $goals['target_bench_kg']       ?? null,
            $goals['target_squat_kg']       ?? null,
            $goals['target_deadlift_kg']    ?? null,
            $goals['weekly_workout_target'] ?? null,
            $goals['weekly_cardio_target']  ?? null,
            $id,
        ]);
    }
}
