-- ============================================================
-- Rock County Fitness Hub — Full Database Schema
-- ============================================================
-- This is the single-file install. Run the whole thing in:
--   phpMyAdmin  →  fitness_hub database  →  SQL tab  →  Go
-- The result matches what migrations 001 through 010 leave you
-- with after applying them in order. New deploys (Hostinger, a
-- fresh dev machine) should run THIS file and skip the
-- migrations folder. The migrations stay in /sql/migrations/
-- only as a history of how the schema evolved.
--
-- Charset is utf8mb4 / utf8mb4_unicode_ci everywhere so users
-- can put emoji in their notes or meal labels without surprises.
-- ============================================================

-- ----- Drop in FK-safe order ---------------------------------
-- Children first, parents last. Safe to re-run during development.
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS cardio_logs;
DROP TABLE IF EXISTS strength_logs;
DROP TABLE IF EXISTS weight_logs;
DROP TABLE IF EXISTS calorie_intake_logs;
DROP TABLE IF EXISTS calorie_logs;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS users;


-- ============================================================
-- USERS
-- ============================================================
-- One row per registered account. Holds the basics (name, email,
-- bcrypt password), the avatar filename, the active calorie goal,
-- the user's target weight + big-three PR targets (all canonical
-- kg, all nullable = "no goal set"), the weekly cadence goals,
-- and the email-verification triplet that drives the soft-
-- verification banner.
-- ============================================================
CREATE TABLE users (
    id                              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                            VARCHAR(100) NOT NULL,
    email                           VARCHAR(150) NOT NULL UNIQUE,

    -- Soft email verification. NULL = unverified (sitewide banner
    -- nudges the user). hash + expires_at are set when a token is
    -- issued and cleared once consumed.
    email_verified_at               TIMESTAMP NULL DEFAULT NULL,
    email_verification_hash         VARCHAR(64)  DEFAULT NULL,
    email_verification_expires_at   TIMESTAMP NULL DEFAULT NULL,

    password                        VARCHAR(255) NOT NULL,          -- bcrypt hash, never plain text
    profile_image_path              VARCHAR(300) DEFAULT NULL,      -- bare filename under /public/uploads/

    -- Active calorie target (which column of calorie_logs we
    -- compare today's intake against on the dashboard).
    current_goal                    ENUM('cut', 'maintain', 'bulk') NOT NULL DEFAULT 'maintain',

    -- Goal targets. All canonical kg, all nullable — blank means
    -- "no goal set", which hides the corresponding progress bar.
    target_weight_kg                DECIMAL(5, 2) DEFAULT NULL,
    target_bench_kg                 DECIMAL(6, 2) DEFAULT NULL,
    target_squat_kg                 DECIMAL(6, 2) DEFAULT NULL,
    target_deadlift_kg              DECIMAL(6, 2) DEFAULT NULL,

    -- Weekly cadence goals (1..7). NULL = no goal set.
    weekly_workout_target           TINYINT UNSIGNED DEFAULT NULL,
    weekly_cardio_target            TINYINT UNSIGNED DEFAULT NULL,

    created_at                      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- CONTACTS
-- ============================================================
-- Messages submitted via the Contact page form. Not tied to a
-- user — anonymous visitors can also submit.
-- ============================================================
CREATE TABLE contacts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(150) NOT NULL,
    message      TEXT         NOT NULL,
    submitted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- CALORIE LOGS (target snapshots)
-- ============================================================
-- Each row = a saved calorie-target calculation (BMR + activity
-- → maintenance/cutting/bulking). Users save a new row whenever
-- their stats change so we can show progress over time. The
-- DASHBOARD pulls the latest row and uses one of the three calorie
-- columns based on the user's current_goal.
-- ============================================================
CREATE TABLE calorie_logs (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id              INT UNSIGNED NOT NULL,
    age                  TINYINT UNSIGNED NOT NULL,
    gender               ENUM('male', 'female') NOT NULL,
    weight_kg            DECIMAL(5,2) NOT NULL,
    height_cm            DECIMAL(5,2) NOT NULL,
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

    INDEX user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- CALORIE INTAKE LOGS (per-meal entries)
-- ============================================================
-- One row per logged eating event. The day total is the SUM
-- across that user's rows for a given date — multiple rows per
-- (user, date) are not just allowed, they're the point.
-- `label` is a free-form name like "Lunch" or "Pizza" and the
-- view falls back to "Meal 1", "Meal 2"… if blank. Macros are
-- optional per row — the dashboard only renders the macro bars
-- when at least one of the day's rows has them filled.
-- ============================================================
CREATE TABLE calorie_intake_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    calories    INT UNSIGNED NOT NULL,
    protein_g   SMALLINT UNSIGNED DEFAULT NULL,
    carbs_g     SMALLINT UNSIGNED DEFAULT NULL,
    fat_g       SMALLINT UNSIGNED DEFAULT NULL,
    label       VARCHAR(50)  DEFAULT NULL,
    logged_date DATE         NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- WEIGHT LOGS
-- ============================================================
-- One row per weigh-in. Weight stored canonically in kg, with
-- the unit the user originally typed it in ('lbs' or 'kg') so
-- the table can render it back in their preferred unit. UNIQUE
-- key on (user, date) means saving the same date upserts the
-- existing row instead of creating a duplicate.
-- ============================================================
CREATE TABLE weight_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    weight_kg   DECIMAL(5,2) NOT NULL,
    unit        ENUM('kg', 'lbs') NOT NULL DEFAULT 'lbs',
    logged_date DATE NOT NULL,
    notes       VARCHAR(300) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_user_date (user_id, logged_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- STRENGTH LOGS (one row per lift attempt)
-- ============================================================
-- Multiple entries per (user, date) are allowed — strength
-- training is naturally more variable than weight or calories.
-- The dashboard chart filters by lift_type to draw three separate
-- lines, and uses Epley estimated 1RM (weight * (1 + reps/30))
-- as the Y-axis value so cross-rep-range comparisons line up.
-- ============================================================
CREATE TABLE strength_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    lift_type   ENUM('bench', 'squat', 'deadlift') NOT NULL,
    weight      DECIMAL(6,2) NOT NULL,
    reps        TINYINT UNSIGNED NOT NULL,
    unit        ENUM('lbs', 'kg') NOT NULL DEFAULT 'lbs',
    logged_date DATE NOT NULL,
    notes       VARCHAR(300) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- CARDIO LOGS (one row per session)
-- ============================================================
-- Multiple sessions per day are allowed (morning run + evening
-- walk are two rows), so no UNIQUE constraint on (user, date).
-- Distance + distance_unit are paired — both NULL or both set,
-- enforced at the form layer (no CHECK constraint so we stay
-- compatible with older MySQL versions on shared hosting).
-- ============================================================
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

    INDEX idx_user_date (user_id, logged_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- PASSWORD RESETS
-- ============================================================
-- One row per "forgot password" request. We store only the
-- SHA-256 hash of the token — the raw token goes out in the
-- email and is never persisted. Tokens are single-use (used_at
-- gets stamped on consumption) and expire after 1 hour.
-- ============================================================
CREATE TABLE password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_token_hash (token_hash),
    INDEX idx_user_id    (user_id),
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- LOGIN ATTEMPTS (rate-limit + audit)
-- ============================================================
-- AuthController::login() counts failed attempts from the same IP
-- in the last 15 minutes; 5+ failures = a generic "too many
-- attempts" message, enforced BEFORE the DB user lookup so a
-- flood gets cheap rejections. Email is stored for audit, not
-- used in the throttle calculation. Not tied to users via FK
-- because a brute-forcer often submits emails that don't exist.
-- ============================================================
CREATE TABLE login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,
    email        VARCHAR(255) NULL,
    succeeded    TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time    (ip, attempted_at),
    INDEX idx_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
