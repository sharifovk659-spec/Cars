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
    flashSet('error', __('cars.invalid_id'));
    redirect(adminCarUrl('index.php'));
}

$stmt = db()->prepare('SELECT * FROM cars WHERE id = :id AND deleted_at IS NULL');
$stmt->execute(['id' => $id]);
$car = $stmt->fetch();

if (!$car) {
    flashSet('error', __('cars.not_found'));
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
    'upload_number' => (string) ($car['upload_number'] ?? ''),
    'vagon'         => (string) ($car['vagon'] ?? ''),
    'treiler'       => (string) ($car['treiler'] ?? ''),
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
            $errors[] = __('cars.image_delete_not_found');
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
        $errors[] = __('cars.min_photos_remain');
    }

    if ($totalImages > getMaxCarImages()) {
        $errors[] = __('cars.max_photos', ['max' => (string) getMaxCarImages()]);
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

            flashSet('success', __('cars.edit_success'));
            redirect(adminUrl('cars/view.php?id=' . $id));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            cleanupSavedImages($savedPaths);
            $errors[] = __('cars.save_error', ['message' => $e->getMessage()]);
        }
    }

    if ($errors !== []) {
        $existingImages = getCarImages($id);
    }
}

renderAdminHeader(__('cars.edit_title'), 'cars');
?>

<section class="glass-card animate-in car-form-page">
    <div class="card-head">
        <h2><?= e($car['name']) ?></h2>
        <div class="action-btns">
            <a href="<?= e(adminCarUrl('view.php', ['id' => $id])) ?>" class="btn-ghost sm btn-with-icon"><?= adminIcon('view') ?> <?= e(__('cars.edit_view')) ?></a>
            <a href="<?= e(adminCarUrl('index.php')) ?>" class="btn-ghost sm btn-with-icon"><?= adminIcon('back') ?> <?= e(__('cars.edit_back')) ?></a>
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
        <?php require __DIR__ . '/../includes/car_form_fields.php'; ?>

        <div class="upload-section">
            <h3><?= e(carFieldLabel('photos')) ?></h3>
            <input type="hidden" name="main_image_id" id="mainImageId" value="<?= !empty($existingImages) ? (int) $existingImages[0]['id'] : 0 ?>">
            <input type="hidden" name="main_new_index" id="mainNewIndex" value="-1">

            <?php if ($existingImages === []): ?>
                <p class="muted"><?= e(__('cars.edit_no_photos')) ?></p>
            <?php else: ?>
                <div class="preview-grid existing-images" id="existingImages">
                    <?php foreach ($existingImages as $index => $image): ?>
                        <div class="preview-item<?= $index === 0 ? ' is-main' : '' ?>" data-id="<?= (int) $image['id'] ?>">
                            <img src="<?= e(carImageUrl($image['image_path']) ?? '') ?>" alt="">
                            <span class="main-badge"><?= e(__('js.main_photo')) ?></span>
                            <div class="preview-actions">
                                <button type="button" class="btn-ghost xs set-main-existing set-main-btn" data-id="<?= (int) $image['id'] ?>"><?= e(__('js.main_photo')) ?></button>
                                <button type="button" class="btn-ghost xs move-left" title="<?= e(__('js.move_back')) ?>">←</button>
                                <button type="button" class="btn-ghost xs move-right" title="<?= e(__('js.move_forward')) ?>">→</button>
                                <label class="delete-check">
                                    <input type="checkbox" name="delete_images[]" value="<?= (int) $image['id'] ?>">
                                    <span><?= e(__('cars.edit_delete')) ?></span>
                                </label>
                            </div>
                            <input type="hidden" name="image_order[]" value="<?= (int) $image['id'] ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="upload-head">
                <div>
                    <h3><?= e(__('cars.edit_add_photos')) ?></h3>
                    <p class="muted"><?= e(__('cars.edit_add_photos_hint', ['max' => (string) getMaxCarImages()])) ?></p>
                </div>
                <label class="btn-primary sm upload-btn">
                    <?= e(__('cars.add_upload_btn')) ?>
                    <input type="file" name="new_images[]" id="newImageInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple hidden>
                </label>
            </div>
            <div class="preview-grid" id="newPreviewGrid"></div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= e(__('cars.edit_save')) ?></button>
            <a href="<?= e(adminUrl('cars/view.php?id=' . $id)) ?>" class="btn-ghost"><?= e(__('btn.cancel')) ?></a>
        </div>
    </form>
</section>

<script>window.ADMIN_VIN_LOOKUP_URL = <?= json_encode(adminUrl('api/vin-lookup.php'), JSON_UNESCAPED_UNICODE) ?>;</script>
<script>window.CAR_FORM_MODE = 'edit';</script>
<script src="<?= e(adminUrl('assets/js/car-form.js?v=' . (is_file(__DIR__ . '/../assets/js/car-form.js') ? filemtime(__DIR__ . '/../assets/js/car-form.js') : '1'))) ?>"></script>

<?php renderAdminFooter(); ?>
