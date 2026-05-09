<?php
// ============================================================
// app/models/WeightLog.php
// Database logic for the weight_logs table.
//
// Each row = one weigh-in. Weight is stored canonically in kg,
// alongside the unit the user originally typed it in ('lbs' or
// 'kg') so the table can render it back in their preferred unit.
//
// One row per (user, date), enforced by UNIQUE(user_id, logged_date).
// Saving the same date upserts via INSERT ... ON DUPLICATE KEY UPDATE.
// Matches the calorie_intake_logs pattern.
// ============================================================

class WeightLog {

    public const ALLOWED_UNITS = ['lbs', 'kg'];

    // Insert or update one day's weigh-in. Caller must validate first.
    // The UNIQUE(user_id, logged_date) key turns same-date saves into
    // overwrites. LAST_INSERT_ID(id) trick keeps the returned id stable
    // whether this was an insert or an update.
    public static function upsert(int $userId, array $data): int {
        $stmt = db()->prepare(
            'INSERT INTO weight_logs
                (user_id, weight_kg, unit, logged_date, notes)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                weight_kg = VALUES(weight_kg),
                unit      = VALUES(unit),
                notes     = VALUES(notes),
                id        = LAST_INSERT_ID(id)'
        );
        $stmt->execute([
            $userId,
            $data['weight_kg'],
            $data['unit'],
            $data['logged_date'],
            $data['notes'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    // Update one row. Scoped to its owner so users can't touch
    // each other's data.
    public static function update(int $id, int $userId, array $data): void {
        $stmt = db()->prepare(
            'UPDATE weight_logs
             SET weight_kg = ?, unit = ?, logged_date = ?, notes = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $data['weight_kg'],
            $data['unit'],
            $data['logged_date'],
            $data['notes'] ?? null,
            $id,
            $userId,
        ]);
    }

    // Full history for a user, newest first (table order).
    // Reverse in the controller for chart rendering.
    //
    // $sinceDate (YYYY-MM-DD) limits the query to entries on or after
    // that date — used by the time-range filter on the weight page.
    // Pass null to fetch all entries.
    public static function forUser(int $userId, ?string $sinceDate = null): array {
        if ($sinceDate === null) {
            $stmt = db()->prepare(
                'SELECT * FROM weight_logs
                 WHERE user_id = ?
                 ORDER BY logged_date DESC, id DESC'
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = db()->prepare(
                'SELECT * FROM weight_logs
                 WHERE user_id = ? AND logged_date >= ?
                 ORDER BY logged_date DESC, id DESC'
            );
            $stmt->execute([$userId, $sinceDate]);
        }
        return $stmt->fetchAll();
    }

    // Total number of weigh-ins for a user (for profile summary).
    public static function countForUser(int $userId): int {
        $stmt = db()->prepare('SELECT COUNT(*) FROM weight_logs WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // Most recent weigh-in (for the dashboard summary later).
    public static function latestForUser(int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM weight_logs
             WHERE user_id = ?
             ORDER BY logged_date DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Find a single row scoped to its owner.
    public static function find(int $id, int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM weight_logs
             WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function delete(int $id, int $userId): void {
        $stmt = db()->prepare(
            'DELETE FROM weight_logs
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }
}
