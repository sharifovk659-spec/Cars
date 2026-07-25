<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ui.php';

requireAuth();

$admin = getCurrentAdmin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $action = $_POST['action'] ?? '';
    $carId = (int) ($_POST['car_id'] ?? 0);

    if ($carId > 0) {
        $check = $pdo->prepare('SELECT id, vin_code FROM cars WHERE id = :id AND deleted_at IS NULL');
        $check->execute(['id' => $carId]);
        $car = $check->fetch();

        if ($car) {
            if ($action === 'delete') {
                $del = $pdo->prepare('UPDATE cars SET deleted_at = NOW() WHERE id = :id');
                $del->execute(['id' => $carId]);
                logActivity((int) $admin['id'], 'car_soft_delete', 'car', $carId, $car['vin_code']);
                flashSet('success', __('flash.car_deleted'));
            }

            if ($action === 'status') {
                $status = $_POST['status'] ?? '';
                $allowed = array_keys(carStatusLabels());

                if (in_array($status, $allowed, true)) {
                    $upd = $pdo->prepare('UPDATE cars SET status = :status WHERE id = :id');
                    $upd->execute(['status' => $status, 'id' => $carId]);
                    logActivity((int) $admin['id'], 'car_status_change', 'car', $carId, $status);
                    flashSet('success', __('flash.status_updated'));
                }
            }

            if ($action === 'upload_date') {
                $uploadDate = trim($_POST['upload_date'] ?? '');

                if ($uploadDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $uploadDate)) {
                    $upd = $pdo->prepare('UPDATE cars SET upload_date = :upload_date WHERE id = :id');
                    $upd->execute(['upload_date' => $uploadDate, 'id' => $carId]);
                    logActivity((int) $admin['id'], 'car_upload_date_set', 'car', $carId, $uploadDate);
                    flashSet('success', __('flash.upload_date_saved'));
                }
            }
        }
    }

    $query = $_GET;
    unset($query['page']);
    $redirectUrl = adminUrl('cars/index.php') . ($query ? '?' . http_build_query($query) : '');
    redirect($redirectUrl);
}

$vinSearch = trim($_GET['vin'] ?? '');
$nameSearch = trim($_GET['name'] ?? '');
$phoneSearch = trim($_GET['phone'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = ['c.deleted_at IS NULL'];
$params = [];

if ($vinSearch !== '') {
    $where[] = 'c.vin_code LIKE :vin';
    $params['vin'] = '%' . $vinSearch . '%';
}

if ($nameSearch !== '') {
    $where[] = 'c.name LIKE :name';
    $params['name'] = '%' . $nameSearch . '%';
}

if ($phoneSearch !== '') {
    $where[] = 'c.contact_phone LIKE :phone';
    $params['phone'] = '%' . $phoneSearch . '%';
}

if ($statusFilter !== '' && array_key_exists($statusFilter, carStatusLabels())) {
    $where[] = 'c.status = :status';
    $params['status'] = $statusFilter;
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'c.receive_date >= :date_from';
    $params['date_from'] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'c.receive_date <= :date_to';
    $params['date_to'] = $dateTo;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cars c WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listStmt = $pdo->prepare(
    "SELECT c.id, c.vin_code, c.name, c.receive_location, c.receive_date, c.upload_date, c.status,
            c.contact_name, c.contact_phone,
            (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id ORDER BY ci.sort_order ASC LIMIT 1) AS main_image,
            (SELECT COUNT(*) FROM car_images ci WHERE ci.car_id = c.id) AS image_count
     FROM cars c
     WHERE {$whereSql}
     ORDER BY c.created_at DESC
     LIMIT :limit OFFSET :offset"
);

foreach ($params as $key => $value) {
    $listStmt->bindValue(':' . $key, $value);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$cars = $listStmt->fetchAll();

$queryBase = array_filter([
    'vin'       => $vinSearch,
    'name'      => $nameSearch,
    'phone'     => $phoneSearch,
    'status'    => $statusFilter,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
], fn($v) => $v !== '');

renderAdminHeader(__('cars.title'), 'cars');
?>

<section class="glass-card filters-card animate-in">
    <form method="get" class="filters-form">
        <div class="filters-grid">
            <label>
                <span>VIN Code</span>
                <input type="text" name="vin" value="<?= e($vinSearch) ?>" placeholder="<?= e(__('cars.search_vin')) ?>">
            </label>
            <label>
                <span><?= e(__('dashboard.name')) ?></span>
                <input type="text" name="name" value="<?= e($nameSearch) ?>" placeholder="<?= e(__('cars.search_name')) ?>">
            </label>
            <label>
                <span><?= e(__('cars.filter_status')) ?></span>
                <select name="status">
                    <option value=""><?= e(__('cars.all')) ?></option>
                    <?php foreach (carStatusLabels() as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $statusFilter === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?= e(__('cars.date_from')) ?></span>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
            </label>
            <label>
                <span><?= e(__('cars.date_to')) ?></span>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>">
            </label>
        </div>
        <div class="filters-actions">
            <button type="submit" class="btn-primary"><?= e(__('cars.apply')) ?></button>
            <a href="<?= e(adminUrl('cars/index.php')) ?>" class="btn-ghost"><?= e(__('cars.reset')) ?></a>
        </div>
    </form>
</section>

<section class="glass-card animate-in" style="--delay: 0.1s">
    <div class="card-head">
        <h2><?= e(__('cars.list_title')) ?> <span class="count-badge"><?= $total ?></span></h2>
        <a href="<?= e(adminUrl('cars/add.php')) ?>" class="btn-primary sm"><?= e(__('cars.add_btn')) ?></a>
    </div>

    <?php if (empty($cars)): ?>
        <p class="muted empty-state"><?= e(__('cars.not_found')) ?></p>
    <?php else: ?>

        <div class="table-wrap desktop-only">
            <table class="data-table cars-table">
                <thead>
                    <tr>
                        <th><?= e(__('dashboard.photo')) ?></th>
                        <th><?= e(__('dashboard.vin')) ?></th>
                        <th><?= e(__('dashboard.name')) ?></th>
                        <th><?= e(__('dashboard.receive')) ?></th>
                        <th><?= e(__('dashboard.upload')) ?></th>
                        <th><?= e(__('dashboard.status')) ?></th>
                        <th><?= e(__('cars.contact')) ?></th>
                        <th><?= e(__('dashboard.photos_count')) ?></th>
                        <th><?= e(__('cars.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cars as $car): ?>
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
                            <td><?= e(carReceiveDisplayText($car)) ?></td>
                            <td>
                                <form method="post" class="inline-form upload-date-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="upload_date">
                                    <input type="hidden" name="car_id" value="<?= (int) $car['id'] ?>">
                                    <input type="date" name="upload_date"
                                           value="<?= e($car['upload_date'] ?? '') ?>"
                                           onchange="this.form.submit()">
                                </form>
                            </td>
                            <td>
                                <form method="post" class="inline-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="car_id" value="<?= (int) $car['id'] ?>">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <?php foreach (carStatusLabels() as $key => $label): ?>
                                            <option value="<?= e($key) ?>"<?= $car['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <?php if ($car['contact_name']): ?>
                                    <strong><?= e($car['contact_name']) ?></strong><br>
                                    <small><?= e($car['contact_phone'] ?? '') ?></small>
                                <?php else: ?>
                                    <?= e(__('common.dash')) ?>
                                <?php endif; ?>
                            </td>
                            <td><span class="count-badge"><?= (int) $car['image_count'] ?></span></td>
                            <td>
                                <?php renderCarActionButtons((int) $car['id'], $car['name']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-cards mobile-only">
            <?php foreach ($cars as $car): ?>
                <article class="car-card glass">
                    <div class="car-card-top">
                        <div class="car-card-photo">
                            <?php if ($img = carImageUrl($car['main_image'])): ?>
                                <img src="<?= e($img) ?>" alt="">
                            <?php else: ?>
                                <span><?= e(__('common.no_photo')) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3><?= e($car['name']) ?></h3>
                            <code><?= e($car['vin_code']) ?></code>
                            <span class="badge <?= carStatusClass($car['status']) ?>"><?= e(carStatusLabel($car['status'])) ?></span>
                        </div>
                    </div>
                    <dl class="car-card-meta">
                        <div><dt><?= e(__('dashboard.receive')) ?></dt><dd><?= e(carReceiveDisplayText($car)) ?></dd></div>
                        <div><dt><?= e(__('dashboard.upload')) ?></dt><dd><?= e(formatDate($car['upload_date'])) ?></dd></div>
                        <div><dt><?= e(__('cars.contact')) ?></dt><dd><?= e($car['contact_name'] ?: __('common.dash')) ?></dd></div>
                        <div><dt><?= e(__('dashboard.photos_count')) ?></dt><dd><?= (int) $car['image_count'] ?></dd></div>
                    </dl>
                    <div class="car-card-actions">
                        <form method="post" class="car-card-status-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="car_id" value="<?= (int) $car['id'] ?>">
                            <label class="status-field">
                                <span><?= e(__('cars.filter_status')) ?></span>
                                <select name="status" class="status-select" onchange="this.form.submit()">
                                    <?php foreach (carStatusLabels() as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $car['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </form>
                        <div class="car-card-action-icons">
                            <?php renderCarActionButtons((int) $car['id'], $car['name']); ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $q = array_merge($queryBase, ['page' => $i]); ?>
                    <a href="<?= e(adminUrl('cars/index.php?' . http_build_query($q))) ?>"
                       class="page-link<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</section>

<form method="post" id="deleteForm" class="hidden">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="car_id" id="deleteCarId" value="">
</form>

<div class="modal" id="deleteModal" hidden>
    <div class="modal-backdrop" data-close></div>
    <div class="modal-card glass">
        <h3><?= e(__('cars.delete_modal_title')) ?></h3>
        <p id="deleteModalText"></p>
        <div class="modal-actions">
            <button type="button" class="btn-ghost" data-close><?= e(__('btn.cancel')) ?></button>
            <button type="button" class="btn-danger" id="confirmDelete"><?= e(__('cars.delete_confirm')) ?></button>
        </div>
    </div>
</div>

<?php renderAdminFooter(); ?>
