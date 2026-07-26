<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bot/helpers.php';
require_once __DIR__ . '/../../includes/image_optimize.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ui.php';

/** @return list<string> */
function adminSearchTypes(): array
{
    return ['vin', 'digits', 'model', 'phone'];
}

function adminSearchTypeLabel(string $type): string
{
    return match ($type) {
        'vin'    => __('dashboard.search_type_vin'),
        'digits' => __('dashboard.search_type_digits'),
        'model'  => __('dashboard.search_type_model'),
        'phone'  => __('dashboard.search_type_phone'),
        default  => __('dashboard.search'),
    };
}

function adminSearchPlaceholder(string $type): string
{
    return match ($type) {
        'vin'    => __('dashboard.search_ph_vin'),
        'digits' => __('dashboard.search_ph_digits'),
        'model'  => __('dashboard.search_ph_model'),
        'phone'  => __('dashboard.search_ph_phone'),
        default  => __('dashboard.search_placeholder'),
    };
}

function normalizeAdminPhoneQuery(string $query): string
{
    return preg_replace('/[\s()\-]+/u', '', trim($query)) ?? '';
}

/**
 * @return array{ok:bool,type:string,query:string,error:?string}
 */
function prepareAdminSearchQuery(string $type, string $rawQuery): array
{
    $type = in_array($type, adminSearchTypes(), true) ? $type : 'vin';
    $query = trim($rawQuery);

    if ($query === '') {
        return [
            'ok'    => false,
            'type'  => $type,
            'query' => '',
            'error' => 'empty',
        ];
    }

    if ($type === 'vin') {
        $query = strtoupper($query);
        if (mb_strlen($query) < 2) {
            return ['ok' => false, 'type' => $type, 'query' => $query, 'error' => 'short'];
        }
    }

    if ($type === 'digits') {
        $query = preg_replace('/\D+/', '', $query) ?? '';
        if ($query === '') {
            return ['ok' => false, 'type' => $type, 'query' => '', 'error' => 'digits_only'];
        }
        if (strlen($query) < 4) {
            return ['ok' => false, 'type' => $type, 'query' => $query, 'error' => 'digits_short'];
        }
        if (strlen($query) > 6) {
            $query = substr($query, -6);
        }
    }

    if ($type === 'model') {
        if (mb_strlen($query) < 2) {
            return ['ok' => false, 'type' => $type, 'query' => $query, 'error' => 'short'];
        }
    }

    if ($type === 'phone') {
        $query = normalizeAdminPhoneQuery($query);
        if ($query === '') {
            return ['ok' => false, 'type' => $type, 'query' => '', 'error' => 'empty'];
        }
        if (mb_strlen($query) < 3) {
            return ['ok' => false, 'type' => $type, 'query' => $query, 'error' => 'short'];
        }
    }

    return [
        'ok'    => true,
        'type'  => $type,
        'query' => $query,
        'error' => null,
    ];
}

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
function searchAdminCars(PDO $pdo, string $query, int $limit = 15, string $type = 'vin'): array
{
    $prepared = prepareAdminSearchQuery($type, $query);
    if (!$prepared['ok'] || $limit <= 0) {
        return [];
    }

    $query = $prepared['query'];
    $type = $prepared['type'];
    $cars = [];
    $seenIds = [];

    $sqlBase = "SELECT c.id, c.vin_code, c.name, c.status, c.receive_location, c.receive_date, c.upload_date,
                       c.upload_number, c.contact_name, c.contact_phone,
                       (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id ORDER BY ci.sort_order ASC LIMIT 1) AS main_image,
                       (SELECT COUNT(*) FROM car_images ci WHERE ci.car_id = c.id) AS image_count
                FROM cars c
                WHERE c.deleted_at IS NULL AND ";

    if ($type === 'vin') {
        $stmt = $pdo->prepare(
            $sqlBase . '(UPPER(c.vin_code) = :exact OR c.vin_code LIKE :like_vin)
             ORDER BY (UPPER(c.vin_code) = :exact_order) DESC, c.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':exact', $query);
        $stmt->bindValue(':exact_order', $query);
        $stmt->bindValue(':like_vin', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } elseif ($type === 'digits') {
        $length = strlen($query);
        $stmt = $pdo->prepare(
            $sqlBase . 'RIGHT(c.vin_code, :length) = :digits
             ORDER BY c.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->bindValue(':digits', $query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } elseif ($type === 'model') {
        $stmt = $pdo->prepare(
            $sqlBase . 'c.name LIKE :like_name
             ORDER BY c.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':like_name', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $like = '%' . $query . '%';
        $stmt = $pdo->prepare(
            $sqlBase . '(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(c.contact_phone, \'\'), \' \', \'\'), \'-\', \'\'), \'(\', \'\'), \')\', \'\') LIKE :like_phone
                        OR c.contact_phone LIKE :like_phone_raw
                        OR c.contact_name LIKE :like_name)
             ORDER BY c.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':like_phone', $like);
        $stmt->bindValue(':like_phone_raw', $like);
        $stmt->bindValue(':like_name', $like);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

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
    $imageUrl = null;
    if (!empty($car['main_image'])) {
        $imageUrl = carImageDisplayUrl((string) $car['main_image'], 320)
            ?? carImageUrl((string) $car['main_image']);
    }

    $contactName = (string) ($car['contact_name'] ?? '');
    $contactPhone = (string) ($car['contact_phone'] ?? '');

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
        'contact_phone'   => $contactPhone,
        'contact_name'    => $contactName,
        'contact_display' => $contactPhone !== ''
            ? ($contactName !== '' ? $contactName . ' · ' . $contactPhone : $contactPhone)
            : ($contactName !== '' ? $contactName : __('common.dash')),
        'view_url'        => adminCarUrl('view.php', ['id' => $id]),
        'edit_url'        => adminCarUrl('edit.php', ['id' => $id]),
    ];
}

function renderDashboardSearchMobileCard(array $car): void
{
    $viewUrl = adminCarUrl('view.php', ['id' => (int) $car['id']]);
    $payload = adminSearchCarPayload($car);
    ?>
    <article class="car-card dashboard-search-card glass">
        <a href="<?= e($viewUrl) ?>" class="car-card-top dashboard-search-card-link">
            <div class="car-card-photo">
                <?php if ($payload['main_image']): ?>
                    <img src="<?= e((string) $payload['main_image']) ?>" alt="">
                <?php else: ?>
                    <span><?= e(__('dashboard.no_photo')) ?></span>
                <?php endif; ?>
            </div>
            <div class="dashboard-search-card-body">
                <h3><?= e((string) $payload['name']) ?></h3>
                <code><?= e((string) $payload['vin_code']) ?></code>
                <span class="badge <?= e((string) $payload['status_class']) ?>"><?= e((string) $payload['status_label']) ?></span>
            </div>
        </a>
        <dl class="car-card-meta dashboard-search-card-meta">
            <div><dt><?= e(__('field.contact_name')) ?></dt><dd><?= e($payload['contact_name'] !== '' ? (string) $payload['contact_name'] : __('common.dash')) ?></dd></div>
            <div><dt><?= e(__('cars.contact')) ?></dt><dd><?= e($payload['contact_phone'] !== '' ? (string) $payload['contact_phone'] : __('common.dash')) ?></dd></div>
            <div><dt><?= e(__('dashboard.receive')) ?></dt><dd><?= e((string) $payload['receive_display']) ?></dd></div>
            <div><dt><?= e(__('dashboard.upload')) ?></dt><dd><?= e((string) $payload['upload_date']) ?></dd></div>
            <div><dt><?= e(__('dashboard.status')) ?></dt><dd><?= e((string) $payload['status_label']) ?></dd></div>
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
