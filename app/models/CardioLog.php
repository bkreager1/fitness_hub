<?php
// ============================================================
// app/models/CardioLog.php
// Database logic for the cardio_logs table.
//
// Each row = one cardio session. Multiple sessions per day
// are allowed (morning run + evening walk are two rows), so
// there's no UNIQUE (user, date) constraint.
//
// Mirrors the StrengthLog pattern (one-row-per-entry, scoped
// CRUD, range-aware history fetch).
// ============================================================

class CardioLog {

    public const ALLOWED_TYPES = [
        'walk', 'run', 'bike', 'elliptical',
        'row', 'stairs', 'sport', 'other',
    ];

    public const ALLOWED_INTENSITIES = ['easy', 'moderate', 'hard'];

    public const ALLOWED_DISTANCE_UNITS = ['mi', 'km'];

    // Friendly labels for views (cardio_type ENUM key → label).
    public const TYPE_LABELS = [
        'walk'       => 'Walk',
        'run'        => 'Run',
        'bike'       => 'Bike',
        'elliptical' => 'Elliptical',
        'row'        => 'Row',
        'stairs'     => 'Stairs',
        'sport'      => 'Sport',
        'other'      => 'Other',
    ];

    public const INTENSITY_LABELS = [
        'easy'     => 'Easy',
        'moderate' => 'Moderate',
        'hard'     => 'Hard',
    ];

    // Insert a new cardio session. Caller validates first.
    public static function create(int $userId, array $data): int {
        $stmt = db()->prepare(
            'INSERT INTO cardio_logs
                (user_id, cardio_type, duration_min, intensity,
                 distance, distance_unit, logged_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['cardio_type'],
            $data['duration_min'],
            $data['intensity']     ?? null,
            $data['distance']      ?? null,
            $data['distance_unit'] ?? null,
            $data['logged_date'],
        ]);
        return (int) db()->lastInsertId();
    }

    // Update one row, scoped to its owner.
    public static function update(int $id, int $userId, array $data): void {
        $stmt = db()->prepare(
            'UPDATE cardio_logs
             SET cardio_type = ?, duration_min = ?, intensity = ?,
                 distance = ?, distance_unit = ?, logged_date = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $data['cardio_type'],
            $data['duration_min'],
            $data['intensity']     ?? null,
            $data['distance']      ?? null,
            $data['distance_unit'] ?? null,
            $data['logged_date'],
            $id,
            $userId,
        ]);
    }

    // Full history for a user, newest first (table order).
    //
    // $sinceDate (YYYY-MM-DD) limits the query to entries on or after
    // that date — used by the time-range filter on the cardio page.
    // Pass null to fetch all entries.
    public static function forUser(int $userId, ?string $sinceDate = null): array {
        if ($sinceDate === null) {
            $stmt = db()->prepare(
                'SELECT * FROM cardio_logs
                 WHERE user_id = ?
                 ORDER BY logged_date DESC, id DESC'
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = db()->prepare(
                'SELECT * FROM cardio_logs
                 WHERE user_id = ? AND logged_date >= ?
                 ORDER BY logged_date DESC, id DESC'
            );
            $stmt->execute([$userId, $sinceDate]);
        }
        return $stmt->fetchAll();
    }

    // Total number of logged cardio sessions (for profile summary).
    public static function countForUser(int $userId): int {
        $stmt = db()->prepare('SELECT COUNT(*) FROM cardio_logs WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // Number of distinct days a user has logged any cardio on. Used to
    // distinguish "no logs at all" from "no logs in this range" on the
    // cardio history range filter.
    public static function countLoggedDaysForUser(int $userId): int {
        $stmt = db()->prepare(
            'SELECT COUNT(DISTINCT logged_date)
             FROM cardio_logs
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // Count of cardio rows on or after $sinceDate. Used by the dashboard
    // cardio card ("X sessions this week").
    public static function countForUserSince(int $userId, string $sinceDate): int {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM cardio_logs
             WHERE user_id = ? AND logged_date >= ?'
        );
        $stmt->execute([$userId, $sinceDate]);
        return (int) $stmt->fetchColumn();
    }

    // Sum of duration_min on or after $sinceDate — feeds the dashboard
    // card's "X min this week" line.
    public static function totalMinutesForUserSince(int $userId, string $sinceDate): int {
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(duration_min), 0)
             FROM cardio_logs
             WHERE user_id = ? AND logged_date >= ?'
        );
        $stmt->execute([$userId, $sinceDate]);
        return (int) $stmt->fetchColumn();
    }

    // Distinct cardio-logged dates on or after $sinceDate. Used together
    // with strength-logged dates to compute "workouts this week" — the
    // weekly_workout_target progress bar counts any training day.
    public static function distinctDatesForUserSince(int $userId, string $sinceDate): array {
        $stmt = db()->prepare(
            'SELECT DISTINCT logged_date FROM cardio_logs
             WHERE user_id = ? AND logged_date >= ?'
        );
        $stmt->execute([$userId, $sinceDate]);
        return array_map(static fn(array $r): string => $r['logged_date'], $stmt->fetchAll());
    }

    // Most recent session — used by the dashboard card preview line
    // ("Last session: Walk · 30 min · moderate").
    public static function latestForUser(int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM cardio_logs
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
            'SELECT * FROM cardio_logs
             WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function delete(int $id, int $userId): void {
        $stmt = db()->prepare(
            'DELETE FROM cardio_logs
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
    }
}
