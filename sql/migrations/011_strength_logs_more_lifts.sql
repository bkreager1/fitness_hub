-- ============================================================
-- Migration 011 — Expand strength_logs beyond the big three
-- ============================================================
-- Phase 7 shipped strength tracking as bench / squat / deadlift
-- only. This widens lift_type into a full ~30-lift catalog grouped
-- by body part (chest / back / shoulders / biceps / triceps /
-- legs / core) so users can log accessory work, not just the
-- power lifts.
--
-- Two changes, both backward-compatible:
--
--   1. lift_type ENUM widened. The original three values
--      ('bench','squat','deadlift') keep their exact keys and
--      positions, so every existing row stays valid and the
--      dashboard's "featured" trio is unaffected.
--
--   2. weight made NULLable. Most lifts are still weight x reps,
--      but a handful are bodyweight (pull-up, dips, crunch,
--      hanging leg raise) where there's no external load to enter.
--      A NULL weight = "bodyweight"; the app logs reps and treats
--      weight as optional for those lifts. Existing rows all have
--      a weight, so loosening NOT NULL -> NULL touches no data.
--
-- The single source of truth for which keys exist, their labels,
-- categories, and which are featured / bodyweight lives in PHP
-- (StrengthLog::LIFTS). Keep this ENUM list in sync with it.
--
-- NOTE: 'plank' is intentionally NOT included yet — it's measured
-- in time held, not weight x reps, so it needs a separate logging
-- mode (a later pass).
--
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

ALTER TABLE strength_logs
    MODIFY COLUMN lift_type ENUM(
        -- Chest
        'bench', 'incline_bench', 'db_bench', 'chest_fly',
        -- Back
        'barbell_row', 'lat_pulldown', 'pull_up', 'seated_row', 'shrugs',
        -- Shoulders
        'ohp', 'db_shoulder_press', 'lateral_raise', 'face_pull',
        -- Biceps
        'barbell_curl', 'db_curl', 'hammer_curl', 'preacher_curl', 'cable_curl',
        -- Triceps
        'tricep_pushdown', 'skullcrusher', 'overhead_extension', 'dips',
        -- Legs
        'squat', 'deadlift', 'leg_press', 'rdl', 'leg_curl',
        'leg_extension', 'calf_raise', 'bulgarian_split_squat',
        -- Core
        'crunch', 'hanging_leg_raise'
    ) NOT NULL,
    MODIFY COLUMN weight DECIMAL(6,2) NULL;
