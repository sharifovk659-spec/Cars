<?php

declare(strict_types=1);

/**
 * Telegram Cars — пайвасти базаи маълумот бо PDO
 * Credentials: private/carsbot/database.php (production) ё config/database.local.php (local)
 */

require_once __DIR__ . '/bootstrap.php';

if (!defined('DB_HOST')) {
    $localFile = __DIR__ . '/database.local.php';

    if (is_file($localFile)) {
        require $localFile;
    }
}

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
    throw new RuntimeException(
        'Database config not found. Create config/database.local.php (local) '
        . 'or /home/u417315406/domains/inovaauto.com/private/carsbot/database.php (production).'
    );
}

if (!defined('DB_PORT')) {
    define('DB_PORT', '3306');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

/**
 * @return PDO
 */
function db(bool $reconnect = false): PDO
{
    static $pdo = null;

    if ($reconnect) {
        $pdo = null;
    }

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        // Keep idle daemon connections from going stale silently.
        $pdo->exec('SET SESSION wait_timeout=600');
    }

    return $pdo;
}

/**
 * Ensure PDO is alive (fixes "MySQL server has gone away" in long-running daemon).
 */
function dbEnsureConnected(): PDO
{
    try {
        $pdo = db();
        $pdo->query('SELECT 1');

        return $pdo;
    } catch (Throwable $e) {
        return db(true);
    }
}
