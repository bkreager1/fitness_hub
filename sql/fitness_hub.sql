-- ============================================================
-- Rock County Fitness Hub — Full Database Schema
-- Run this entire file in phpMyAdmin > fitness_hub > SQL tab
-- ============================================================

-- Drop tables if they exist (safe to re-run during development)
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS strength_logs;
DROP TABLE IF EXISTS weight_logs;
DROP TABLE IF EXISTS calorie_logs;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS users;

-- ============================================================
-- USERS TABLE
-- Stores registered user accounts
-- ============================================================
CREATE TABLE users (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name               VARCHAR(100) NOT NULL,
    email              VARCHAR(150) NOT NULL UNIQUE,
    password           VARCHAR(255) NOT NULL,          -- bcrypt hash, never plain text
    profile_image_path VARCHAR(300) DEFAULT NULL,      -- path like /public/uploads/abc.jpg
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CALORIE LOGS TABLE
-- Each row = one calorie calculation saved by a user
-- Users can save multiple over time to track changes
-- ============================================================
CREATE TABLE calorie_logs (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id              INT UNSIGNED NOT NULL,
    age                  TINYINT UNSIGNED NOT NULL,
    gender               ENUM('male', 'female') NOT NULL,
    weight_kg            DECIMAL(5,2) NOT NULL,       -- e.g. 82.50
    height_cm            DECIMAL(5,2) NOT NULL,       -- e.g. 175.00
    activity_level       ENUM(
                            'sedentary',
                            'lightly_active',
                            'moderately_active',
                            'very_active',
                            'extra_active'
                         ) NOT NULL,
    maintenance_calories INT UNSIGNED NOT NULL,
    cutting_calories     INT UNSIGNED NOT NULL,
    bulking_calories     INT UNSIGNED NOT NULL,
    logged_date          DATE NOT NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WEIGHT LOGS TABLE
-- Each row = one weigh-in entry by a user
-- Full history — one row per date
-- ============================================================
CREATE TABLE weight_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    weight_kg   DECIMAL(5,2) NOT NULL,
    unit        ENUM('kg', 'lbs') NOT NULL DEFAULT 'lbs',
    logged_date DATE NOT NULL,
    notes       VARCHAR(300) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STRENGTH LOGS TABLE
-- Each row = one strength session log (bench / squat / deadlift)
-- All three lifts in one row per session date
-- ============================================================
CREATE TABLE strength_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    bench_press DECIMAL(6,2) DEFAULT NULL,   -- 1-rep max, nullable
    squat       DECIMAL(6,2) DEFAULT NULL,
    deadlift    DECIMAL(6,2) DEFAULT NULL,
    unit        ENUM('lbs', 'kg') NOT NULL DEFAULT 'lbs',
    logged_date DATE NOT NULL,
    notes       VARCHAR(300) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONTACTS TABLE
-- Stores messages submitted via the Contact page form
-- ============================================================
CREATE TABLE contacts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(150) NOT NULL,
    message      TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PASSWORD RESETS TABLE
-- Stores password reset tokens for forgot-password flow
-- ============================================================
CREATE TABLE password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_id (user_id),
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;