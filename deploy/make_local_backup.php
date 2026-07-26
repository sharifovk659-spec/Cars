<?php

declare(strict_types=1);

/**
 * Local backup of project files + database (no secrets printed).
 * Usage: php deploy/make_local_backup.php
 */

$root = dirname(__DIR__);
$stamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'pre_search_filters_' . $stamp;

if (!is_dir($backupRoot) && !mkdir($backupRoot, 0755, true) && !is_dir($backupRoot)) {
    fwrite(STDERR, "Cannot create backup dir\n");
    exit(1);
}

$filesDir = $backupRoot . DIRECTORY_SEPARATOR . 'files';
$dbFile = $backupRoot . DIRECTORY_SEPARATOR . 'database.sql';
$metaFile = $backupRoot . DIRECTORY_SEPARATOR . 'meta.txt';

if (!is_dir($filesDir) && !mkdir($filesDir, 0755, true) && !is_dir($filesDir)) {
    fwrite(STDERR, "Cannot create files dir\n");
    exit(1);
}

$excludeNames = [
    '.git' => true,
    'backups' => true,
    'node_modules' => true,
    'vendor' => true,
    '.idea' => true,
    '.vscode' => true,
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$copied = 0;
foreach ($iterator as $item) {
    /** @var SplFileInfo $item */
    $path = $item->getPathname();
    $relative = substr($path, strlen($root) + 1);
    $parts = preg_split('#[\\\\/]#', $relative) ?: [];

    foreach ($parts as $part) {
        if (isset($excludeNames[$part])) {
            continue 2;
        }
    }

    // Skip large generated caches
    if (str_starts_with(str_replace('\\', '/', $relative), 'uploads/cache/')) {
        continue;
    }

    $target = $filesDir . DIRECTORY_SEPARATOR . $relative;
    if ($item->isDir()) {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }
        continue;
    }

    $parent = dirname($target);
    if (!is_dir($parent)) {
        mkdir($parent, 0755, true);
    }
    if (@copy($path, $target)) {
        $copied++;
    }
}

require_once $root . '/config/database.php';

$host = DB_HOST;
$port = defined('DB_PORT') ? (string) DB_PORT : '3306';
$name = DB_NAME;
$user = DB_USER;
$pass = defined('DB_PASS') ? (string) DB_PASS : '';

$mysqldump = null;
foreach (['mysqldump', 'C:\\xampp\\mysql\\bin\\mysqldump.exe', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $candidate) {
    if ($candidate === 'mysqldump') {
        $which = stripos(PHP_OS_FAMILY, 'Windows') !== false ? 'where mysqldump' : 'command -v mysqldump';
        $out = [];
        @exec($which, $out, $code);
        if ($code === 0 && !empty($out[0]) && is_file(trim($out[0]))) {
            $mysqldump = trim($out[0]);
            break;
        }
        continue;
    }
    if (is_file($candidate)) {
        $mysqldump = $candidate;
        break;
    }
}

$dbOk = false;
$dbNote = 'mysqldump not found';

if ($mysqldump !== null) {
    $cmd = escapeshellarg($mysqldump)
        . ' --host=' . escapeshellarg($host)
        . ' --port=' . escapeshellarg($port)
        . ' --user=' . escapeshellarg($user)
        . ' --single-transaction --routines --triggers --default-character-set=utf8mb4 '
        . escapeshellarg($name);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $dbFile, 'w'],
        2 => ['pipe', 'w'],
    ];
    $env = $_ENV;
    $env['MYSQL_PWD'] = $pass;
    $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
    if (is_resource($proc)) {
        fclose($pipes[0]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        $dbOk = $code === 0 && is_file($dbFile) && filesize($dbFile) > 0;
        $dbNote = $dbOk ? 'ok' : ('failed: ' . trim((string) $err));
        if (!$dbOk && is_file($dbFile)) {
            @unlink($dbFile);
        }
    }
}

if (!$dbOk) {
    // Fallback: PDO dump of structure+data for main tables (no password printed)
    try {
        $pdo = db();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $fh = fopen($dbFile, 'wb');
        if ($fh === false) {
            throw new RuntimeException('Cannot write SQL file');
        }
        fwrite($fh, "-- Telegram Cars backup {$stamp}\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($tables as $table) {
            $table = (string) $table;
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_NUM);
            fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($fh, ($create[1] ?? '') . ";\n\n");
            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_map(static fn ($c) => '`' . str_replace('`', '``', (string) $c) . '`', array_keys($row));
                $vals = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = $pdo->quote((string) $value);
                    }
                }
                fwrite($fh, 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        $dbOk = true;
        $dbNote = 'pdo-fallback';
    } catch (Throwable $e) {
        $dbNote = 'pdo-failed';
    }
}

file_put_contents(
    $metaFile,
    "stamp={$stamp}\nfiles_copied={$copied}\ndb={$dbNote}\nroot={$root}\n"
);

echo $backupRoot . PHP_EOL;
echo 'files=' . $copied . PHP_EOL;
echo 'db=' . ($dbOk ? 'ok' : 'fail') . PHP_EOL;
exit($dbOk ? 0 : 2);
