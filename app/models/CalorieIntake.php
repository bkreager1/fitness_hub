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
    // $macros = ['protein_g' => ?int, 'carbs_g' => ?int, 'fat_g' => ?int]
    // Each macro is optional — null means "the user didn't fill it in".
    public static function create(
        int $userId, int $calories, string $date, ?string $label, array $macros = []
    ): int {
        $stmt = db()->prepare(
            'INSERT INTO calorie_intake_logs
                (user_id, calories, protein_g, carbs_g, fat_g, label, logged_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $calories,
            $macros['protein_g'] ?? null,
            $macros['carbs_g']   ?? null,
            $macros['fat_g']     ?? null,
            $label,
            $date,
        ]);
        return (int) db()->lastInsertId();
    }

    // Update calories + label + macros on an existing entry. The date
    // is intentionally not editable — to "move" an entry, delete and
    // re-add, which keeps the audit trail honest.
    public static function update(
        int $id, int $userId, int $calories, ?string $label, array $macros = []
    ): void {
        $stmt = db()->prepare(
            'UPDATE calorie_intake_logs
             SET calories = ?, protein_g = ?, carbs_g = ?, fat_g = ?, label = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $calories,
            $macros['protein_g'] ?? null,
            $macros['carbs_g']   ?? null,
            $macros['fat_g']     ?? null,
            $label,
            $id,
            $userId,
        ]);
    }

    // Per-day macro totals for the dashboard. Returns rows like
    //   { logged_date, protein_g, carbs_g, fat_g }
    // where each macro is the SUM across that day's meals (null if
    // none of the meals had a macro entered). Used to drive the
    // dashboard's macro bars without pulling the full per-meal list.
    public static function macroTotalsForUserOnDate(int $userId, string $date): array {
        $stmt = db()->prepare(
            'SELECT
                COALESCE(SUM(protein_g), 0) AS protein_g,
                COALESCE(SUM(carbs_g),   0) AS carbs_g,
                COALESCE(SUM(fat_g),     0) AS fat_g,
                SUM(CASE WHEN protein_g IS NOT NULL
                          OR carbs_g   IS NOT NULL
                          OR fat_g     IS NOT NULL THEN 1 ELSE 0 END) AS macro_rows
             FROM calorie_intake_logs
             WHERE user_id = ? AND logged_date = ?'
        );
        $stmt->execute([$userId, $date]);
        $row = $stmt->fetch() ?: [];
        return [
            'protein_g'  => (int) ($row['protein_g'] ?? 0),
            'carbs_g'    => (int) ($row['carbs_g']   ?? 0),
            'fat_g'      => (int) ($row['fat_g']     ?? 0),
            'macro_rows' => (int) ($row['macro_rows'] ?? 0),
        ];
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

    // Count of meal rows logged on or after $sinceDate. Used by the
    // dashboard stat strip ("X meals this week").
    public static function countForUserSince(int $userId, string $sinceDate): int {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM calorie_intake_logs
             WHERE user_id = ? AND logged_date >= ?'
        );
        $stmt->execute([$userId, $sinceDate]);
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
