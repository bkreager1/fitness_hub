<?php
// ============================================================
// app/models/PasswordReset.php
// Database logic for the password_resets table.
//
// Security notes:
// - We never store the raw token, only a SHA-256 hash.
// - Tokens expire after TOKEN_LIFETIME seconds.
// - Tokens are single-use: once consumed, used_at is filled in.
// ============================================================

class PasswordReset {

    // Reset links are valid for 1 hour from issue time.
    private const TOKEN_LIFETIME = 3600;

    // Store a new token for a user. We hash the raw token before inserting
    // so the DB never sees the actual secret the user will click.
    public static function create(int $userId, string $rawToken): void {
        $hash    = hash('sha256', $rawToken);
        $expires = date('Y-m-d H:i:s', time() + self::TOKEN_LIFETIME);

        $stmt = db()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $hash, $expires]);
    }

    // Look up a token that is: not used, not expired, and whose hash matches.
    // Returns the row (id, user_id, ...) or null.
    public static function findValidToken(string $rawToken): ?array {
        $hash = hash('sha256', $rawToken);

        $stmt = db()->prepare(
            'SELECT * FROM password_resets
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Mark a specific token as consumed. Call this after a successful reset.
    public static function markAsUsed(int $id): void {
        $stmt = db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    // Burn every outstanding token for this user. Called before issuing a new
    // one (so only the latest email works) and after a successful reset.
    public static function invalidateAllForUser(int $userId): void {
        $stmt = db()->prepare(
            'UPDATE password_resets
                SET used_at = NOW()
              WHERE user_id = ?
                AND used_at IS NULL'
        );
        $stmt->execute([$userId]);
    }
}
