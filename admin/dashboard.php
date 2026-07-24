<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

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

$stmt = $pdo->query(
    "SELECT COUNT(*) FROM cars WHERE deleted_at IS NULL"
);
$stats['total_cars'] = (int) $stmt->fetchColumn();

$stmt = $pdo->query(
    "SELECT COUNT(*) FROM cars WHERE deleted_at IS NULL AND DATE(created_at) = CURDATE()"
);
$stats['added_today'] = (int) $stmt->fetchColumn();

$stmt = $pdo->query(
    "SELECT COUNT(*) FROM cars WHERE deleted_at IS NULL AND status = 'reserved'"
);
$stats['in_progress'] = (int) $stmt->fetchColumn();

$stmt = $pdo->query(
    "SELECT COUNT(*) FROM cars WHERE deleted_at IS NULL AND upload_date IS NOT NULL"
);
$stats['uploaded'] = (int) $stmt->fetchColumn();

$stmt = $pdo->query(
    "SELECT COUNT(*) FROM cars c
     WHERE c.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM car_images ci WHERE ci.car_id = c.id)"
);
$stats['without_images'] = (int) $stmt->fetchColumn();

$stmt = $pdo->query(
    "SELECT COUNT(*) FROM search_history WHERE DATE(searched_at) = CURDATE()"
);
$stats['searches_today'] = (int) $stmt->fetchColumn();

$recentStmt = $pdo->query(
    "SELECT c.id, c.vin_code, c.name, c.status, c.receive_date, c.upload_date, c.created_at,
            (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id ORDER BY ci.sort_order ASC LIMIT 1) AS main_image,
            (SELECT COUNT(*) FROM car_images ci WHERE ci.car_id = c.id) AS image_count
     FROM cars c
     WHERE c.deleted_at IS NULL
     ORDER BY c.created_at DESC
     LIMIT 8"
);
$recentCars = $recentStmt->fetchAll();

renderAdminHeader('Главная', 'dashboard');
?>

<section class="stats-grid">
    <article class="stat-card glass animate-in" style="--delay: 0.05s">
        <div class="stat-icon blue">🚗</div>
        <div>
            <span class="stat-label">Все машины</span>
            <strong class="stat-value"><?= $stats['total_cars'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.1s">
        <div class="stat-icon green">✦</div>
        <div>
            <span class="stat-label">Добавлено сегодня</span>
            <strong class="stat-value"><?= $stats['added_today'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.15s">
        <div class="stat-icon amber">⏳</div>
        <div>
            <span class="stat-label">В обработке</span>
            <strong class="stat-value"><?= $stats['in_progress'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.2s">
        <div class="stat-icon cyan">↑</div>
        <div>
            <span class="stat-label">Загружено</span>
            <strong class="stat-value"><?= $stats['uploaded'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.25s">
        <div class="stat-icon red">📷</div>
        <div>
            <span class="stat-label">Без фото</span>
            <strong class="stat-value"><?= $stats['without_images'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass animate-in" style="--delay: 0.3s">
        <div class="stat-icon purple">🔍</div>
        <div>
            <span class="stat-label">Поиски сегодня</span>
            <strong class="stat-value"><?= $stats['searches_today'] ?></strong>
        </div>
    </article>
</section>

<section class="glass-card animate-in" style="--delay: 0.35s">
    <div class="card-head">
        <h2>Последние машины</h2>
        <a href="<?= e(adminUrl('cars/index.php')) ?>" class="btn-link">Все машины →</a>
    </div>

    <?php if (empty($recentCars)): ?>
        <p class="muted empty-state">Пока нет машин в базе</p>
    <?php else: ?>
        <div class="table-wrap desktop-only">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Фото</th>
                        <th>VIN</th>
                        <th>Название</th>
                        <th>Статус</th>
                        <th>Приём</th>
                        <th>Загрузка</th>
                        <th>Фото</th>
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
                                        <span class="no-photo">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><code><?= e($car['vin_code']) ?></code></td>
                            <td><?= e($car['name']) ?></td>
                            <td><span class="badge <?= carStatusClass($car['status']) ?>"><?= e(carStatusLabel($car['status'])) ?></span></td>
                            <td><?= e(formatDate($car['receive_date'])) ?></td>
                            <td><?= e(formatDate($car['upload_date'])) ?></td>
                            <td><?= (int) $car['image_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-cards mobile-only">
            <?php foreach ($recentCars as $car): ?>
                <article class="car-card-mini glass">
                    <div class="car-card-mini-photo">
                        <?php if ($img = carImageUrl($car['main_image'])): ?>
                            <img src="<?= e($img) ?>" alt="">
                        <?php else: ?>
                            <span>Нет фото</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?= e($car['name']) ?></strong>
                        <code><?= e($car['vin_code']) ?></code>
                        <span class="badge <?= carStatusClass($car['status']) ?>"><?= e(carStatusLabel($car['status'])) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php renderAdminFooter(); ?>
