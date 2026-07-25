<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';
require_once __DIR__ . '/../includes/ui.php';

requireAuth();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    flashSet('error', 'Неверный ID машины');
    redirect(adminCarUrl('index.php'));
}

$stmt = db()->prepare(
    "SELECT c.*, (SELECT COUNT(*) FROM car_images ci WHERE ci.car_id = c.id) AS image_count
     FROM cars c
     WHERE c.id = :id AND c.deleted_at IS NULL"
);
$stmt->execute(['id' => $id]);
$car = $stmt->fetch();

if (!$car) {
    flashSet('error', 'Машина не найдена');
    redirect(adminCarUrl('index.php'));
}

$images = getCarImages($id);

renderAdminHeader('Просмотр машины', 'cars');
?>

<section class="glass-card animate-in car-view-page">
    <div class="card-head">
        <div class="car-view-title">
            <a href="<?= e(adminCarUrl('index.php')) ?>" class="btn-ghost xs btn-with-icon back-link"><?= adminIcon('back') ?> К списку</a>
            <h2><?= e($car['name']) ?></h2>
            <div class="car-view-meta">
                <code class="vin-pill"><?= e($car['vin_code']) ?></code>
                <span class="badge <?= carStatusClass($car['status']) ?>"><?= e(carStatusLabel($car['status'])) ?></span>
            </div>
        </div>
        <div class="action-btns action-btns-lg">
            <a href="<?= e(adminCarUrl('edit.php', ['id' => $id])) ?>" class="btn-primary sm btn-with-icon"><?= adminIcon('edit') ?> Редактировать</a>
        </div>
    </div>

    <div class="detail-grid">
        <div class="detail-media">
            <?php if ($images !== []): ?>
                <div class="main-photo-wrap">
                    <span class="main-badge large">Главное фото</span>
                    <img src="<?= e(carImageUrl($images[0]['image_path']) ?? '') ?>" alt="<?= e($car['name']) ?>" class="main-photo">
                </div>

                <?php if (count($images) > 1): ?>
                    <div class="gallery-section">
                        <h3>Все фото <span class="count-badge"><?= count($images) ?></span></h3>
                        <div class="gallery">
                            <?php foreach ($images as $image): ?>
                                <?php if ($url = carImageUrl($image['image_path'])): ?>
                                    <figure class="gallery-item<?= (int) $image['sort_order'] === 1 ? ' is-main' : '' ?>">
                                        <img src="<?= e($url) ?>" alt="Фото <?= (int) $image['sort_order'] ?>">
                                        <?php if ((int) $image['sort_order'] === 1): ?>
                                            <figcaption>Главное</figcaption>
                                        <?php endif; ?>
                                    </figure>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-photo-large">📷 Нет фотографий</div>
            <?php endif; ?>
        </div>

        <div class="detail-info">
            <div class="info-panel car-form-preview">
                <h3>Маълумот</h3>
                <div class="sheet-row"><span class="sheet-label"><?= e(carFieldLabel('name')) ?> :</span><span class="sheet-value"><?= e($car['name']) ?></span></div>
                <div class="sheet-row"><span class="sheet-label"><?= e(carFieldLabel('receive_date')) ?> :</span><span class="sheet-value"><?= e(formatDate($car['receive_date'])) ?></span></div>
                <div class="sheet-row"><span class="sheet-label"><?= e(carFieldLabel('upload_date')) ?> :</span><span class="sheet-value"><?= e(formatDate($car['upload_date'])) ?></span></div>
                <div class="sheet-row"><span class="sheet-label"><?= e(carFieldLabel('upload_number')) ?></span><span class="sheet-value"><?= e($car['upload_number'] ?: '—') ?></span></div>
                <div class="sheet-row"><span class="sheet-label"><?= e(carFieldLabel('vagon')) ?></span><span class="sheet-value"><?= e($car['vagon'] ?: '—') ?></span></div>
                <div class="sheet-row"><span class="sheet-label"><?= e(carFieldLabel('treiler')) ?></span><span class="sheet-value"><?= e($car['treiler'] ?: '—') ?></span></div>
                <div class="sheet-row"><span class="sheet-label"><?= e(carFieldLabel('contact_phone')) ?></span><span class="sheet-value"><?= e($car['contact_phone'] ?: '—') ?></span></div>
            </div>

            <?php if (trim((string) ($car['description'] ?? '')) !== ''): ?>
                <div class="info-panel">
                    <h3>Описание</h3>
                    <p class="info-text"><?= nl2br(e($car['description'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (trim((string) ($car['notes'] ?? '')) !== ''): ?>
                <div class="info-panel">
                    <h3>Заметки</h3>
                    <p class="info-text"><?= nl2br(e($car['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php renderAdminFooter(); ?>
