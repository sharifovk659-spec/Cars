<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';
require_once __DIR__ . '/../includes/ui.php';

requireAuth();

$admin = getCurrentAdmin();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    flashSet('error', 'Неверный ID машины');
    redirect(adminCarUrl('index.php'));
}

$stmt = db()->prepare('SELECT * FROM cars WHERE id = :id AND deleted_at IS NULL');
$stmt->execute(['id' => $id]);
$car = $stmt->fetch();

if (!$car) {
    flashSet('error', 'Машина не найдена');
    redirect(adminCarUrl('index.php'));
}

$existingImages = getCarImages($id);
$errors = [];

$input = [
    'vin_code'      => $car['vin_code'],
    'name'          => $car['name'],
    'description'   => (string) ($car['description'] ?? ''),
    'receive_date'  => $car['receive_date'],
    'upload_date'   => (string) ($car['upload_date'] ?? ''),
    'status'        => $car['status'],
    'contact_name'  => (string) ($car['contact_name'] ?? ''),
    'contact_phone' => (string) ($car['contact_phone'] ?? ''),
    'notes'         => (string) ($car['notes'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $input = carFormInput();
    $errors = validateCarForm($input, $id);

    $deleteIds = array_map('intval', $_POST['delete_images'] ?? []);
    $deleteIds = array_values(array_filter($deleteIds, fn($v) => $v > 0));

    $orderIds = array_map('intval', $_POST['image_order'] ?? []);
    $orderIds = array_values(array_filter($orderIds, fn($v) => $v > 0));

    $existingById = [];
    foreach ($existingImages as $img) {
        $existingById[(int) $img['id']] = $img;
    }

    foreach ($deleteIds as $deleteId) {
        if (!isset($existingById[$deleteId])) {
            $errors[] = 'Сурат барои нест кардан ёфт нашуд';
        }
    }

    $orderedExisting = [];
    $usedIds = [];

    foreach ($orderIds as $orderId) {
        if (in_array($orderId, $deleteIds, true) || !isset($existingById[$orderId])) {
            continue;
        }
        $orderedExisting[] = $existingById[$orderId];
        $usedIds[] = $orderId;
    }

    foreach ($existingImages as $img) {
        $imgId = (int) $img['id'];
        if (!in_array($imgId, $deleteIds, true) && !in_array($imgId, $usedIds, true)) {
            $orderedExisting[] = $img;
        }
    }

    $newUploads = ['files' => [], 'errors' => []];
    if (isset($_FILES['new_images']) && is_array($_FILES['new_images']['name'] ?? null)) {
        $hasNew = false;
        foreach ($_FILES['new_images']['error'] as $err) {
            if ((int) $err !== UPLOAD_ERR_NO_FILE) {
                $hasNew = true;
                break;
            }
        }
        if ($hasNew) {
            $newUploads = normalizeUploadedImages($_FILES['new_images']);
            $errors = array_merge($errors, $newUploads['errors']);
        }
    }

    $totalImages = count($orderedExisting) + count($newUploads['files']);

    if ($totalImages < MIN_CAR_IMAGES) {
        $errors[] = 'Акаллан 1 сурат бояд боқӣ монад';
    }

    if ($totalImages > getMaxCarImages()) {
        $errors[] = 'На бештар аз ' . getMaxCarImages() . ' сурат';
    }

    $mainExistingId = (int) ($_POST['main_image_id'] ?? 0);
    $mainNewIndex = (int) ($_POST['main_new_index'] ?? -1);

    if ($errors === []) {
        $savedPaths = [];
        $pathsToDelete = [];
        $pdo = db();

        try {
            $pdo->beginTransaction();

            updateCarRecord($pdo, $id, $input);

            foreach ($deleteIds as $deleteId) {
                if (isset($existingById[$deleteId])) {
                    $pathsToDelete[] = $existingById[$deleteId]['image_path'];
                }
            }

            if ($newUploads['files'] !== []) {
                $savedPaths = saveUploadedImages($newUploads['files']);
            }

            /** @var list<array{type: string, path: string, id?: int}> $finalItems */
            $finalItems = [];

            foreach ($orderedExisting as $img) {
                $finalItems[] = ['type' => 'existing', 'path' => $img['image_path'], 'id' => (int) $img['id']];
            }

            foreach ($savedPaths as $index => $path) {
                $finalItems[] = ['type' => 'new', 'path' => $path, 'new_index' => $index];
            }

            if ($mainNewIndex >= 0) {
                foreach ($finalItems as $idx => $item) {
                    if ($item['type'] === 'new' && ($item['new_index'] ?? -1) === $mainNewIndex) {
                        $main = $finalItems[$idx];
                        unset($finalItems[$idx]);
                        array_unshift($finalItems, $main);
                        $finalItems = array_values($finalItems);
                        break;
                    }
                }
            } elseif ($mainExistingId > 0) {
                foreach ($finalItems as $idx => $item) {
                    if ($item['type'] === 'existing' && ($item['id'] ?? 0) === $mainExistingId) {
                        $main = $finalItems[$idx];
                        unset($finalItems[$idx]);
                        array_unshift($finalItems, $main);
                        $finalItems = array_values($finalItems);
                        break;
                    }
                }
            }

            $delImages = $pdo->prepare('DELETE FROM car_images WHERE car_id = :car_id');
            $delImages->execute(['car_id' => $id]);

            $insert = $pdo->prepare(
                'INSERT INTO car_images (car_id, image_path, sort_order) VALUES (:car_id, :image_path, :sort_order)'
            );

            foreach ($finalItems as $index => $item) {
                $insert->execute([
                    'car_id'     => $id,
                    'image_path' => $item['path'],
                    'sort_order' => $index + 1,
                ]);
            }

            $pdo->commit();

            foreach ($pathsToDelete as $path) {
                deleteCarImageFile($path);
            }

            logActivity(
                (int) $admin['id'],
                'car_update',
                'car',
                $id,
                json_encode([
                    'vin'     => $input['vin_code'],
                    'deleted' => count($deleteIds),
                    'added'   => count($savedPaths),
                    'total'   => count($finalItems),
                ], JSON_UNESCAPED_UNICODE)
            );

            flashSet('success', 'Машина обновлена');
            redirect(adminUrl('cars/view.php?id=' . $id));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            cleanupSavedImages($savedPaths);
            $errors[] = 'Хатогии сабт: ' . $e->getMessage();
        }
    }

    if ($errors !== []) {
        $existingImages = getCarImages($id);
    }
}

renderAdminHeader('Редактировать машину', 'cars');
?>

<section class="glass-card animate-in">
    <div class="card-head">
        <h2><?= e($car['name']) ?></h2>
        <div class="action-btns">
            <a href="<?= e(adminCarUrl('view.php', ['id' => $id])) ?>" class="btn-ghost sm btn-with-icon"><?= adminIcon('view') ?> Просмотр</a>
            <a href="<?= e(adminCarUrl('index.php')) ?>" class="btn-ghost sm btn-with-icon"><?= adminIcon('back') ?> Назад</a>
        </div>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-error">
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="car-form" id="editCarForm">
        <?= csrfField() ?>

        <p class="form-section-title">Основные данные</p>
        <div class="form-grid">
            <label class="field">
                <span>VinCode *</span>
                <input type="text" name="vin_code" maxlength="17" required value="<?= e($input['vin_code']) ?>">
            </label>
            <label class="field">
                <span>Название *</span>
                <input type="text" name="name" required value="<?= e($input['name']) ?>">
            </label>
            <label class="field full">
                <span>Описание</span>
                <textarea name="description" rows="3"><?= e($input['description']) ?></textarea>
            </label>
            <label class="field">
                <span>Дата приёма *</span>
                <input type="date" name="receive_date" required value="<?= e($input['receive_date']) ?>">
            </label>
            <label class="field">
                <span>Дата загрузки</span>
                <input type="date" name="upload_date" value="<?= e($input['upload_date']) ?>">
            </label>
            <label class="field">
                <span>Статус</span>
                <select name="status">
                    <?php foreach (carStatusLabels() as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $input['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Контакт</span>
                <input type="text" name="contact_name" value="<?= e($input['contact_name']) ?>">
            </label>
            <label class="field">
                <span>Телефон</span>
                <input type="text" name="contact_phone" value="<?= e($input['contact_phone']) ?>">
            </label>
            <label class="field full">
                <span>Заметки</span>
                <textarea name="notes" rows="2"><?= e($input['notes']) ?></textarea>
            </label>
        </div>

        <div class="upload-section">
            <h3>Текущие фото</h3>
            <input type="hidden" name="main_image_id" id="mainImageId" value="<?= !empty($existingImages) ? (int) $existingImages[0]['id'] : 0 ?>">
            <input type="hidden" name="main_new_index" id="mainNewIndex" value="-1">

            <?php if ($existingImages === []): ?>
                <p class="muted">Нет фото</p>
            <?php else: ?>
                <div class="preview-grid existing-images" id="existingImages">
                    <?php foreach ($existingImages as $index => $image): ?>
                        <div class="preview-item" data-id="<?= (int) $image['id'] ?>">
                            <img src="<?= e(carImageUrl($image['image_path']) ?? '') ?>" alt="">
                            <?php if ($index === 0): ?><span class="main-badge">Главное</span><?php endif; ?>
                            <div class="preview-actions">
                                <button type="button" class="btn-ghost xs set-main-existing" data-id="<?= (int) $image['id'] ?>">Главное</button>
                                <button type="button" class="btn-ghost xs move-left">←</button>
                                <button type="button" class="btn-ghost xs move-right">→</button>
                                <label class="delete-check">
                                    <input type="checkbox" name="delete_images[]" value="<?= (int) $image['id'] ?>">
                                    <span>Удалить</span>
                                </label>
                            </div>
                            <input type="hidden" name="image_order[]" value="<?= (int) $image['id'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="upload-head">
                <div>
                    <h3>Добавить фото</h3>
                    <p class="muted">Максимум <?= getMaxCarImages() ?> фото в сумме</p>
                </div>
                <label class="btn-primary sm upload-btn">
                    Выбрать
                    <input type="file" name="new_images[]" id="newImageInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple hidden>
                </label>
            </div>
            <div class="preview-grid" id="newPreviewGrid"></div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Сохранить изменения</button>
            <a href="<?= e(adminUrl('cars/view.php?id=' . $id)) ?>" class="btn-ghost">Отмена</a>
        </div>
    </form>
</section>

<script src="<?= e(adminUrl('assets/js/car-form.js')) ?>"></script>
<script>window.CAR_FORM_MODE = 'edit';</script>

<?php renderAdminFooter(); ?>
