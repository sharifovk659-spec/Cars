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

$searchType = trim($_GET['type'] ?? 'vin');
if (!in_array($searchType, adminSearchTypes(), true)) {
    $searchType = 'vin';
}
$searchQuery = trim($_GET['q'] ?? '');
$searchPrepared = $searchQuery !== '' ? prepareAdminSearchQuery($searchType, $searchQuery) : null;
$searchResults = ($searchPrepared !== null && $searchPrepared['ok'])
    ? searchAdminCars($pdo, $searchPrepared['query'], 15, $searchPrepared['type'])
    : [];
if ($searchPrepared !== null && $searchPrepared['ok']) {
    $searchQuery = $searchPrepared['query'];
    $searchType = $searchPrepared['type'];
}

$showSearchResults = ($searchPrepared['ok'] ?? false);

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
            <p class="muted dashboard-search-min" id="dashboardSearchHint"><?= e(__('dashboard.search_hint_typed')) ?></p>
        </div>
    </div>

    <div class="dashboard-search-tabs" role="tablist" aria-label="<?= e(__('dashboard.search')) ?>" id="dashboardSearchTabs">
        <?php foreach (adminSearchTypes() as $type): ?>
            <button type="button"
                    class="dashboard-search-tab<?= $searchType === $type ? ' is-active' : '' ?>"
                    role="tab"
                    data-search-type="<?= e($type) ?>"
                    data-placeholder="<?= e(adminSearchPlaceholder($type)) ?>"
                    aria-selected="<?= $searchType === $type ? 'true' : 'false' ?>">
                <?= e(adminSearchTypeLabel($type)) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <form method="get"
          class="dashboard-search-form"
          id="dashboardSearchForm"
          data-search-url="<?= e(adminUrl('api/search.php')) ?>">
        <input type="hidden" name="type" id="dashboardSearchType" value="<?= e($searchType) ?>">
        <label class="dashboard-search-field">
            <span class="dashboard-search-icon" aria-hidden="true">🔍</span>
            <input type="search"
                   name="q"
                   id="dashboardSearchInput"
                   value="<?= e($searchQuery) ?>"
                   autocomplete="off"
                   enterkeyhint="search"
                   inputmode="<?= $searchType === 'digits' || $searchType === 'phone' ? 'tel' : 'search' ?>"
                   placeholder="<?= e(adminSearchPlaceholder($searchType)) ?>">
        </label>
        <div class="dashboard-search-actions">
            <button type="submit" class="btn-primary dashboard-search-submit" id="dashboardSearchSubmit">
                <span class="dashboard-search-submit-label"><?= e(__('dashboard.search_btn')) ?></span>
                <span class="dashboard-search-submit-loading" hidden><?= e(__('dashboard.search_loading')) ?></span>
            </button>
            <a href="<?= e(adminUrl('dashboard.php')) ?>"
               class="btn-ghost dashboard-search-reset"
               id="dashboardSearchReset"
               <?= $searchQuery === '' ? 'hidden' : '' ?>><?= e(__('cars.reset')) ?></a>
        </div>
    </form>

    <p class="muted dashboard-search-typing" id="dashboardSearchTyping" hidden><?= e(__('dashboard.search_typing')) ?></p>
    <p class="dashboard-search-error" id="dashboardSearchError" hidden></p>

    <div id="dashboardSearchResults" class="dashboard-search-results"<?= $showSearchResults ? '' : ' hidden' ?>>
        <div class="card-head dashboard-search-results-head">
            <h3>
                <?= e(__('dashboard.search_results')) ?>
                <span class="count-badge" id="dashboardSearchCount"><?= count($searchResults) ?></span>
            </h3>
        </div>

        <p class="muted empty-state" id="dashboardSearchEmpty"<?= ($showSearchResults && $searchResults === []) ? '' : ' hidden' ?>>
            <?= e(__('dashboard.search_no_results')) ?>
        </p>

        <div class="table-wrap desktop-only" id="dashboardSearchTable"<?= $searchResults !== [] ? '' : ' hidden' ?>>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= e(__('dashboard.photo')) ?></th>
                        <th><?= e(__('dashboard.name')) ?></th>
                        <th><?= e(__('dashboard.vin')) ?></th>
                        <th><?= e(__('field.contact_name')) ?></th>
                        <th><?= e(__('cars.contact')) ?></th>
                        <th><?= e(__('dashboard.receive')) ?></th>
                        <th><?= e(__('dashboard.upload')) ?></th>
                        <th><?= e(__('dashboard.status')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="dashboardSearchTbody">
                    <?php foreach ($searchResults as $car): ?>
                        <?php $row = adminSearchCarPayload($car); ?>
                        <tr>
                            <td>
                                <div class="thumb">
                                    <?php if ($row['main_image']): ?>
                                        <img src="<?= e((string) $row['main_image']) ?>" alt="">
                                    <?php else: ?>
                                        <span class="no-photo"><?= e(__('common.dash')) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= e((string) $row['name']) ?></td>
                            <td><a href="<?= e((string) $row['view_url']) ?>"><code><?= e((string) $row['vin_code']) ?></code></a></td>
                            <td><?= e($row['contact_name'] !== '' ? (string) $row['contact_name'] : __('common.dash')) ?></td>
                            <td><?= e($row['contact_phone'] !== '' ? (string) $row['contact_phone'] : __('common.dash')) ?></td>
                            <td><?= e((string) $row['receive_display']) ?></td>
                            <td><?= e((string) $row['upload_date']) ?></td>
                            <td><span class="badge <?= e((string) $row['status_class']) ?>"><?= e((string) $row['status_label']) ?></span></td>
                            <td class="actions-cell">
                                <a href="<?= e((string) $row['view_url']) ?>" class="btn-link sm"><?= e(__('dashboard.open')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-cards mobile-only" id="dashboardSearchCards"<?= $searchResults !== [] ? '' : ' hidden' ?>>
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
