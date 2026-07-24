<?php

declare(strict_types=1);

/**
 * Бехатар иҷро кардани migration-ҳо (schema.sql танҳо барои базаи холӣ).
 *
 * Usage:
 *   php deploy/migrate.php           — иҷро
 *   php deploy/migrate.php --dry-run — нақшаи иҷро
 */

$root = dirname(__DIR__);

require_once $root . '/config/app.php';
require_once $root . '/config/database.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

/** @var list<string> $migrations */
$migrations = [
    'schema_install.sql',
    'migration_admin_v1.sql',
    'migration_settings_v1.sql',
];

function migrateLog(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function migrateTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table'
    );
    $stmt->execute(['table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrateEnsureTrackingTable(PDO $pdo, bool $dryRun): void
{
    if (migrateTableExists($pdo, 'schema_migrations')) {
        return;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` VARCHAR(255) NOT NULL,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_schema_migrations_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    if ($dryRun) {
        migrateLog('[dry-run] CREATE TABLE schema_migrations');
        return;
    }

    $pdo->exec($sql);
    migrateLog('[ok] schema_migrations table ready');
}

function migrateApplied(PDO $pdo, string $name): bool
{
    if (!migrateTableExists($pdo, 'schema_migrations')) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration = :name');
    $stmt->execute(['name' => $name]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrateMarkApplied(PDO $pdo, string $name, bool $dryRun): void
{
    if ($dryRun) {
        migrateLog("[dry-run] RECORD migration: {$name}");
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:name)');
    $stmt->execute(['name' => $name]);
}

function migrateSplitSql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $delimiter = ';';
    $lines = preg_split('/\R/', $sql) ?: [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }

        if (preg_match('/^DELIMITER\s+(\S+)/i', $trimmed, $matches) === 1) {
            if ($buffer !== '') {
                $statements[] = trim($buffer);
                $buffer = '';
            }
            $delimiter = $matches[1];
            continue;
        }

        $buffer .= $line . "\n";

        if (str_ends_with(rtrim($line), $delimiter)) {
            $statement = rtrim($buffer);
            $statement = substr($statement, 0, -strlen($delimiter));
            $statement = trim($statement);

            if ($statement !== '') {
                $statements[] = $statement;
            }

            $buffer = '';
        }
    }

    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}

function migrateRunFile(PDO $pdo, string $path, bool $dryRun): void
{
    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException("Cannot read migration file: {$path}");
    }

    $sql = preg_replace('/^\s*USE\s+`[^`]+`\s*;\s*$/mi', '', $sql) ?? $sql;
    $statements = migrateSplitSql($sql);

    foreach ($statements as $statement) {
        if ($dryRun) {
            $preview = preg_replace('/\s+/', ' ', $statement) ?? $statement;
            $preview = substr($preview, 0, 120);
            migrateLog("[dry-run] SQL: {$preview}...");
            continue;
        }

        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            $code = (string) $e->getCode();
            $message = $e->getMessage();

            if (
                str_contains($message, 'Duplicate column')
                || str_contains($message, 'Duplicate key name')
                || str_contains($message, 'already exists')
            ) {
                migrateLog("[skip] {$message}");
                continue;
            }

            throw $e;
        }
    }
}

try {
    $pdo = db();
    migrateEnsureTrackingTable($pdo, $dryRun);

    $hasCoreTables = migrateTableExists($pdo, 'cars');

    foreach ($migrations as $migration) {
        if (migrateApplied($pdo, $migration)) {
            migrateLog("[skip] {$migration} (already applied)");
            continue;
        }

        if ($migration === 'schema_install.sql' && $hasCoreTables) {
            migrateLog('[skip] schema_install.sql (database already has tables — no reset)');
            migrateMarkApplied($pdo, $migration, $dryRun);
            continue;
        }

        $path = $root . '/database/' . $migration;

        if (!is_file($path)) {
            throw new RuntimeException("Migration file missing: {$path}");
        }

        migrateLog(($dryRun ? '[dry-run] ' : '[run] ') . $migration);
        migrateRunFile($pdo, $path, $dryRun);
        migrateMarkApplied($pdo, $migration, $dryRun);

        if ($migration === 'schema_install.sql') {
            $hasCoreTables = true;
        }
    }

    migrateLog($dryRun ? 'Dry-run complete.' : 'Migrations complete.');
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
