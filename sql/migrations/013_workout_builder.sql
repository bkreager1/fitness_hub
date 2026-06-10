-- ============================================================
-- Migration 013 — Workout builder (templates + sessions)
-- ============================================================
-- Three new tables plus one link column:
--
--   workouts            saved templates ("Push Day")
--   workout_exercises   ordered lines in a template (lift + target
--                       sets×reps), lift_type is a catalog key
--                       (VARCHAR, validated in PHP against
--                       StrengthLog::allowedKeys() — NOT an ENUM, so
--                       it never drifts from the lift catalog and is
--                       ready for user-defined exercises later)
--   workout_sessions    one performed session (from a template or
--                       ad-hoc); name is a snapshot of the workout
--                       name so it survives the template being edited
--                       or deleted
--
--   strength_logs.session_id   links a logged set to its session.
--                       ON DELETE SET NULL: deleting a session never
--                       destroys the logged lifts (they stay in
--                       history, just ungrouped). The app's
--                       "delete session" action removes the children
--                       explicitly when the user asks for it.
--
-- workout_sessions.workout_id is also ON DELETE SET NULL so past
-- sessions outlive the template they came from.
--
-- Purely additive. Existing data untouched.
--
-- Run in phpMyAdmin > fitness_hub > SQL tab — OR apply via the app's
-- own DB connection (see migration 011's note about the leftover
-- check DB).
-- ============================================================

CREATE TABLE workouts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(80) NOT NULL,
    notes       VARCHAR(300) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_exercises (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_id  INT UNSIGNED NOT NULL,
    lift_type   VARCHAR(32) NOT NULL,
    target_sets TINYINT UNSIGNED DEFAULT NULL,
    target_reps TINYINT UNSIGNED DEFAULT NULL,
    position    SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    INDEX idx_workout (workout_id),
    FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_sessions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    workout_id  INT UNSIGNED DEFAULT NULL,
    name        VARCHAR(80) NOT NULL,
    logged_date DATE NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_date (user_id, logged_date),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (workout_id) REFERENCES workouts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE strength_logs
    ADD COLUMN session_id INT UNSIGNED DEFAULT NULL AFTER notes,
    ADD INDEX idx_session (session_id),
    ADD FOREIGN KEY (session_id) REFERENCES workout_sessions(id) ON DELETE SET NULL;
