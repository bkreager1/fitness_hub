-- ============================================================
-- Migration 009 — Login attempt log (rate-limit + audit)
-- ============================================================
-- Records every login attempt so we can throttle brute force.
--
-- AuthController::login() consults this table before verifying the
-- password: if the client IP has failed 5+ times in the last 15
-- minutes, we short-circuit with the generic "too many attempts"
-- message — without ever revealing whether the email exists.
--
-- Rows are kept indefinitely (small footprint, useful audit trail).
-- A future janitor cron can prune anything older than 30 days.
--
-- Fields:
--   ip            IPv4 or IPv6 string (VARCHAR(45) holds both)
--   email         what was submitted — NULL if the form was blank.
--                 Stored for audit only; throttle is by IP.
--   succeeded     0 = bad credentials, 1 = signed in successfully
--   attempted_at  defaults to NOW(), indexed for time-window queries
--
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

CREATE TABLE login_attempts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip            VARCHAR(45) NOT NULL,
    email         VARCHAR(255) NULL,
    succeeded     TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time    (ip, attempted_at),
    INDEX idx_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
