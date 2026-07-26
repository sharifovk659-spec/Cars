<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/settings.php';

function carRetentionMonths(): int
{
    return 3;
}

function carPurgeLockFile(): string
{
    return APP_ROOT . '/storage/car_purge_last_run.txt';
}

/** @return list<array{id: int, vin_code: string}> */
function findExpiredCars(PDO $pdo, ?int $months = null): array
{
    $months = $months ?? carRetentionMonths();
    $months = max(1, $months);

    $stmt = $pdo->query(
        'SELECT c.id, c.vin_code
         FROM cars c
         WHERE c.created_at < DATE_SUB(NOW(), INTERVAL ' . (int) $months . ' MONTH)
         ORDER BY c.id ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function deleteStoredCarImage(?string $path): void
{
    if ($path === null || $path === '') {
        return;
    }

    $fullPath = resolveImageFullPath($path);

    if ($fullPath !== null && is_file($fullPath)) {
        unlink($fullPath);
    }
}

function purgeCarById(PDO $pdo, int $carId): bool
{
    if ($carId <= 0) {
        return false;
    }

    $images = $pdo->prepare('SELECT image_path FROM car_images WHERE car_id = :car_id');
    $images->execute(['car_id' => $carId]);

    foreach ($images->fetchAll(PDO::FETCH_COLUMN) as $imagePath) {
        deleteStoredCarImage(is_string($imagePath) ? $imagePath : null);
    }

    $pdo->prepare('DELETE FROM car_images WHERE car_id = :car_id')->execute(['car_id' => $carId]);
    $delete = $pdo->prepare('DELETE FROM cars WHERE id = :id');
    $delete->execute(['id' => $carId]);

    return $delete->rowCount() > 0;
}

/**
 * @return array{deleted: int, vins: list<string>}
 */
function purgeExpiredCars(PDO $pdo, ?int $months = null): array
{
    $deleted = 0;
    $vins = [];

    foreach (findExpiredCars($pdo, $months) as $car) {
        $carId = (int) ($car['id'] ?? 0);
        $vin = (string) ($car['vin_code'] ?? '');

        if ($carId <= 0) {
            continue;
        }

        if (purgeCarById($pdo, $carId)) {
            $deleted++;
            $vins[] = $vin;
        }
    }

    if ($deleted > 0) {
        error_log('Car retention purge removed ' . $deleted . ' car(s): ' . implode(', ', $vins));
    }

    return [
        'deleted' => $deleted,
        'vins'    => $vins,
    ];
}

function maybePurgeExpiredCars(PDO $pdo): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $checkedAt = (int) ($_SESSION['car_purge_checked_at'] ?? 0);
        if ($checkedAt > 0 && (time() - $checkedAt) < 3600) {
            return;
        }
        $_SESSION['car_purge_checked_at'] = time();
    }

    $lockFile = carPurgeLockFile();
    $storageDir = dirname($lockFile);

    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    $lastRun = is_file($lockFile) ? (int) trim((string) file_get_contents($lockFile)) : 0;

    if ($lastRun > 0 && (time() - $lastRun) < 86400) {
        return;
    }

    purgeExpiredCars($pdo);
    file_put_contents($lockFile, (string) time());
}

function carRetentionCutoffDate(?int $months = null): string
{
    $months = $months ?? carRetentionMonths();

    return date('Y-m-d', strtotime('-' . max(1, $months) . ' months'));
}
