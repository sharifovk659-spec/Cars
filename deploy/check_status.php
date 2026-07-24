<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo 'APP_URL=' . APP_URL . PHP_EOL;
echo 'DEPLOY_PATH=' . APP_ROOT . PHP_EOL;

$tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'TABLES=' . implode(',', $tables) . PHP_EOL;

foreach (['admins', 'cars', 'car_images', 'settings', 'schema_migrations'] as $table) {
    if (in_array($table, $tables, true)) {
        $count = (int) db()->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        echo strtoupper($table) . '_COUNT=' . $count . PHP_EOL;
    }
}

$migrations = db()->query('SELECT migration FROM schema_migrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
echo 'MIGRATIONS=' . implode(',', $migrations) . PHP_EOL;

echo 'UPLOADS_WRITABLE=' . (is_writable(APP_ROOT . '/uploads/cars') ? 'yes' : 'no') . PHP_EOL;
echo 'STORAGE_WRITABLE=' . (is_writable(APP_ROOT . '/storage') ? 'yes' : 'no') . PHP_EOL;
