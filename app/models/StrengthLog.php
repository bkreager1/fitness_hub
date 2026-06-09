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

    public const ALLOWED_UNITS = ['lbs', 'kg'];

    // ---- Lift catalog (single source of truth) ----------------
    // Each entry is [label, category, featured, bodyweight]:
    //   label       human-facing name shown in views + the picker
    //   category    body-part group; drives the picker's <optgroup>
    //               and the canonical display ordering
    //   featured    true for the "big three" (bench/squat/deadlift)
    //               — these get a dashboard card row + goal bar; the
    //               rest are accessory lifts surfaced in a rollup
    //   bodyweight  true when there's no external load (pull-up,
    //               dips, crunch, hanging leg raise) — weight is
    //               optional and the chart plots reps for these
    //
    // ENUM keys mirror strength_logs.lift_type (migration 011). The
    // original bench/squat/deadlift keys are preserved so existing
    // rows + the users.target_*_kg goal columns still line up. Read
    // everything through the accessors below (labels(),
    // featuredLifts(), byCategory(), …) so a future move to a
    // user-defined `exercises` table stays mechanical — call sites
    // never hard-code lift keys.
    //
    // ('plank' is deliberately absent — it's time-based, not
    //  weight × reps, and needs a separate logging mode later.)
    public const LIFTS = [
        // Chest
        'bench'                 => ['Bench press',             'Chest',     true,  false],
        'incline_bench'         => ['Incline bench press',     'Chest',     false, false],
        'db_bench'              => ['Dumbbell bench press',    'Chest',     false, false],
        'chest_fly'             => ['Chest fly',               'Chest',     false, false],
        // Back
        'barbell_row'           => ['Barbell row',             'Back',      false, false],
        'lat_pulldown'          => ['Lat pulldown',            'Back',      false, false],
        'pull_up'               => ['Pull-up',                 'Back',      false, true],
        'seated_row'            => ['Seated cable row',        'Back',      false, false],
        'shrugs'                => ['Shrugs',                  'Back',      false, false],
        // Shoulders
        'ohp'                   => ['Overhead press',          'Shoulders', false, false],
        'db_shoulder_press'     => ['Dumbbell shoulder press', 'Shoulders', false, false],
        'lateral_raise'         => ['Lateral raise',           'Shoulders', false, false],
        'face_pull'             => ['Face pull',               'Shoulders', false, false],
        // Biceps
        'barbell_curl'          => ['Barbell curl',            'Biceps',    false, false],
        'db_curl'               => ['Dumbbell curl',           'Biceps',    false, false],
        'hammer_curl'           => ['Hammer curl',             'Biceps',    false, false],
        'preacher_curl'         => ['Preacher curl',           'Biceps',    false, false],
        'cable_curl'            => ['Cable curl',              'Biceps',    false, false],
        // Triceps
        'tricep_pushdown'       => ['Tricep pushdown',         'Triceps',   false, false],
        'skullcrusher'          => ['Skullcrusher',            'Triceps',   false, false],
        'overhead_extension'    => ['Overhead cable extension','Triceps',   false, false],
        'dips'                  => ['Dips',                    'Triceps',   false, true],
        // Legs
        'squat'                 => ['Squat',                   'Legs',      true,  false],
        'deadlift'              => ['Deadlift',                'Legs',      true,  false],
        'leg_press'             => ['Leg press',               'Legs',      false, false],
        'rdl'                   => ['Romanian deadlift',       'Legs',      false, false],
        'leg_curl'              => ['Leg curl',                'Legs',      false, false],
        'leg_extension'         => ['Leg extension',           'Legs',      false, false],
        'calf_raise'            => ['Calf raise',              'Legs',      false, false],
        'bulgarian_split_squat' => ['Bulgarian split squat',   'Legs',      false, false],
        // Core
        'crunch'                => ['Crunch',                  'Core',      false, true],
        'hanging_leg_raise'     => ['Hanging leg raise',       'Core',      false, true],
    ];

    // Tuple positions within a LIFTS entry.
    private const L_LABEL      = 0;
    private const L_CATEGORY   = 1;
    private const L_FEATURED   = 2;
    private const L_BODYWEIGHT = 3;

    // All valid lift_type keys, in catalog (display) order.
    public static function allowedKeys(): array {
        return array_keys(self::LIFTS);
    }

    // [key => label] map — the shape views expect as $liftLabels.
    public static function labels(): array {
        return array_map(
            static fn(array $l): string => $l[self::L_LABEL],
            self::LIFTS
        );
    }

    // Single label with a safe fallback for unknown keys.
    public static function label(string $key): string {
        return self::LIFTS[$key][self::L_LABEL] ?? $key;
    }

    // The "big three" keys (bench/squat/deadlift), in catalog order.
    // Drives the dashboard card rows + per-lift goal bars.
    public static function featuredLifts(): array {
        return array_keys(array_filter(
            self::LIFTS,
            static fn(array $l): bool => $l[self::L_FEATURED]
        ));
    }

    public static function isFeatured(string $key): bool {
        return (bool) (self::LIFTS[$key][self::L_FEATURED] ?? false);
    }

    // Bodyweight lifts log reps with optional added weight.
    public static function isBodyweight(string $key): bool {
        return (bool) (self::LIFTS[$key][self::L_BODYWEIGHT] ?? false);
    }

    // [category => [key => label, …], …], preserving catalog order.
    // Feeds the lift picker's grouped <optgroup> options.
    public static function byCategory(): array {
        $out = [];
        foreach (self::LIFTS as $key => $l) {
            $out[$l[self::L_CATEGORY]][$key] = $l[self::L_LABEL];
        }
        return $out;
    }

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
    //
    // $sinceDate (YYYY-MM-DD) limits the query to entries on or after
    // that date — used by the time-range filter on the strength page.
    // Pass null to fetch all entries.
    public static function forUser(int $userId, ?string $sinceDate = null): array {
        if ($sinceDate === null) {
            $stmt = db()->prepare(
                'SELECT * FROM strength_logs
                 WHERE user_id = ?
                 ORDER BY logged_date DESC, id DESC'
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = db()->prepare(
                'SELECT * FROM strength_logs
                 WHERE user_id = ? AND logged_date >= ?
                 ORDER BY logged_date DESC, id DESC'
            );
            $stmt->execute([$userId, $sinceDate]);
        }
        return $stmt->fetchAll();
    }

    // Total number of logged lift attempts (for profile summary).
    // One row = one lift x weight x reps entry, so this is a "sets" count.
    public static function countForUser(int $userId): int {
        $stmt = db()->prepare('SELECT COUNT(*) FROM strength_logs WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // Number of distinct days a user has logged any lift on. Used to
    // distinguish "no logs at all" from "no logs in this range" on
    // the strength history range filter.
    public static function countLoggedDaysForUser(int $userId): int {
        $stmt = db()->prepare(
            'SELECT COUNT(DISTINCT logged_date)
             FROM strength_logs
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // Count of lift rows on or after $sinceDate. Used by the dashboard
    // stat strip ("X lifts this week").
    public static function countForUserSince(int $userId, string $sinceDate): int {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM strength_logs
             WHERE user_id = ? AND logged_date >= ?'
        );
        $stmt->execute([$userId, $sinceDate]);
        return (int) $stmt->fetchColumn();
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
    // Returns [key => row|null] for every catalog lift, so callers
    // can index any lift without an isset() guard.
    public static function latestPerLiftForUser(int $userId): array {
        $out = array_fill_keys(self::allowedKeys(), null);

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
