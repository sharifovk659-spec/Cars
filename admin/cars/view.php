<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';

requireAuth();

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    "SELECT c.*, (SELECT COUNT(*) FROM car_images ci WHERE ci.car_id = c.id) AS image_count
     FROM cars c
     WHERE c.id = :id AND c.deleted_at IS NULL"
);
$stmt->execute(['id' => $id]);
$car = $stmt->fetch();

if (!$car) {
    flashSet('error', 'Машина не найдена');
    redirect(adminUrl('cars/index.php'));
}

$images = getCarImages($id);
$mainImage = $images[0] ?? null;
$otherImages = array_slice($images, 1);

renderAdminHeader('Просмотр машины', 'cars');
?>

<section class="glass-card animate-in">
    <div class="card-head">
        <h2><?= e($car['name']) ?></h2>
        <div class="action-btns">
            <a href="<?= e(adminUrl('cars/edit.php?id=' . $car['id'])) ?>" class="btn-primary sm">Редактировать</a>
            <a href="<?= e(adminUrl('cars/index.php')) ?>" class="btn-ghost sm">← Назад</a>
        </div>
    </div>

    <?php if ($mainImage): ?>
        <div class="main-photo-wrap">
            <span class="main-badge large">Главное фото</span>
            <img src="<?= e(carImageUrl($mainImage['image_path']) ?? '') ?>" alt="Главное фото" class="main-photo">
        </div>
    <?php endif; ?>

    <?php if ($otherImages !== []): ?>
        <div class="gallery-section">
            <h3>Все фото</h3>
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
    <?php elseif (!$mainImage): ?>
        <div class="no-photo-large">Нет фотографий</div>
    <?php endif; ?>

    <dl class="detail-list view-details">
        <div><dt>VIN Code</dt><dd><code><?= e($car['vin_code']) ?></code></dd></div>
        <div><dt>Название</dt><dd><?= e($car['name']) ?></dd></div>
        <div><dt>Дата приёма</dt><dd><?= e(formatDate($car['receive_date'])) ?></dd></div>
        <div><dt>Дата загрузки</dt><dd><?= e(formatDate($car['upload_date'])) ?></dd></div>
        <div><dt>Статус</dt><dd><span class="badge <?= carStatusClass($car['status']) ?>"><?= e(carStatusLabel($car['status'])) ?></span></dd></div>
        <div><dt>Контакт</dt><dd><?= e($car['contact_name'] ?: '—') ?></dd></div>
        <div><dt>Телефон</dt><dd><?= e($car['contact_phone'] ?: '—') ?></dd></div>
        <div><dt>Дата добавления</dt><dd><?= e(formatDateTime($car['created_at'])) ?></dd></div>
        <div class="full"><dt>Описание</dt><dd><?= nl2br(e($car['description'] ?: '—')) ?></dd></div>
        <div class="full"><dt>Заметки</dt><dd><?= nl2br(e($car['notes'] ?: '—')) ?></dd></div>
    </dl>
</section>

<?php renderAdminFooter(); ?>
