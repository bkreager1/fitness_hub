<?php
// ============================================================
// app/models/CalorieLog.php
// Database logic for the calorie_logs table.
//
// Each row is a *snapshot* of the user's inputs (age, gender,
// weight, height, activity) on a given date plus the three
// calorie targets (maintenance / cut / bulk) that follow from
// them via the Mifflin-St Jeor formula.
//
// There is no update() — snapshots are immutable. To "change"
// a log, the user deletes it and creates a new one.
// ============================================================

class CalorieLog {

    // Allowed values for the activity_level ENUM. Kept here so the
    // controller and views can reference one canonical list.
    public const ACTIVITY_LEVELS = [
        'sedentary'         => 'Sedentary (little / no exercise)',
        'lightly_active'    => 'Lightly active (1–3 days / week)',
        'moderately_active' => 'Moderately active (3–5 days / week)',
        'very_active'       => 'Very active (6–7 days / week)',
        'extra_active'      => 'Extra active (physical job + training)',
    ];

    // Insert a new snapshot. Caller is responsible for having already
    // validated $data and computed the three calorie targets.
    public static function create(int $userId, array $data): int {
        $stmt = db()->prepare(
            'INSERT INTO calorie_logs (
                user_id, age, gender, weight_kg, height_cm,
                activity_level, maintenance_calories,
                cutting_calories, bulking_calories, logged_date
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['age'],
            $data['gender'],
            $data['weight_kg'],
            $data['height_cm'],
            $data['activity_level'],
            $data['maintenance_calories'],
            $data['cutting_calories'],
            $data['bulking_calories'],
            $data['logged_date'],
        ]);
        return (int) db()->lastInsertId();
    }

    // Full history for one user, newest first (table order).
    // Reverse this in the view layer for chart rendering.
    public static function forUser(int $userId): array {
        $stmt = db()->prepare(
            'SELECT * FROM calorie_logs
             WHERE user_id = ?
             ORDER BY logged_date DESC, id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // Most recent snapshot (for the dashboard summary).
    public static function latestForUser(int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM calorie_logs
             WHERE user_id = ?
             ORDER BY logged_date DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Find a single row, but scoped to its owner so users can't
    // touch each other's data.
    public static function find(int $id, int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM calorie_logs
             WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Delete one row, scoped to its owner.
    public static function delete(int $id, int $userId): void {
        $stmt = db()->prepare(
            'DELETE FROM calorie_logs
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }
}
