<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';
require_once __DIR__ . '/../../includes/settings.php';

requireAuth();

$admin = getCurrentAdmin();
$errors = [];
$success = false;

$fields = [
    'bot_name'          => getSetting('bot_name', APP_NAME) ?? '',
    'welcome_message'   => getSetting('welcome_message', '') ?? '',
    'not_found_message' => getSetting('not_found_message', '') ?? '',
    'contact_phone'     => getSetting('contact_phone', '') ?? '',
    'max_car_images'    => getSetting('max_car_images', '5') ?? '5',
    'company_name'      => getSetting('company_name', APP_NAME) ?? '',
    'company_logo'      => getSetting('company_logo', '') ?? '',
];

$currentToken = getBotToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    foreach (['bot_name', 'welcome_message', 'not_found_message', 'contact_phone', 'company_name'] as $key) {
        $fields[$key] = trim($_POST[$key] ?? '');
    }

    $maxImages = (int) ($_POST['max_car_images'] ?? 5);
    $fields['max_car_images'] = (string) max(1, min(5, $maxImages));

    if ($fields['bot_name'] === '') {
        $errors[] = __('settings.error_bot_name');
    }

    if ($fields['company_name'] === '') {
        $errors[] = __('settings.error_company_name');
    }

    $newToken = trim($_POST['telegram_bot_token'] ?? '');

    if ($newToken !== '' && $newToken !== '••••••••') {
        setSetting('telegram_bot_token', $newToken);
        $currentToken = $newToken;
    }

    if (isset($_FILES['company_logo']) && ($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['company_logo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = __('settings.error_logo_upload');
        } elseif ($file['size'] > MAX_IMAGE_SIZE) {
            $errors[] = __('settings.error_logo_size');
        } else {
            $mime = mime_content_type($file['tmp_name']) ?: $file['type'];

            if (!in_array($mime, ALLOWED_IMAGE_MIMES, true)) {
                $errors[] = __('settings.error_logo_type');
            } else {
                $logoDir = APP_ROOT . '/uploads/settings';

                if (!is_dir($logoDir)) {
                    mkdir($logoDir, 0755, true);
                }

                $ext = match ($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    default      => 'png',
                };

                $filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $dest = $logoDir . DIRECTORY_SEPARATOR . $filename;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if ($fields['company_logo'] !== '') {
                        deleteCarImageFile($fields['company_logo']);
                    }
                    $fields['company_logo'] = 'uploads/settings/' . $filename;
                } else {
                    $errors[] = __('settings.error_logo_save');
                }
            }
        }
    }

    if ($errors === []) {
        $changes = [];

        foreach ($fields as $key => $value) {
            if ($key === 'company_logo' && $value === getSetting($key, '')) {
                continue;
            }
            setSetting($key, $value);
            $changes[] = $key;
        }

        if ($newToken !== '' && $newToken !== '••••••••') {
            $changes[] = 'telegram_bot_token';
        }

        logActivity(
            (int) $admin['id'],
            'settings_update',
            'settings',
            null,
            json_encode($changes, JSON_UNESCAPED_UNICODE)
        );

        flashSet('success', __('flash.settings_saved'));
        redirect(adminUrl('settings/index.php'));
    }
}

$logoUrl = $fields['company_logo'] ? resolveImagePublicUrl($fields['company_logo']) : null;

renderAdminHeader(__('settings.title'), 'settings');
?>

<section class="glass-card animate-in">
    <div class="card-head">
        <h2><?= e(__('settings.title')) ?></h2>
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

    <form method="post" enctype="multipart/form-data" class="car-form settings-form">
        <?= csrfField() ?>

        <div class="form-grid">
            <label class="field">
                <span><?= e(__('settings.bot_name')) ?></span>
                <input type="text" name="bot_name" required value="<?= e($fields['bot_name']) ?>">
            </label>

            <label class="field">
                <span><?= e(__('settings.company_name')) ?></span>
                <input type="text" name="company_name" required value="<?= e($fields['company_name']) ?>">
            </label>

            <label class="field">
                <span><?= e(__('settings.contact_phone')) ?></span>
                <input type="text" name="contact_phone" value="<?= e($fields['contact_phone']) ?>" placeholder="+992...">
            </label>

            <label class="field">
                <span><?= e(__('settings.max_images')) ?></span>
                <input type="number" name="max_car_images" min="1" max="5" value="<?= e($fields['max_car_images']) ?>">
            </label>

            <label class="field full">
                <span><?= e(__('settings.token')) ?></span>
                <input type="password" name="telegram_bot_token"
                       value="<?= $currentToken !== '' ? '••••••••' : '' ?>"
                       placeholder="Token-ро ворид кунед">
                <small class="muted">Мавҷуд: <?= e(maskToken($currentToken)) ?> — барои тағйир token-и нав нависед</small>
            </label>

            <label class="field full">
                <span><?= e(__('settings.welcome_message')) ?></span>
                <textarea name="welcome_message" rows="4" placeholder="{name}, {company}"><?= e($fields['welcome_message']) ?></textarea>
            </label>

            <label class="field full">
                <span><?= e(__('settings.not_found_message')) ?></span>
                <textarea name="not_found_message" rows="3" placeholder="{query}"><?= e($fields['not_found_message']) ?></textarea>
            </label>

            <label class="field full">
                <span><?= e(__('settings.logo')) ?></span>
                <?php if ($logoUrl): ?>
                    <div class="logo-preview">
                        <img src="<?= e($logoUrl) ?>" alt="Logo">
                    </div>
                <?php endif; ?>
                <input type="file" name="company_logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= e(__('settings.save')) ?></button>
        </div>
    </form>
</section>

<?php renderAdminFooter(); ?>
