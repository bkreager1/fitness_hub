-- ============================================================
-- Migration 002 — Add current_goal to users
-- ============================================================
-- Stores the user's active calorie goal (cut / maintain / bulk).
-- Drives the intake hint copy, the history table comparison column,
-- and which target line is highlighted on the chart.
--
-- Purely additive: existing users default to 'maintain'.
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

ALTER TABLE users
    ADD COLUMN current_goal ENUM('cut', 'maintain', 'bulk')
        NOT NULL DEFAULT 'maintain'
        AFTER profile_image_path;
