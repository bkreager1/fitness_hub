<?php
// ============================================================
// app/models/StrengthLog.php
// Database logic for the strength_logs table.
//
// Each row = one lift attempt. Schema stores weight + unit
// alongside lift_type, so a row could be "bench, 225 lbs x 5".
// Multiple entries per (user, date) are allowed — strength
// training is naturally more variable than weight or calories.
// ============================================================

class StrengthLog {

    public const ALLOWED_LIFTS = ['bench', 'squat', 'deadlift'];
    public const ALLOWED_UNITS = ['lbs', 'kg'];

    // Friendly labels for views (lift_type ENUM key → label).
    public const LIFT_LABELS = [
        'bench'    => 'Bench press',
        'squat'    => 'Squat',
        'deadlift' => 'Deadlift',
    ];

    // Insert a new lift entry. Caller validates first.
    public static function create(int $userId, array $data): int {
        $stmt = db()->prepare(
            'INSERT INTO strength_logs
                (user_id, lift_type, weight, reps, unit, logged_date, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['lift_type'],
            $data['weight'],
            $data['reps'],
            $data['unit'],
            $data['logged_date'],
            $data['notes'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    // Update one row, scoped to its owner.
    public static function update(int $id, int $userId, array $data): void {
        $stmt = db()->prepare(
            'UPDATE strength_logs
             SET lift_type = ?, weight = ?, reps = ?, unit = ?,
                 logged_date = ?, notes = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $data['lift_type'],
            $data['weight'],
            $data['reps'],
            $data['unit'],
            $data['logged_date'],
            $data['notes'] ?? null,
            $id,
            $userId,
        ]);
    }

    // Full history for a user, newest first (table order).
    public static function forUser(int $userId): array {
        $stmt = db()->prepare(
            'SELECT * FROM strength_logs
             WHERE user_id = ?
             ORDER BY logged_date DESC, id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // Most recent lift overall (any type) — used by dashboard later.
    public static function latestForUser(int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM strength_logs
             WHERE user_id = ?
             ORDER BY logged_date DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Most recent entry per lift type — useful for "current PRs"
    // style summaries on the dashboard.
    // Returns ['bench' => row|null, 'squat' => …, 'deadlift' => …].
    public static function latestPerLiftForUser(int $userId): array {
        $out = ['bench' => null, 'squat' => null, 'deadlift' => null];

        $stmt = db()->prepare(
            'SELECT s.*
             FROM strength_logs s
             INNER JOIN (
                SELECT lift_type, MAX(logged_date) AS max_date
                FROM strength_logs
                WHERE user_id = ?
                GROUP BY lift_type
             ) t ON s.lift_type = t.lift_type AND s.logged_date = t.max_date
             WHERE s.user_id = ?'
        );
        $stmt->execute([$userId, $userId]);
        foreach ($stmt->fetchAll() as $row) {
            // If two same-day entries tie on the max date, keep
            // whichever has the higher id (most recent insert).
            $existing = $out[$row['lift_type']] ?? null;
            if (!$existing || (int) $row['id'] > (int) $existing['id']) {
                $out[$row['lift_type']] = $row;
            }
        }
        return $out;
    }

    // Find a single row scoped to its owner.
    public static function find(int $id, int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM strength_logs
             WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function delete(int $id, int $userId): void {
        $stmt = db()->prepare(
            'DELETE FROM strength_logs
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }

    // ---- Conversion helper (used by views + chart data prep) ----
    // Returns the weight in canonical kg regardless of input unit.
    public static function toKg(float $weight, string $unit): float {
        return $unit === 'kg' ? $weight : $weight / 2.2046226218;
    }
}
