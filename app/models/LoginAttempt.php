<?php
// ============================================================
// app/models/LoginAttempt.php
// Records login attempts for brute-force throttling + audit.
//
// AuthController::login() calls recentFailuresByIp() before
// verifying the password and bails with a generic "too many
// attempts" message if the IP is over the threshold.
// ============================================================

class LoginAttempt {

    // 5 failures from the same IP within 15 minutes = lockout.
    public const MAX_FAILURES   = 5;
    public const WINDOW_SECONDS = 15 * 60;

    // Insert a row for one login attempt.
    public static function record(string $ip, ?string $email, bool $succeeded): void {
        $stmt = db()->prepare(
            'INSERT INTO login_attempts (ip, email, succeeded)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $ip,
            $email === '' ? null : $email,
            $succeeded ? 1 : 0,
        ]);
    }

    // Count failed attempts from $ip in the last $sinceSeconds.
    // A successful login DOESN'T reset the count automatically —
    // we just check failures since (NOW - window). After a single
    // success there'll still be failures in the window, but the
    // client is already logged in and won't hit /login again.
    public static function recentFailuresByIp(string $ip, int $sinceSeconds): int {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip = ?
               AND succeeded = 0
               AND attempted_at > (NOW() - INTERVAL ? SECOND)'
        );
        $stmt->execute([$ip, $sinceSeconds]);
        return (int) $stmt->fetchColumn();
    }
}
