<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/settings.php';

/** @return array<string, string> */
function carFormInput(): array
{
    return [
        'vin_code'      => strtoupper(trim($_POST['vin_code'] ?? '')),
        'name'          => trim($_POST['name'] ?? ''),
        'description'   => trim($_POST['description'] ?? ''),
        'receive_location' => trim($_POST['receive_location'] ?? 'sharjah'),
        'receive_date'  => trim($_POST['receive_date'] ?? ''),
        'upload_date'   => trim($_POST['upload_date'] ?? ''),
        'upload_number' => trim($_POST['upload_number'] ?? ''),
        'vagon'         => trim($_POST['vagon'] ?? ''),
        'treiler'       => trim($_POST['treiler'] ?? ''),
        'status'        => trim($_POST['status'] ?? 'available'),
        'contact_name'  => trim($_POST['contact_name'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'notes'         => trim($_POST['notes'] ?? ''),
    ];
}

/**
 * @return list<string>
 */
function validateCarForm(array $input, ?int $excludeCarId = null): array
{
    $errors = [];

    if ($input['vin_code'] === '') {
        $errors[] = __('validation.vin_required');
    } elseif (strlen($input['vin_code']) > 17) {
        $errors[] = __('validation.vin_max');
    } else {
        $sql = 'SELECT id FROM cars WHERE vin_code = :vin AND deleted_at IS NULL';
        $params = ['vin' => $input['vin_code']];

        if ($excludeCarId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeCarId;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            $errors[] = __('validation.vin_duplicate');
        }
    }

    if ($input['name'] === '') {
        $errors[] = __('validation.name_required');
    }

    if (!array_key_exists($input['receive_location'], carReceiveLocations())) {
        $errors[] = __('validation.location_invalid');
    }

    if ($input['receive_date'] === '') {
        $errors[] = __('validation.receive_required', ['field' => carFieldLabel('receive_date')]);
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['receive_date'])) {
        $errors[] = __('validation.receive_invalid');
    }

    if ($input['upload_date'] !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['upload_date'])) {
            $errors[] = __('validation.upload_invalid');
        } elseif ($input['receive_date'] !== '' && $input['upload_date'] < $input['receive_date']) {
            $errors[] = __('validation.upload_before_receive');
        }
    }

    if (!array_key_exists($input['status'], carStatusLabels())) {
        $errors[] = __('validation.status_invalid');
    }

    return $errors;
}

/**
 * @param array<string, mixed> $files $_FILES['images']
 * @return array{files: list<array{name: string, type: string, tmp_name: string, error: int, size: int}>, errors: list<string>}
 */
function normalizeUploadedImages(array $files): array
{
    $normalized = [];
    $errors = [];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return ['files' => [], 'errors' => [__('validation.min_photo')]];
    }

    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $normalized[] = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => (int) $files['error'][$i],
            'size'     => (int) $files['size'][$i],
        ];
    }

    if ($normalized === []) {
        $errors[] = __('validation.min_photo');
    } elseif (count($normalized) > getMaxCarImages()) {
        $errors[] = __('validation.max_photos', ['max' => (string) getMaxCarImages()]);
    }

    foreach ($normalized as $index => $file) {
        $num = (string) ($index + 1);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = __('validation.photo_upload_error', ['num' => $num]);
            continue;
        }

        if ($file['size'] > MAX_IMAGE_SIZE) {
            $errors[] = __('validation.photo_size', ['num' => $num]);
        }

        $mime = mime_content_type($file['tmp_name']) ?: $file['type'];

        if (!in_array($mime, ALLOWED_IMAGE_MIMES, true)) {
            $errors[] = __('validation.photo_type', ['num' => $num]);
            continue;
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $errors[] = __('validation.photo_invalid', ['num' => $num]);
        }
    }

    return ['files' => $normalized, 'errors' => $errors];
}

/**
 * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
 */
function storeCarImageFile(array $file): string
{
    $mime = mime_content_type($file['tmp_name']) ?: $file['type'];
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => throw new RuntimeException(__('validation.photo_format')),
    };

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new RuntimeException(__('validation.photo_invalid', ['num' => '1']));
    }

    if (!is_dir(UPLOADS_PATH)) {
        mkdir(UPLOADS_PATH, 0755, true);
    }

    do {
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $fullPath = UPLOADS_PATH . DIRECTORY_SEPARATOR . $filename;
    } while (file_exists($fullPath));

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        throw new RuntimeException(__('validation.photo_save_failed'));
    }

    return 'uploads/cars/' . $filename;
}

function deleteCarImageFile(?string $path): void
{
    if ($path === null || $path === '') {
        return;
    }

    $fullPath = APP_ROOT . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

/**
 * @param list<array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
 * @return list<string> saved relative paths
 */
function saveUploadedImages(array $files): array
{
    $paths = [];

    foreach ($files as $file) {
        $paths[] = storeCarImageFile($file);
    }

    return $paths;
}

/**
 * @param list<string> $paths
 */
function insertCarImages(PDO $pdo, int $carId, array $paths, int $mainIndex = 0): void
{
    if ($paths === []) {
        return;
    }

    $ordered = $paths;
    if ($mainIndex > 0 && $mainIndex < count($ordered)) {
        $main = $ordered[$mainIndex];
        unset($ordered[$mainIndex]);
        array_unshift($ordered, $main);
        $ordered = array_values($ordered);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO car_images (car_id, image_path, sort_order) VALUES (:car_id, :image_path, :sort_order)'
    );

    foreach ($ordered as $index => $path) {
        $stmt->execute([
            'car_id'     => $carId,
            'image_path' => $path,
            'sort_order' => $index + 1,
        ]);
    }
}

/** @return list<array{id: int, image_path: string, sort_order: int}> */
function getCarImages(int $carId): array
{
    $stmt = db()->prepare(
        'SELECT id, image_path, sort_order FROM car_images WHERE car_id = :id ORDER BY sort_order ASC'
    );
    $stmt->execute(['id' => $carId]);

    return $stmt->fetchAll();
}

function nullIfEmpty(?string $value): ?string
{
    return ($value === null || $value === '') ? null : $value;
}

/**
 * @param array<string, string> $input
 */
function insertCarRecord(PDO $pdo, array $input): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO cars (vin_code, name, description, receive_location, receive_date, upload_date, upload_number, vagon, treiler, status, contact_name, contact_phone, notes)
         VALUES (:vin_code, :name, :description, :receive_location, :receive_date, :upload_date, :upload_number, :vagon, :treiler, :status, :contact_name, :contact_phone, :notes)'
    );

    $stmt->execute([
        'vin_code'      => $input['vin_code'],
        'name'          => $input['name'],
        'description'   => nullIfEmpty($input['description']),
        'receive_location' => $input['receive_location'],
        'receive_date'  => $input['receive_date'],
        'upload_date'   => nullIfEmpty($input['upload_date']),
        'upload_number' => nullIfEmpty($input['upload_number']),
        'vagon'         => nullIfEmpty($input['vagon']),
        'treiler'       => nullIfEmpty($input['treiler']),
        'status'        => $input['status'],
        'contact_name'  => nullIfEmpty($input['contact_name']),
        'contact_phone' => nullIfEmpty($input['contact_phone']),
        'notes'         => nullIfEmpty($input['notes']),
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string, string> $input
 */
function updateCarRecord(PDO $pdo, int $carId, array $input): void
{
    $stmt = $pdo->prepare(
        'UPDATE cars SET
            vin_code = :vin_code,
            name = :name,
            description = :description,
            receive_location = :receive_location,
            receive_date = :receive_date,
            upload_date = :upload_date,
            upload_number = :upload_number,
            vagon = :vagon,
            treiler = :treiler,
            status = :status,
            contact_name = :contact_name,
            contact_phone = :contact_phone,
            notes = :notes
         WHERE id = :id AND deleted_at IS NULL'
    );

    $stmt->execute([
        'vin_code'      => $input['vin_code'],
        'name'          => $input['name'],
        'description'   => nullIfEmpty($input['description']),
        'receive_location' => $input['receive_location'],
        'receive_date'  => $input['receive_date'],
        'upload_date'   => nullIfEmpty($input['upload_date']),
        'upload_number' => nullIfEmpty($input['upload_number']),
        'vagon'         => nullIfEmpty($input['vagon']),
        'treiler'       => nullIfEmpty($input['treiler']),
        'status'        => $input['status'],
        'contact_name'  => nullIfEmpty($input['contact_name']),
        'contact_phone' => nullIfEmpty($input['contact_phone']),
        'notes'         => nullIfEmpty($input['notes']),
        'id'            => $carId,
    ]);
}

/**
 * @param list<int> $orderedImageIds
 */
function reorderExistingImages(PDO $pdo, int $carId, array $orderedImageIds): void
{
    $stmt = $pdo->prepare(
        'UPDATE car_images SET sort_order = :sort_order WHERE id = :id AND car_id = :car_id'
    );

    foreach ($orderedImageIds as $index => $imageId) {
        $stmt->execute([
            'sort_order' => $index + 1,
            'id'         => $imageId,
            'car_id'     => $carId,
        ]);
    }
}

function cleanupSavedImages(array $paths): void
{
    foreach ($paths as $path) {
        deleteCarImageFile($path);
    }
}
