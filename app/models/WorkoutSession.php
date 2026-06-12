<?php
// ============================================================
// app/models/WorkoutSession.php
// One performed workout session. The session row itself is thin —
// a name snapshot + date — and the actual lifts live in
// strength_logs with session_id pointing back here, so every chart,
// PR, and history view picks them up with zero extra wiring.
//
// name is snapshotted from the workout template at log time (and
// workout_id is ON DELETE SET NULL), so past sessions survive the
// template being renamed or deleted.
// ============================================================

class WorkoutSession {

    // A single session scoped to its owner, or null.
    public static function find(int $id, int $userId): ?array {
        $stmt = db()->prepare(
            'SELECT * FROM workout_sessions
             WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Total sessions ever logged — lets the list header say
    // "Showing 15 of 23" instead of silently truncating.
    public static function countForUser(int $userId): int {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM workout_sessions WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // Most recent sessions for a user, newest session date first.
    // $limit is int-clamped and inlined because LIMIT placeholders
    // are unreliable across PDO emulation modes.
    public static function forUser(int $userId, int $limit = 15): array {
        $limit = max(1, min(50, $limit));
        $stmt = db()->prepare(
            "SELECT * FROM workout_sessions
             WHERE user_id = ?
             ORDER BY logged_date DESC, id DESC
             LIMIT $limit"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // The logged lifts for a set of sessions, grouped by session id
    // and in insertion order (= the order the template listed them).
    // One query feeds the whole "Recent sessions" list — no N+1.
    // Returns [session_id => [strength_logs rows…]].
    public static function liftsForSessions(array $sessionIds): array {
        $ids = array_values(array_filter(array_map('intval', $sessionIds)));
        if (!$ids) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT * FROM strength_logs
             WHERE session_id IN ($placeholders)
             ORDER BY id ASC"
        );
        $stmt->execute($ids);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['session_id']][] = $row;
        }
        return $out;
    }

    // Create a session plus one strength_logs row per performed lift,
    // in one transaction. $lifts rows are pre-validated
    // ['lift_type', 'weight' (float|null), 'reps', 'sets']; the
    // session's unit + date apply to every row.
    public static function createWithLifts(
        int $userId,
        ?int $workoutId,
        string $name,
        string $loggedDate,
        string $unit,
        array $lifts
    ): int {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO workout_sessions (user_id, workout_id, name, logged_date)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $workoutId, $name, $loggedDate]);
            $sessionId = (int) $pdo->lastInsertId();

            foreach ($lifts as $lift) {
                StrengthLog::create($userId, [
                    'lift_type'   => $lift['lift_type'],
                    'weight'      => $lift['weight'],
                    'reps'        => $lift['reps'],
                    'sets'        => $lift['sets'],
                    'unit'        => $unit,
                    'logged_date' => $loggedDate,
                    'notes'       => null,
                    'session_id'  => $sessionId,
                ]);
            }

            $pdo->commit();
            return $sessionId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // Delete a session AND its logged lifts. The FK alone would only
    // SET NULL (a safety net for any other deletion path) — but when
    // the user deletes a session they mean "this didn't happen," so
    // the lifts are removed explicitly. Scoped to owner throughout.
    public static function delete(int $id, int $userId): void {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'DELETE FROM strength_logs WHERE session_id = ? AND user_id = ?'
            );
            $stmt->execute([$id, $userId]);

            $stmt = $pdo->prepare(
                'DELETE FROM workout_sessions WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$id, $userId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
