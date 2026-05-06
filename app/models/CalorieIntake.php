<?php
// ============================================================
// app/models/CalorieIntake.php
// Database logic for the calorie_intake_logs table.
//
// Each row = one meal / one logged eating event for a user.
// Many rows can share the same (user_id, logged_date) — the day's
// total is the SUM of those rows. The optional `label` column is
// a free-form name like "Lunch" or "Pizza".
//
// Sister model to CalorieLog (which stores target snapshots).
// ============================================================

class CalorieIntake {

    // Insert one new intake entry. Returns the new row's id.
    public static function create(int $userId, int $calories, string $date, ?string $label): int {
        $stmt = db()->prepare(
            'INSERT INTO calorie_intake_logs (user_id, calories, label, logged_date)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $calories, $label, $date]);
        return (int) db()->lastInsertId();
    }

    // Update calories + label on an existing entry. The date is
    // intentionally not editable — to "move" an entry, delete and
    // re-add, which keeps the audit trail honest.
    public static function update(int $id, int $userId, int $calories, ?string $label): void {
        $stmt = db()->prepare(
            'UPDATE calorie_intake_logs
             SET calories = ?, label = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$calories, $label, $id, $userId]);
    }

    // Full history for one user, newest-first (one row per entry).
    // Used by the per-meal history table in the view.
    //
    // $sinceDate (YYYY-MM-DD) limits the query to entries on or after
    // that date — used by the time-range filter on the calorie page.
    // Pass null to fetch all entries.
    public static function forUser(int $userId, ?string $sinceDate = null): array {
        if ($sinceDate === null) {
            $stmt = db()->prepare(
                'SELECT * FROM calorie_intake_logs
                 WHERE user_id = ?
                 ORDER BY logged_date DESC, id DESC'
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = db()->prepare(
                'SELECT * FROM calorie_intake_logs
                 WHERE user_id = ? AND logged_date >= ?
                 ORDER BY logged_date DESC, id DESC'
            );
            $stmt->execute([$userId, $sinceDate]);
        }
        return $stmt->fetchAll();
    }

    // All entries for a single date, ordered by insertion (oldest-first
    // so the user's "Today's meals" list reads in the order they ate).
    public static function forUserOnDate(int $userId, string $date): array {
        $stmt = db()->prepare(
            'SELECT * FROM calorie_intake_logs
             WHERE user_id = ? AND logged_date = ?
             ORDER BY id ASC'
        );
        $stmt->execute([$userId, $date]);
        return $stmt->fetchAll();
    }

    // Sum of calories for a single date. Returns 0 if no rows.
    // Used for "you're at X today" hints + the dashboard card.
    public static function totalForUserOnDate(int $userId, string $date): int {
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(calories), 0)
             FROM calorie_intake_logs
             WHERE user_id = ? AND logged_date = ?'
        );
        $stmt->execute([$userId, $date]);
        return (int) $stmt->fetchColumn();
    }

    // Daily totals for the chart. Returns rows like
    //   [{ logged_date: 'YYYY-MM-DD', calories: 1850 }, ...]
    // newest-first; reverse in the controller for the chart's
    // left-to-right time progression. $sinceDate honors the range
    // filter the same way as forUser().
    public static function dailyTotalsForUser(int $userId, ?string $sinceDate = null): array {
        if ($sinceDate === null) {
            $stmt = db()->prepare(
                'SELECT logged_date, SUM(calories) AS calories
                 FROM calorie_intake_logs
                 WHERE user_id = ?
                 GROUP BY logged_date
                 ORDER BY logged_date DESC'
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = db()->prepare(
                'SELECT logged_date, SUM(calories) AS calories
                 FROM calorie_intake_logs
                 WHERE user_id = ? AND logged_date >= ?
                 GROUP BY logged_date
                 ORDER BY logged_date DESC'
            );
            $stmt->execute([$userId, $sinceDate]);
        }
        return $stmt->fetchAll();
    }

    // Number of distinct days a user has logged any intake on
    // (for the profile "calorie days" counter). With many rows now
    // possible per day, we count days, not rows.
    public static function countForUser(int $userId): int {
        $stmt = db()->prepare(
            'SELECT COUNT(DISTINCT logged_date)
             FROM calorie_intake_logs
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // Most recent entry — used by dashboard summary later if needed.
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
