-- ============================================================
-- Migration 001 — Add calorie_intake_logs table
-- ============================================================
-- Adds a new table for daily calorie intake logging. This is the
-- separate concern from calorie_logs (which holds target snapshots).
--
--   calorie_logs         → "what my targets are/were"  (occasional)
--   calorie_intake_logs  → "what I ate today"          (daily)
--
-- Purely additive: doesn't touch any existing table. Safe to run.
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

CREATE TABLE calorie_intake_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    calories    INT UNSIGNED NOT NULL,
    logged_date DATE NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- One row per user per day. Re-saving the same date upserts
    -- via INSERT ... ON DUPLICATE KEY UPDATE in the model.
    UNIQUE KEY uniq_user_date (user_id, logged_date),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
