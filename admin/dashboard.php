<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/search.php';
require_once __DIR__ . '/includes/ui.php';

requireAuth();

$pdo = db();

$stats = [
    'total_cars'      => 0,
    'added_today'     => 0,
    'in_progress'     => 0,
    'uploaded'        => 0,
    'without_images'  => 0,
    'searches_today'  => 0,
];

$statsRow = $pdo->query(
    "SELECT
        COUNT(*) AS total_cars,
        SUM(CASE WHEN created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY THEN 1 ELSE 0 END) AS added_today,
        SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN upload_date IS NOT NULL THEN 1 ELSE 0 END) AS uploaded,
        SUM(CASE WHEN NOT EXISTS (
            SELECT 1 FROM car_images ci WHERE ci.car_id = c.id
        ) THEN 1 ELSE 0 END) AS without_images
     FROM cars c
     WHERE c.deleted_at IS NULL"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$stats['total_cars'] = (int) ($statsRow['total_cars'] ?? 0);
$stats['added_today'] = (int) ($statsRow['added_today'] ?? 0);
$stats['in_progress'] = (int) ($statsRow['in_progress'] ?? 0);
$stats['uploaded'] = (int) ($statsRow['uploaded'] ?? 0);
$stats['without_images'] = (int) ($statsRow['without_images'] ?? 0);

$stats['searches_today'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM search_history
     WHERE searched_at >= CURDATE() AND searched_at < CURDATE() + INTERVAL 1 DAY"
)->fetchColumn();

$recentStmt = $pdo->query(
    "SELECT c.id, c.vin_code, c.name, c.status, c.receive_location, c.receive_date, c.upload_date, c.created_at,
            ci.image_path AS main_image,
            COALESCE(img.image_count, 0) AS image_count
     FROM cars c
     LEFT JOIN (
        SELECT car_id, MIN(sort_order) AS min_sort, COUNT(*) AS image_count
        FROM car_images
        GROUP BY car_id
     ) img ON img.car_id = c.id
     LEFT JOIN car_images ci ON ci.car_id = img.car_id AND ci.sort_order = img.min_sort
     WHERE c.deleted_at IS NULL
     ORDER BY c.created_at DESC
     LIMIT 8"
);
$recentCars = $recentStmt->fetchAll();

renderAdminHeader(__('dashboard.title'), 'dashboard');
?>

<section class="stats-grid">
    <article class="stat-card glass animate-in" style="--delay: 0.05s">
        <div class="stat-icon blue">🚗</div>
        <div>
            <span class="stat-label"><?= e(__('dashboard.all_cars')) ?></span>
            <strong class="stat-value"><?= $stats['total_cars'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.1s">
        <div class="stat-icon green">✦</div>
        <div>
            <span class="stat-label"><?= e(__('dashboard.added_today')) ?></span>
            <strong class="stat-value"><?= $stats['added_today'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.15s">
        <div class="stat-icon amber">⏳</div>
        <div>
            <span class="stat-label"><?= e(__('dashboard.in_progress')) ?></span>
            <strong class="stat-value"><?= $stats['in_progress'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.2s">
        <div class="stat-icon cyan">↑</div>
        <div>
            <span class="stat-label"><?= e(__('dashboard.uploaded')) ?></span>
            <strong class="stat-value"><?= $stats['uploaded'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.25s">
        <div class="stat-icon red">📷</div>
        <div>
            <span class="stat-label"><?= e(__('dashboard.without_photos')) ?></span>
            <strong class="stat-value"><?= $stats['without_images'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.3s">
        <div class="stat-icon purple">🔍</div>
        <div>
            <span class="stat-label"><?= e(__('dashboard.searches_today')) ?></span>
            <strong class="stat-value"><?= $stats['searches_today'] ?></strong>
        </div>
    </article>
</section>

<section class="glass-card filters-card animate-in cars-filters is-open dashboard-cars-filters" style="--delay: 0.32s" data-cars-filters>
    <div class="card-head">
        <div>
            <h2><?= e(__('dashboard.search')) ?></h2>
            <p class="muted dashboard-search-min"><?= e(__('cars.filters')) ?></p>
        </div>
    </div>
    <button type="button" class="btn-ghost cars-filters-toggle mobile-only" aria-expanded="true">
        <span><?= e(__('cars.filters')) ?></span>
        <span class="cars-mobile-chevron" aria-hidden="true">▾</span>
    </button>
    <form method="get" class="filters-form" action="<?= e(adminUrl('cars/index.php')) ?>">
        <div class="filters-grid">
            <label>
                <span>VIN Code</span>
                <input type="text" name="vin" value="" placeholder="<?= e(__('cars.search_vin')) ?>" autocomplete="off">
            </label>
            <label>
                <span><?= e(__('dashboard.name')) ?></span>
                <input type="text" name="name" value="" placeholder="<?= e(__('cars.search_name')) ?>" autocomplete="off">
            </label>
            <label>
                <span><?= e(__('cars.contact')) ?></span>
                <input type="text" name="phone" value="" placeholder="<?= e(__('cars.search_phone')) ?>" autocomplete="off" inputmode="tel">
            </label>
            <label>
                <span><?= e(__('cars.filter_status')) ?></span>
                <select name="status" class="status-select">
                    <option value=""><?= e(__('cars.all')) ?></option>
                    <?php foreach (carStatusLabels() as $key => $label): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?= e(__('cars.date_from')) ?></span>
                <input type="date" name="date_from" value="" class="date-picker-field">
            </label>
            <label>
                <span><?= e(__('cars.date_to')) ?></span>
                <input type="date" name="date_to" value="" class="date-picker-field">
            </label>
        </div>
        <div class="filters-actions">
            <button type="submit" class="btn-primary"><?= e(__('cars.apply')) ?></button>
            <a href="<?= e(adminUrl('cars/index.php')) ?>" class="btn-ghost"><?= e(__('cars.reset')) ?></a>
        </div>
    </form>
</section>

<section class="glass-card animate-in" style="--delay: 0.35s">
    <div class="card-head">
        <h2><?= e(__('dashboard.recent_cars')) ?></h2>
        <a href="<?= e(adminUrl('cars/index.php')) ?>" class="btn-link"><?= e(__('dashboard.all_cars_link')) ?></a>
    </div>

    <?php if (empty($recentCars)): ?>
        <p class="muted empty-state"><?= e(__('dashboard.no_cars')) ?></p>
    <?php else: ?>
        <div class="table-wrap desktop-only">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= e(__('dashboard.photo')) ?></th>
                        <th><?= e(__('dashboard.vin')) ?></th>
                        <th><?= e(__('dashboard.name')) ?></th>
                        <th><?= e(__('dashboard.status')) ?></th>
                        <th><?= e(__('dashboard.receive')) ?></th>
                        <th><?= e(__('dashboard.upload')) ?></th>
                        <th><?= e(__('dashboard.photos_count')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentCars as $car): ?>
                        <tr>
                            <td>
                                <div class="thumb">
                                    <?php if ($img = carImageUrl($car['main_image'])): ?>
                                        <img src="<?= e($img) ?>" alt="">
                                    <?php else: ?>
                                        <span class="no-photo"><?= e(__('common.dash')) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><code><?= e($car['vin_code']) ?></code></td>
                            <td><?= e($car['name']) ?></td>
                            <td><span class="badge <?= carStatusClass($car['status']) ?>"><?= e(carStatusLabel($car['status'])) ?></span></td>
                            <td><?= e(carReceiveDisplayText($car)) ?></td>
                            <td><?= e(formatDate($car['upload_date'])) ?></td>
                            <td><?= (int) $car['image_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-cards mobile-only">
            <?php foreach ($recentCars as $car): ?>
                <?php renderDashboardRecentMobileCard($car); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php renderAdminFooter(); ?>
