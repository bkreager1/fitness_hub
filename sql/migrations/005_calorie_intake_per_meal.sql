-- ============================================================
-- Migration 005 — Per-meal calorie intake
-- ============================================================
-- Switches calorie_intake_logs from "one row per user per day"
-- (upserted) to "many rows per user per day" (one per meal/entry),
-- with an optional free-form label like "Lunch" or "Pizza".
--
-- Three changes, done in a single ALTER for atomicity:
--   1. Add a standalone index on user_id. The composite unique
--      key was implicitly serving as the FK's required index;
--      once we drop it, the FK needs its own index to lean on.
--   2. Drop the (user_id, logged_date) UNIQUE constraint so a
--      user can have multiple entries on the same date.
--   3. Add a nullable `label` column for the optional meal name.
--
-- Existing rows stay valid as "the only entry for that day" — they
-- simply gain the ability to have siblings. No data is lost.
--
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

ALTER TABLE calorie_intake_logs
    ADD INDEX idx_user_id (user_id),
    DROP INDEX uniq_user_date,
    ADD COLUMN label VARCHAR(50) NULL AFTER calories;
