<?php
// ============================================================
// config/database.php
// Database connection + app-wide configuration constants
//
// Anywhere in the app you can call db() to get the shared PDO instance.
// ============================================================

// ----- App-wide constants --------------------------------------------
// Any of these can be overridden by config/database.local.php
// (loaded below, before these defaults fire).
if (is_file(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

// BASE_URL = the public URL path your app lives at.
// On XAMPP at http://localhost/fitness_hub/ this should be '/fitness_hub'
// When you deploy to Hostinger at the root of a domain, change to ''
defined('BASE_URL')  or define('BASE_URL',  '/fitness_hub');
defined('SITE_NAME') or define('SITE_NAME', 'Rock County Fitness Hub');

// ----- Database credentials ------------------------------------------
// XAMPP defaults: user=root, blank password, MySQL on localhost:3306.
// database.local.php (loaded above, gitignored) can override any of these.
defined('DB_HOST')    or define('DB_HOST',    'localhost');
defined('DB_NAME')    or define('DB_NAME',    'fitness_hub');
defined('DB_USER')    or define('DB_USER',    'root');
defined('DB_PASS')    or define('DB_PASS',    '');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

// ----- Filesystem paths ----------------------------------------------
// APP_ROOT = absolute path to the project root on disk
define('APP_ROOT',    dirname(__DIR__));
define('VIEW_PATH',   APP_ROOT . '/app/views');
define('UPLOAD_PATH', APP_ROOT . '/public/uploads');

// ----- PDO connection -------------------------------------------------
// Returns the shared PDO instance. First call creates it, later calls
// reuse the same connection — that's what the static keyword gives us.
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        // Data Source Name — tells PDO how to find our database
        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname='    . DB_NAME
             . ';charset='   . DB_CHARSET;

        $options = [
            // Throw an exception on any SQL error (makes bugs visible)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return rows as associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use REAL prepared statements (extra SQL-injection protection)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Friendly message if the DB is unreachable
            http_response_code(500);
            die('Database connection failed. '
              . 'Is XAMPP MySQL running? '
              . 'Check credentials in config/database.php.');
        }
    }

    return $pdo;
}
