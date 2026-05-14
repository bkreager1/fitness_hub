-- ============================================================
-- Migration 008 — Cardio tracker + weekly cadence goals
-- ============================================================
-- Two related additions that ship together:
--
--   1. Two new nullable goal columns on users:
--        weekly_workout_target  → days/week the user aims to train
--                                 (counts ANY logged session — strength
--                                 OR cardio — on a given date)
--        weekly_cardio_target   → cardio-specific sessions/week
--
--      Both TINYINT UNSIGNED, both NULL = "no goal set yet".
--      The dashboard hides the corresponding progress bar when NULL.
--
--   2. New cardio_logs table — one row per cardio session.
--      Multiple sessions per day are allowed (morning run + evening
--      walk are two rows), so no UNIQUE constraint on (user, date).
--
--      Fields:
--        cardio_type   required ENUM, eight choices
--        duration_min  required minutes (SMALLINT handles 18+ hours)
--        intensity     optional easy / moderate / hard
--        distance      optional DECIMAL(6,2) — paired with…
--        distance_unit optional ENUM('mi','km') — both NULL or both set,
--                      enforced at the form layer (not via CHECK to keep
--                      compatibility with older MySQL versions)
--
-- Purely additive. Existing data untouched.
--
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

ALTER TABLE users
    ADD COLUMN weekly_workout_target TINYINT UNSIGNED NULL AFTER target_deadlift_kg,
    ADD COLUMN weekly_cardio_target  TINYINT UNSIGNED NULL AFTER weekly_workout_target;

CREATE TABLE cardio_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    cardio_type   ENUM('walk','run','bike','elliptical','row','stairs','sport','other') NOT NULL,
    duration_min  SMALLINT UNSIGNED NOT NULL,
    intensity     ENUM('easy','moderate','hard') NULL,
    distance      DECIMAL(6,2) NULL,
    distance_unit ENUM('mi','km') NULL,
    logged_date   DATE NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, logged_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
