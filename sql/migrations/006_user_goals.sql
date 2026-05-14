-- ============================================================
-- Migration 006 — User goals (target weight + target lifts)
-- ============================================================
-- Adds four nullable goal columns to the users table:
--   target_weight_kg     canonical kg, single source of truth
--   target_bench_kg
--   target_squat_kg
--   target_deadlift_kg
--
-- All four are stored canonically in kg (matching weight_logs
-- and strength_logs' storage). The view layer converts to the
-- user's preferred display unit (lbs or kg) when rendering.
--
-- NULL means "no goal set yet" — the dashboard hides the
-- corresponding goal-progress bar in that case. Existing users
-- start with all four NULL and can opt in via the new "Goals"
-- section on the profile page.
--
-- DECIMAL precision picks:
--   target_weight_kg     (5, 2)  →  up to 999.99 kg  (≈2,204 lb)
--   target_*_kg          (6, 2)  →  up to 9999.99 kg (way over any human PR)
--
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

ALTER TABLE users
    ADD COLUMN target_weight_kg   DECIMAL(5, 2) NULL AFTER current_goal,
    ADD COLUMN target_bench_kg    DECIMAL(6, 2) NULL AFTER target_weight_kg,
    ADD COLUMN target_squat_kg    DECIMAL(6, 2) NULL AFTER target_bench_kg,
    ADD COLUMN target_deadlift_kg DECIMAL(6, 2) NULL AFTER target_squat_kg;
