-- ============================================================
-- Migration 007 — Optional macros per meal
-- ============================================================
-- Adds three nullable macro columns to calorie_intake_logs:
--   protein_g, carbs_g, fat_g  (grams, SMALLINT, NULL allowed)
--
-- Purely additive. Existing meals stay valid with all three NULL.
-- Users who don't care about macros just leave the fields blank and
-- the dashboard's macro bars stay hidden. Users who fill some in get
-- a per-day total bar driven by an auto-computed target from their
-- active calorie goal (cut/maintain/bulk, standard 30/40/30-ish
-- splits — calculation lives in DashboardController, not the DB).
--
-- SMALLINT UNSIGNED gives us 0..65535 which is way past any sane
-- per-meal gram amount (a 1000-calorie ribeye is ≈80g protein).
--
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

ALTER TABLE calorie_intake_logs
    ADD COLUMN protein_g SMALLINT UNSIGNED NULL AFTER calories,
    ADD COLUMN carbs_g   SMALLINT UNSIGNED NULL AFTER protein_g,
    ADD COLUMN fat_g     SMALLINT UNSIGNED NULL AFTER carbs_g;
