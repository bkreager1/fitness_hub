-- ============================================================
-- Migration 010 — Soft email verification
-- ============================================================
-- Adds three nullable columns to users so we can prove the
-- person who typed the email at signup actually owns the inbox:
--
--   email_verified_at              when the user clicked their
--                                  verification link. NULL = pending.
--   email_verification_hash        SHA-256 of the raw token mailed
--                                  to the user. We never store the
--                                  raw token, only its hash.
--   email_verification_expires_at  24h from issue time. Past this,
--                                  the link is dead and the user
--                                  must hit "Resend verification".
--
-- All existing rows are grandfathered as verified (no surprise
-- banner for accounts that pre-date this feature).
--
-- "Soft" because login still works without verification — a
-- persistent banner nudges the user, but nothing is blocked.
--
-- Run in phpMyAdmin > fitness_hub > SQL tab.
-- ============================================================

ALTER TABLE users
    ADD COLUMN email_verified_at             TIMESTAMP NULL DEFAULT NULL AFTER email,
    ADD COLUMN email_verification_hash       VARCHAR(64)  NULL          AFTER email_verified_at,
    ADD COLUMN email_verification_expires_at TIMESTAMP    NULL DEFAULT NULL AFTER email_verification_hash;

-- Mark every existing account as verified — they trust-on-first-use
-- under the old flow.
UPDATE users
SET email_verified_at = created_at
WHERE email_verified_at IS NULL;
