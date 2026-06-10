-- ============================================================
-- Migration 012 — Add a sets count to strength_logs
-- ============================================================
-- Each strength_logs row already captures weight × reps for one
-- entry. This adds a "sets" count so an entry can read "3 × 5 @
-- 225" (three sets of five) instead of forcing one row per set.
--
-- Defaults to 1, so every existing row stays valid and the quick
-- top-set logging flow is unchanged for anyone who ignores it.
-- Range (1–20) is enforced at the form layer; TINYINT is plenty.
--
-- Run in phpMyAdmin > fitness_hub > SQL tab — OR apply via the
-- app's own DB connection. (See migration 011's note: verify it
-- lands on the live `fitness_hub` DB, not a leftover check DB.)
-- ============================================================

ALTER TABLE strength_logs
    ADD COLUMN sets TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER reps;
