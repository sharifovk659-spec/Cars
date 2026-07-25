<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/admin/includes/auth.php';

$rows = db()->query('SELECT id, username, email, is_active FROM admins ORDER BY id ASC')->fetchAll();
echo 'Admins: ' . count($rows) . PHP_EOL;

foreach ($rows as $row) {
    echo sprintf(
        "#%d username=%s email=%s active=%s\n",
        (int) $row['id'],
        (string) $row['username'],
        (string) $row['email'],
        (string) $row['is_active']
    );
}

$result = loginAdmin('admin', 'admin123', false);
echo 'loginAdmin(admin): ' . (($result['success'] ?? false) ? 'OK' : 'FAIL') . PHP_EOL;

if (!($result['success'] ?? false)) {
    echo 'Error: ' . ($result['error'] ?? 'unknown') . PHP_EOL;
    exit(1);
}

exit(0);
