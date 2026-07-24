<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';

requireAuth();

$admin = getCurrentAdmin();
$errors = [];
$input = [
    'vin_code'      => '',
    'name'          => '',
    'description'   => '',
    'receive_date'  => date('Y-m-d'),
    'upload_date'   => '',
    'status'        => 'available',
    'contact_name'  => '',
    'contact_phone' => '',
    'notes'         => '',
];

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

<section class="glass-card animate-in">
    <div class="card-head">
        <h2>Новая машина</h2>
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

        <div class="form-grid">
            <label class="field">
                <span>VinCode *</span>
                <input type="text" name="vin_code" maxlength="17" required
                       value="<?= e($input['vin_code']) ?>" placeholder="1HGBH41JXMN109186">
            </label>

            <label class="field">
                <span>Название машины *</span>
                <input type="text" name="name" required value="<?= e($input['name']) ?>" placeholder="Toyota Camry 2020">
            </label>

            <label class="field full">
                <span>Описание</span>
                <textarea name="description" rows="3" placeholder="Краткое описание..."><?= e($input['description']) ?></textarea>
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
                <input type="text" name="contact_name" value="<?= e($input['contact_name']) ?>" placeholder="Имя">
            </label>

            <label class="field">
                <span>Телефон</span>
                <input type="text" name="contact_phone" value="<?= e($input['contact_phone']) ?>" placeholder="+992...">
            </label>

            <label class="field full">
                <span>Заметки</span>
                <textarea name="notes" rows="2" placeholder="Дополнительные заметки..."><?= e($input['notes']) ?></textarea>
            </label>
        </div>

        <div class="upload-section">
            <div class="upload-head">
                <div>
                    <h3>Фотографии (1–5) *</h3>
                    <p class="muted">JPG, PNG, WEBP — максимум 5 MB каждая</p>
                </div>
                <label class="btn-primary sm upload-btn">
                    Выбрать фото
                    <input type="file" name="images[]" id="imageInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple hidden>
                </label>
            </div>

            <input type="hidden" name="main_image" id="mainImageInput" value="0">
            <div class="preview-grid" id="previewGrid"></div>
            <p class="preview-hint muted" id="previewHint">Выберите фото для предпросмотра и отметьте главное</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Сохранить машину</button>
            <a href="<?= e(adminUrl('cars/index.php')) ?>" class="btn-ghost">Отмена</a>
        </div>
    </form>
</section>

<script src="<?= e(adminUrl('assets/js/car-form.js')) ?>"></script>

<?php renderAdminFooter(); ?>
