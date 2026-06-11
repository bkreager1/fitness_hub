<?php
// ============================================================
// app/models/Workout.php
// Saved workout templates — a named, ordered list of exercises
// (lift + optional target sets × reps). One row in `workouts`,
// many in `workout_exercises`.
//
// lift_type is a catalog key (string) the caller validates against
// StrengthLog::allowedKeys(); stored as VARCHAR (not an ENUM) so it
// never drifts from the lift catalog.
// ============================================================

class Workout {

    // All of a user's workouts, newest first, each with an
    // exercise_count for the list view.
    public static function forUser(int $userId): array {
        $stmt = db()->prepare(
            'SELECT w.*, COUNT(e.id) AS exercise_count
             FROM workouts w
             LEFT JOIN workout_exercises e ON e.workout_id = w.id
             WHERE w.user_id = ?
             GROUP BY w.id
             ORDER BY w.created_at DESC, w.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function countForUser(int $userId): int {
        $stmt = db()->prepare('SELECT COUNT(*) FROM workouts WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // A single workout scoped to its owner, or null.
    public static function find(int $id, int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM workouts WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Ordered exercises for a workout.
    public static function exercisesFor(int $workoutId): array {
        $stmt = db()->prepare(
            'SELECT * FROM workout_exercises
             WHERE workout_id = ?
             ORDER BY position ASC, id ASC'
        );
        $stmt->execute([$workoutId]);
        return $stmt->fetchAll();
    }

    // Every exercise across all of a user's workouts, grouped by
    // workout_id and ordered by position. One query feeds the list
    // view so it can render each template's lifts inline without an
    // N+1 (one query per card). Returns [workout_id => [rows…]].
    public static function exercisesForUser(int $userId): array {
        $stmt = db()->prepare(
            'SELECT e.*
             FROM workout_exercises e
             INNER JOIN workouts w ON w.id = e.workout_id
             WHERE w.user_id = ?
             ORDER BY e.workout_id ASC, e.position ASC, e.id ASC'
        );
        $stmt->execute([$userId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['workout_id']][] = $row;
        }
        return $out;
    }

    // Create a workout + its exercises in one transaction. $exercises
    // is an ordered list of ['lift_type', 'target_sets', 'target_reps']
    // (targets may be null). Caller validates lift_type + bounds.
    public static function create(int $userId, string $name, ?string $notes, array $exercises): int {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO workouts (user_id, name, notes) VALUES (?, ?, ?)'
            );
            $stmt->execute([$userId, $name, $notes]);
            $workoutId = (int) $pdo->lastInsertId();

            self::insertExercises($workoutId, $exercises);

            $pdo->commit();
            return $workoutId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // Update name/notes and replace the exercise list wholesale — the
    // simplest correct approach for a reorderable list. Scoped to
    // owner; caller has already confirmed ownership via find().
    public static function update(int $id, int $userId, string $name, ?string $notes, array $exercises): void {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE workouts SET name = ?, notes = ? WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$name, $notes, $id, $userId]);

            $del = $pdo->prepare('DELETE FROM workout_exercises WHERE workout_id = ?');
            $del->execute([$id]);

            self::insertExercises($id, $exercises);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id, int $userId): void {
        // workout_exercises cascade on delete; workout_sessions.workout_id
        // is SET NULL so past performed sessions survive.
        $stmt = db()->prepare('DELETE FROM workouts WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }

    // Bulk-insert the ordered exercise list. position = array index.
    private static function insertExercises(int $workoutId, array $exercises): void {
        if (!$exercises) return;
        $stmt = db()->prepare(
            'INSERT INTO workout_exercises
                (workout_id, lift_type, target_sets, target_reps, position)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach (array_values($exercises) as $pos => $ex) {
            $stmt->execute([
                $workoutId,
                $ex['lift_type'],
                $ex['target_sets'] ?? null,
                $ex['target_reps'] ?? null,
                $pos,
            ]);
        }
    }
}
