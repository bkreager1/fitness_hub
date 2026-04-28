-- ============================================================
-- Migration 004 — Rebuild strength_logs as one-row-per-lift
-- ============================================================
-- The original schema had separate bench_press / squat / deadlift
-- columns plus a 1RM-only weight. The new shape has one row per
-- lift attempt, with the specific lift, weight, and reps —
-- supporting different rep ranges and weight × reps logging.
--
-- Safe to run only if strength_logs has no rows yet. Verified
-- empty before authoring this migration.
--
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

DROP TABLE IF EXISTS strength_logs;

CREATE TABLE strength_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    lift_type   ENUM('bench', 'squat', 'deadlift') NOT NULL,
    weight      DECIMAL(6,2) NOT NULL,
    reps        TINYINT UNSIGNED NOT NULL,
    unit        ENUM('lbs', 'kg') NOT NULL DEFAULT 'lbs',
    logged_date DATE NOT NULL,
    notes       VARCHAR(300) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
