<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bot/helpers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ui.php';

/**
 * @return array<string, mixed>|null
 */
function adminCarListRow(PDO $pdo, int $carId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT c.id, c.vin_code, c.name, c.status, c.receive_location, c.receive_date, c.upload_date,
                c.upload_number, c.contact_name, c.contact_phone,
                (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id ORDER BY ci.sort_order ASC LIMIT 1) AS main_image,
                (SELECT COUNT(*) FROM car_images ci WHERE ci.car_id = c.id) AS image_count
         FROM cars c
         WHERE c.id = :id AND c.deleted_at IS NULL
         LIMIT 1"
    );
    $stmt->execute(['id' => $carId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * @return list<array<string, mixed>>
 */
function searchAdminCars(PDO $pdo, string $query, int $limit = 15): array
{
    $query = trim($query);
    if ($query === '' || $limit <= 0) {
        return [];
    }

    $cars = [];
    $seenIds = [];

    $exact = findCarBySearchQuery($query);
    if ($exact !== null) {
        $row = adminCarListRow($pdo, (int) $exact['id']);
        if ($row !== null) {
            $cars[] = $row;
            $seenIds[(int) $row['id']] = true;
        }
    }

    $remaining = $limit - count($cars);
    if ($remaining <= 0) {
        return $cars;
    }

    $like = '%' . $query . '%';
    $stmt = $pdo->prepare(
        "SELECT c.id, c.vin_code, c.name, c.status, c.receive_location, c.receive_date, c.upload_date,
                c.upload_number, c.contact_name, c.contact_phone,
                (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id ORDER BY ci.sort_order ASC LIMIT 1) AS main_image,
                (SELECT COUNT(*) FROM car_images ci WHERE ci.car_id = c.id) AS image_count
         FROM cars c
         WHERE c.deleted_at IS NULL
           AND (
                c.vin_code LIKE :like_vin
                OR c.name LIKE :like_name
                OR c.upload_number LIKE :like_upload
                OR c.contact_phone LIKE :like_phone
                OR c.contact_name LIKE :like_contact
           )
         ORDER BY c.created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':like_vin', $like);
    $stmt->bindValue(':like_name', $like);
    $stmt->bindValue(':like_upload', $like);
    $stmt->bindValue(':like_phone', $like);
    $stmt->bindValue(':like_contact', $like);
    $stmt->bindValue(':limit', $remaining, PDO::PARAM_INT);
    $stmt->execute();

    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];
        if (isset($seenIds[$id])) {
            continue;
        }

        $cars[] = $row;
        $seenIds[$id] = true;
    }

    return $cars;
}

/** @return array<string, mixed> */
function adminSearchCarPayload(array $car): array
{
    $id = (int) $car['id'];
    $imageUrl = carImageUrl($car['main_image'] ?? null);

    return [
        'id'              => $id,
        'vin_code'        => (string) $car['vin_code'],
        'name'            => (string) ($car['name'] ?? ''),
        'status'          => (string) ($car['status'] ?? 'available'),
        'status_label'    => carStatusLabel($car['status'] ?? 'available'),
        'status_class'    => carStatusClass($car['status'] ?? 'available'),
        'receive_display' => carReceiveDisplayText($car),
        'upload_date'     => formatDate($car['upload_date'] ?? null),
        'image_count'     => (int) ($car['image_count'] ?? 0),
        'main_image'      => $imageUrl,
        'contact_phone'   => (string) ($car['contact_phone'] ?? ''),
        'contact_name'    => (string) ($car['contact_name'] ?? ''),
        'contact_display' => (string) (($car['contact_phone'] ?? '') !== '' ? $car['contact_phone'] : (($car['contact_name'] ?? '') !== '' ? $car['contact_name'] : __('common.dash'))),
        'view_url'        => adminCarUrl('view.php', ['id' => $id]),
        'edit_url'        => adminCarUrl('edit.php', ['id' => $id]),
    ];
}

function renderDashboardSearchMobileCard(array $car): void
{
    $viewUrl = adminCarUrl('view.php', ['id' => (int) $car['id']]);
    ?>
    <article class="car-card dashboard-search-card glass">
        <a href="<?= e($viewUrl) ?>" class="car-card-top dashboard-search-card-link">
            <div class="car-card-photo">
                <?php if ($img = carImageUrl($car['main_image'] ?? null)): ?>
                    <img src="<?= e($img) ?>" alt="">
                <?php else: ?>
                    <span><?= e(__('dashboard.no_photo')) ?></span>
                <?php endif; ?>
            </div>
            <div class="dashboard-search-card-body">
                <h3><?= e($car['name']) ?></h3>
                <code><?= e($car['vin_code']) ?></code>
                <span class="badge <?= carStatusClass($car['status']) ?>"><?= e(carStatusLabel($car['status'])) ?></span>
            </div>
        </a>
        <dl class="car-card-meta dashboard-search-card-meta">
            <div><dt><?= e(__('dashboard.receive')) ?></dt><dd><?= e(carReceiveDisplayText($car)) ?></dd></div>
            <div><dt><?= e(__('dashboard.upload')) ?></dt><dd><?= e(formatDate($car['upload_date'] ?? null)) ?></dd></div>
            <div><dt><?= e(__('cars.contact')) ?></dt><dd><?= e($car['contact_phone'] ?: ($car['contact_name'] ?: __('common.dash'))) ?></dd></div>
            <div><dt><?= e(__('dashboard.photos_count')) ?></dt><dd><?= (int) ($car['image_count'] ?? 0) ?></dd></div>
        </dl>
        <a href="<?= e($viewUrl) ?>" class="btn-primary sm dashboard-search-open"><?= e(__('dashboard.open')) ?></a>
    </article>
    <?php
}

function renderDashboardRecentMobileCard(array $car): void
{
    $viewUrl = adminCarUrl('view.php', ['id' => (int) $car['id']]);
    ?>
    <article class="car-card glass">
        <a href="<?= e($viewUrl) ?>" class="car-card-top">
            <div class="car-card-photo">
                <?php if ($img = carImageUrl($car['main_image'] ?? null)): ?>
                    <img src="<?= e($img) ?>" alt="">
                <?php else: ?>
                    <span><?= e(__('dashboard.no_photo')) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <h3><?= e($car['name']) ?></h3>
                <code><?= e($car['vin_code']) ?></code>
                <span class="badge <?= carStatusClass($car['status']) ?>"><?= e(carStatusLabel($car['status'])) ?></span>
            </div>
        </a>
        <dl class="car-card-meta">
            <div><dt><?= e(__('dashboard.receive')) ?></dt><dd><?= e(carReceiveDisplayText($car)) ?></dd></div>
            <div><dt><?= e(__('dashboard.upload')) ?></dt><dd><?= e(formatDate($car['upload_date'] ?? null)) ?></dd></div>
            <div><dt><?= e(__('dashboard.photos_count')) ?></dt><dd><?= (int) ($car['image_count'] ?? 0) ?></dd></div>
            <div><dt><?= e(__('dashboard.status')) ?></dt><dd><?= e(carStatusLabel($car['status'])) ?></dd></div>
        </dl>
        <a href="<?= e($viewUrl) ?>" class="btn-primary sm dashboard-search-open"><?= e(__('dashboard.open')) ?></a>
    </article>
    <?php
}
