<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';

requireAuth();

$admin = getCurrentAdmin();
$errors = [];
$input = carDefaultFormInput();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $input = carFormInput();
    $errors = validateCarForm($input);

    $uploads = normalizeUploadedImages($_FILES['images'] ?? []);
    $errors = array_merge($errors, $uploads['errors']);

    $mainIndex = max(0, (int) ($_POST['main_image'] ?? 0));

    if ($mainIndex >= count($uploads['files'])) {
        $mainIndex = 0;
    }

    if ($errors === []) {
        $savedPaths = [];
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $savedPaths = saveUploadedImages($uploads['files']);
            $carId = insertCarRecord($pdo, $input);
            insertCarImages($pdo, $carId, $savedPaths, $mainIndex);

            $pdo->commit();

            logActivity(
                (int) $admin['id'],
                'car_create',
                'car',
                $carId,
                json_encode(['vin' => $input['vin_code'], 'images' => count($savedPaths)], JSON_UNESCAPED_UNICODE)
            );

            flashSet('success', 'Мошин бо муваффақият илова шуд');
            redirect(adminUrl('cars/index.php'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            cleanupSavedImages($savedPaths);
            $errors[] = 'Хатогии сабт: ' . $e->getMessage();
        }
    }
}

renderAdminHeader('Добавить машину', 'cars-add');
?>

<section class="glass-card animate-in car-form-page">
    <div class="card-head">
        <h2>Мошини нав</h2>
        <a href="<?= e(adminUrl('cars/index.php')) ?>" class="btn-ghost sm">← Назад</a>
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

    <form method="post" enctype="multipart/form-data" class="car-form" id="carForm">
        <?= csrfField() ?>
        <?php require __DIR__ . '/../includes/car_form_fields.php'; ?>

        <div class="upload-section">
            <div class="upload-head">
                <div>
                    <h3><?= e(carFieldLabel('photos')) ?> (1–5) *</h3>
                    <p class="muted">JPG, PNG, WEBP — максимум 5 MB</p>
                </div>
                <label class="btn-primary sm upload-btn">
                    Интихоб кардан
                    <input type="file" name="images[]" id="imageInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple hidden>
                </label>
            </div>
            <input type="hidden" name="main_image" id="mainImageInput" value="0">
            <div class="preview-grid" id="previewGrid"></div>
            <p class="preview-hint muted" id="previewHint">Суратҳоро интихоб кунед ва асосиро нишон диҳед</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Сохранить машину</button>
            <a href="<?= e(adminUrl('cars/index.php')) ?>" class="btn-ghost">Отмена</a>
        </div>
    </form>
</section>

<?php $carFormJs = __DIR__ . '/../assets/js/car-form.js'; ?>
<script src="<?= e(adminUrl('assets/js/car-form.js?v=' . (is_file($carFormJs) ? filemtime($carFormJs) : '1'))) ?>"></script>

<?php renderAdminFooter(); ?>
