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
    "SELECT c.id, c.vin_code, c.name, c.status, c.receive_location, c.receive_date, c.upload_date, c.created_at,
            (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id ORDER BY ci.sort_order ASC LIMIT 1) AS main_image,
            (SELECT COUNT(*) FROM car_images ci WHERE ci.car_id = c.id) AS image_count
     FROM cars c
     WHERE c.deleted_at IS NULL
     ORDER BY c.created_at DESC
     LIMIT 8"
);
$recentCars = $recentStmt->fetchAll();

$searchQuery = trim($_GET['q'] ?? '');
$searchResults = $searchQuery !== '' ? searchAdminCars($pdo, $searchQuery) : [];

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

<section class="glass-card dashboard-search-panel animate-in" style="--delay: 0.32s">
    <div class="card-head dashboard-search-head">
        <div>
            <h2><?= e(__('dashboard.search')) ?></h2>
            <div class="dashboard-search-tags" aria-label="<?= e(__('dashboard.search_hint')) ?>">
                <button type="button" class="dashboard-search-tag" data-search-hint="VIN"><?= e(__('dashboard.search_tag_vin')) ?></button>
                <button type="button" class="dashboard-search-tag" data-search-hint="76870"><?= e(__('dashboard.search_tag_digits')) ?></button>
                <button type="button" class="dashboard-search-tag" data-search-hint="Toyota"><?= e(__('dashboard.search_tag_model')) ?></button>
                <button type="button" class="dashboard-search-tag" data-search-hint="+971"><?= e(__('dashboard.search_tag_phone')) ?></button>
            </div>
            <p class="muted dashboard-search-min"><?= e(__('dashboard.search_min')) ?></p>
        </div>
    </div>

    <form method="get"
          class="users-search dashboard-search-form"
          id="dashboardSearchForm"
          data-search-url="<?= e(adminUrl('api/search.php')) ?>">
        <label class="users-search-field">
            <span aria-hidden="true">🔍</span>
            <input type="search"
                   name="q"
                   id="dashboardSearchInput"
                   value="<?= e($searchQuery) ?>"
                   autocomplete="off"
                   enterkeyhint="search"
                   inputmode="search"
                   placeholder="<?= e(__('dashboard.search_placeholder')) ?>">
        </label>
        <div class="dashboard-search-actions">
            <button type="submit" class="btn-primary sm"><?= e(__('dashboard.search_btn')) ?></button>
            <a href="<?= e(adminUrl('dashboard.php')) ?>"
               class="btn-ghost sm"
               id="dashboardSearchReset"
               <?= $searchQuery === '' ? 'hidden' : '' ?>><?= e(__('cars.reset')) ?></a>
        </div>
    </form>

    <p class="muted dashboard-search-typing" id="dashboardSearchTyping" hidden><?= e(__('dashboard.search_typing')) ?></p>

    <div id="dashboardSearchResults" class="dashboard-search-results"<?= $searchQuery === '' ? ' hidden' : '' ?>>
        <div class="card-head dashboard-search-results-head">
            <h3>
                <?= e(__('dashboard.search_results')) ?>
                <span class="count-badge" id="dashboardSearchCount"><?= $searchQuery !== '' ? count($searchResults) : 0 ?></span>
            </h3>
        </div>

        <p class="muted empty-state" id="dashboardSearchEmpty"<?= ($searchQuery !== '' && $searchResults === []) ? '' : ' hidden' ?>>
            <?= e(__('dashboard.search_no_results')) ?>
        </p>

        <div class="table-wrap desktop-only" id="dashboardSearchTable"<?= ($searchQuery !== '' && $searchResults !== []) ? '' : ' hidden' ?>>
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
                        <th></th>
                    </tr>
                </thead>
                <tbody id="dashboardSearchTbody">
                    <?php foreach ($searchResults as $car): ?>
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
                            <td><a href="<?= e(adminCarUrl('view.php', ['id' => (int) $car['id']])) ?>"><code><?= e($car['vin_code']) ?></code></a></td>
                            <td><?= e($car['name']) ?></td>
                            <td><span class="badge <?= carStatusClass($car['status']) ?>"><?= e(carStatusLabel($car['status'])) ?></span></td>
                            <td><?= e(carReceiveDisplayText($car)) ?></td>
                            <td><?= e(formatDate($car['upload_date'])) ?></td>
                            <td><?= (int) $car['image_count'] ?></td>
                            <td class="actions-cell">
                                <a href="<?= e(adminCarUrl('view.php', ['id' => (int) $car['id']])) ?>" class="btn-link sm"><?= e(__('dashboard.open')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-cards mobile-only" id="dashboardSearchCards"<?= ($searchQuery !== '' && $searchResults !== []) ? '' : ' hidden' ?>>
            <?php foreach ($searchResults as $car): ?>
                <?php renderDashboardSearchMobileCard($car); ?>
            <?php endforeach; ?>
        </div>
    </div>
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
