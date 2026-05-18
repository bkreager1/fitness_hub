<?php
// ============================================================
// app/services/Mailer.php
// Tiny transactional-email helper.
//
// In dev (APP_ENV !== 'prod') every message is appended to
// storage/mail.log instead of being delivered. That keeps the
// existing "open the link in the log file" workflow working
// for password reset and verification testing.
//
// In prod we use PHP's native mail() function — Hostinger has
// sendmail wired up by default on shared plans, so this works
// without extra setup. If you ever need a proper SMTP backend
// (DKIM/SPF, retry, etc.), swap sendMail() out for PHPMailer.
//
// MAIL_FROM can be overridden in config/database.local.php.
// ============================================================

class Mailer {

    private const DEV_LOG = 'mail.log';

    // Send a transactional message. Returns true on success.
    public static function send(string $to, string $subject, string $body): bool {
        return APP_ENV === 'prod'
            ? self::sendMail($to, $subject, $body)
            : self::logToFile($to, $subject, $body);
    }

    // Dev: append to storage/mail.log so the dev can copy-paste the
    // link (verification, password reset, etc.) and follow the flow.
    private static function logToFile(string $to, string $subject, string $body): bool {
        $dir = APP_ROOT . '/storage';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $entry = sprintf(
            "[%s] To: %s\nSubject: %s\n\n%s\n\n----\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $body
        );
        return (bool) @file_put_contents(
            $dir . '/' . self::DEV_LOG,
            $entry,
            FILE_APPEND
        );
    }

    // Prod: hand off to PHP's mail() — Hostinger's sendmail catches it.
    private static function sendMail(string $to, string $subject, string $body): bool {
        $headers = [
            'From: ' . self::fromAddress(),
            'Reply-To: ' . self::fromAddress(),
            'Content-Type: text/plain; charset=UTF-8',
            'MIME-Version: 1.0',
            'X-Mailer: PHP/' . phpversion(),
        ];
        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    // From-address — override via define('MAIL_FROM', 'no-reply@yourdomain')
    // in config/database.local.php. Falls back to a host-based default
    // so it works out of the box on Hostinger.
    private static function fromAddress(): string {
        if (defined('MAIL_FROM') && MAIL_FROM !== '') {
            return MAIL_FROM;
        }
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        return 'no-reply@' . $host;
    }
}
