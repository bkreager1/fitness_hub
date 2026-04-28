-- ============================================================
-- Migration 003 — Enforce one weight log per (user, date)
-- ============================================================
-- Adds a UNIQUE constraint so re-saving the same date upserts via
-- INSERT ... ON DUPLICATE KEY UPDATE in the model. Matches the
-- behavior of calorie_intake_logs — one row per day per user.
--
-- Safe to run only if there are no duplicate (user_id, logged_date)
-- pairs already in weight_logs. Phase 7 weight-tracker work is fresh,
-- so the table is empty at the time of this migration.
--
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

ALTER TABLE weight_logs
    ADD UNIQUE KEY uniq_user_date (user_id, logged_date);
