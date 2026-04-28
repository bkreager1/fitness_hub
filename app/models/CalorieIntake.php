<?php
// ============================================================
// app/models/CalorieIntake.php
// Database logic for the calorie_intake_logs table.
//
// Each row = one day's calorie consumption total for a user.
// Schema enforces UNIQUE(user_id, logged_date), so re-saving the
// same date upserts via INSERT ... ON DUPLICATE KEY UPDATE.
//
// Sister model to CalorieLog (which stores target snapshots).
// ============================================================

class CalorieIntake {

    // Insert or update one day's intake. Same (user_id, logged_date)
    // pair overwrites the existing calories. Returns the affected
    // row's id (whether new or updated).
    public static function upsert(int $userId, int $calories, string $date): int {
        $stmt = db()->prepare(
            'INSERT INTO calorie_intake_logs (user_id, calories, logged_date)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                calories = VALUES(calories),
                id       = LAST_INSERT_ID(id)'
        );
        $stmt->execute([$userId, $calories, $date]);
        return (int) db()->lastInsertId();
    }

    // Full history for one user, newest-first (table order).
    // Reverse this in the controller for chart rendering.
    public static function forUser(int $userId): array {
        $stmt = db()->prepare(
            'SELECT * FROM calorie_intake_logs
             WHERE user_id = ?
             ORDER BY logged_date DESC, id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // Today's row (or whatever date the form is asking about).
    // Used to pre-fill the intake form's calories field.
    public static function forUserOnDate(int $userId, string $date): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM calorie_intake_logs
             WHERE user_id = ? AND logged_date = ? LIMIT 1'
        );
        $stmt->execute([$userId, $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Most recent intake row (for dashboard summary later).
    public static function latestForUser(int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM calorie_intake_logs
             WHERE user_id = ?
             ORDER BY logged_date DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Find a single row scoped to its owner (security).
    public static function find(int $id, int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM calorie_intake_logs
             WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function delete(int $id, int $userId): void {
        $stmt = db()->prepare(
            'DELETE FROM calorie_intake_logs
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }
}
