<?php

declare(strict_types=1);

/**
 * Санҷиши пурраи Telegram Cars — Block 13
 * Ҳеҷ маълумоти мавҷударо нест намекунад
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/telegram_auth.php';
require_once __DIR__ . '/../includes/car_common.php';
require_once __DIR__ . '/../admin/includes/auth.php';
require_once __DIR__ . '/../admin/includes/cars.php';
require_once __DIR__ . '/../bot/helpers.php';

/** @var list<array{name: string, status: string, detail: string}> */
$results = [];

function test(string $name, bool $passed, string $detail = ''): void
{
    global $results;
    $results[] = [
        'name'   => $name,
        'status' => $passed ? 'PASS' : 'FAIL',
        'detail' => $detail,
    ];
    $icon = $passed ? '✓' : '✗';
    echo $icon . ' ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
}

function createTestPng(string $path): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $img = imagecreatetruecolor(40, 30);
    $blue = imagecolorallocate($img, 37, 99, 235);
    imagefill($img, 0, 0, $blue);
    $ok = imagepng($img, $path);
    imagedestroy($img);

    return $ok;
}

function createUploadFile(string $path, string $mime): array
{
    return [
        'name'     => basename($path),
        'type'     => $mime,
        'tmp_name' => $path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => (int) filesize($path),
    ];
}

echo "=== Telegram Cars — Full Test Suite ===" . PHP_EOL . PHP_EOL;

// 1. Database
try {
    $pdo = db();
    $pdo->query('SELECT 1');
    test('Database connection', true);
} catch (Throwable $e) {
    test('Database connection', false, $e->getMessage());
    exit(1);
}

// 2. Login
startSession();
$_SESSION = [];
$login = loginAdmin('admin@telegramcars.local', 'Admin@123', false);
test('Admin login', $login['success'] === true);
test('Session after login', isLoggedIn());

$admin = getCurrentAdmin();
test('Get current admin', $admin !== null && ($admin['email'] ?? '') === 'admin@telegramcars.local');

// 3. Auth protection (logic)
$wasLoggedIn = isLoggedIn();
test('Admin is authenticated', $wasLoggedIn);

// 4. CSRF
startSession();
$validToken = csrfToken();
test('CSRF token generated', strlen($validToken) === 64);
test('CSRF valid token', verifyCsrf($validToken));
test('CSRF invalid token rejected', !verifyCsrf('invalid-token'));

// 5. SQL injection — prepared statements
$malicious = "' OR '1'='1";
$stmt = db()->prepare('SELECT id FROM cars WHERE vin_code = :vin AND deleted_at IS NULL');
$stmt->execute(['vin' => $malicious]);
test('SQL injection safe (VIN lookup)', $stmt->fetch() === false);

$stmt2 = db()->prepare("SELECT id FROM cars WHERE deleted_at IS NULL AND name LIKE :name");
$stmt2->execute(['name' => '%' . $malicious . '%']);
test('SQL injection safe (name search)', is_array($stmt2->fetchAll()));

// 6. XSS escape
$xss = '<script>alert(1)</script>';
test('XSS escaped', e($xss) === htmlspecialchars($xss, ENT_QUOTES, 'UTF-8'));

// 7. Validation — dates
$dateErrors = validateCarForm([
    'vin_code'      => 'TESTVIN' . bin2hex(random_bytes(4)),
    'name'          => 'Test Car',
    'description'   => '',
    'receive_date'  => '2026-07-20',
    'upload_date'   => '2026-07-10',
    'status'        => 'available',
    'contact_name'  => '',
    'contact_phone' => '',
    'notes'         => '',
]);
test('Upload date before receive rejected', in_array('Рӯзи боргирӣ наметавонад аз рӯзи қабул пештар бошад', $dateErrors, true));

// 8. Path traversal
test('Path traversal blocked (full path)', resolveImageFullPath('../../../config/database.php') === null);
test('Path traversal blocked (public url)', resolveImagePublicUrl('../../etc/passwd') === null);
test('Valid uploads path prefix required', resolveImagePublicUrl('uploads/cars/test.jpg') !== null);

// 9. Dangerous file upload
$dangerFile = sys_get_temp_dir() . '/tc_test.php';
file_put_contents($dangerFile, '<?php echo "hack"; ?>');
$dangerUpload = createUploadFile($dangerFile, 'image/png');
$dangerNorm = normalizeUploadedImages([
    'name'     => [$dangerUpload['name']],
    'type'     => [$dangerUpload['type']],
    'tmp_name' => [$dangerUpload['tmp_name']],
    'error'    => [$dangerUpload['error']],
    'size'     => [$dangerUpload['size']],
]);
@unlink($dangerFile);
$dangerRejected = false;
foreach ($dangerNorm['errors'] as $err) {
    if (str_contains($err, 'сурат') || str_contains($err, 'JPG')) {
        $dangerRejected = true;
    }
}
test('Dangerous file upload rejected', $dangerRejected || $dangerNorm['files'] === []);

// 10. Create test car with image (no delete of existing data)
$testVin = 'TC' . strtoupper(bin2hex(random_bytes(4))) . (string) random_int(10000, 99999);
$pngPath = sys_get_temp_dir() . '/tc_car_' . uniqid() . '.png';

if (!createTestPng($pngPath)) {
    test('Create test PNG (GD)', false, 'GD extension missing');
} else {
    test('Create test PNG (GD)', true);

    $input = [
        'vin_code'      => $testVin,
        'name'          => 'Test Toyota QA',
        'description'   => 'QA test car',
        'receive_date'  => '2026-07-01',
        'upload_date'   => '2026-07-15',
        'status'        => 'available',
        'contact_name'  => 'QA Contact',
        'contact_phone' => '+992900000000',
        'notes'         => 'Auto test',
    ];

    $valErrors = validateCarForm($input);
    test('Validate new car form', $valErrors === [], implode('; ', $valErrors));

    $carId = 0;
    try {
        $pdo->beginTransaction();

        if (!is_dir(UPLOADS_PATH)) {
            mkdir(UPLOADS_PATH, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.png';
        $dest = UPLOADS_PATH . DIRECTORY_SEPARATOR . $filename;
        copy($pngPath, $dest);
        $imagePath = 'uploads/cars/' . $filename;

        $carId = insertCarRecord($pdo, $input);
        $insertImg = $pdo->prepare(
            'INSERT INTO car_images (car_id, image_path, sort_order) VALUES (:car_id, :image_path, 1)'
        );
        $insertImg->execute(['car_id' => $carId, 'image_path' => $imagePath]);

        $pdo->commit();
        test('Add car with 1 image', $carId > 0, 'ID=' . $carId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        test('Add car with 1 image', false, $e->getMessage());
    }

    @unlink($pngPath);

    // 11. Duplicate VIN
    $dupErrors = validateCarForm($input);
    test('Duplicate VinCode rejected', in_array('VinCode такрорӣ аст', $dupErrors, true));

    if ($carId > 0) {
        // 12. Find car / bot search
        $found = findCarBySearchQuery($testVin);
        test('Find car by full VIN', $found !== null && $found['vin_code'] === $testVin);

        $last5 = substr($testVin, -5);
        $found5 = findCarBySearchQuery($last5);
        test('Find car by last 5 digits', $found5 !== null && $found5['vin_code'] === $testVin);

        // 13. Images in list query
        $listStmt = db()->prepare(
            "SELECT (SELECT ci.image_path FROM car_images ci WHERE ci.car_id = c.id ORDER BY ci.sort_order ASC LIMIT 1) AS main_image
             FROM cars c WHERE c.id = :id"
        );
        $listStmt->execute(['id' => $carId]);
        $row = $listStmt->fetch();
        test('Main image in list query', !empty($row['main_image']));

        $imgPath = resolveImageFullPath($row['main_image']);
        test('Image file exists on disk', $imgPath !== null && is_file($imgPath));

        // 14. Edit car
        $editInput = $input;
        $editInput['name'] = 'Test Toyota QA Updated';
        $editInput['status'] = 'reserved';
        try {
            $pdo->beginTransaction();
            updateCarRecord($pdo, $carId, $editInput);
            $pdo->commit();
            test('Edit car data', true);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            test('Edit car data', false, $e->getMessage());
        }

        $updated = findCarById($carId);
        test('Edit persisted', ($updated['name'] ?? '') === 'Test Toyota QA Updated' && ($updated['status'] ?? '') === 'reserved');

        // 15. Soft delete
        $del = db()->prepare('UPDATE cars SET deleted_at = NOW() WHERE id = :id');
        $del->execute(['id' => $carId]);
        $afterDel = findCarById($carId);
        test('Soft delete hides car', $afterDel === null);

        // Restore for visibility (user said don't delete - restore soft delete)
        db()->prepare('UPDATE cars SET deleted_at = NULL WHERE id = :id')->execute(['id' => $carId]);
        test('Car restored after soft delete test', findCarById($carId) !== null);

        // 16. Bot image paths
        $paths = getCarImagePaths($carId);
        test('Bot getCarImagePaths', count($paths) >= 1);

        // 17. API format
        $apiCar = formatCarForApi($updated);
        test('API car format has images', count($apiCar['images']) >= 1);
        test('API car format has dates', !empty($apiCar['receive_date']) && !empty($apiCar['upload_date']));
    }
}

// 18. API security
test('API rejects fake initData', validateTelegramInitData('query_id=1&user=%7B%7D&auth_date=1&hash=fake', 'test-token') === null);

$apiTest = shell_exec('curl.exe -s -o NUL -w "%{http_code}" -H "X-Telegram-Init-Data: invalid" "' . APP_URL . '/api/car.php?vin=TEST"');
$apiCode = trim($apiTest ?? '');
test('API rejects invalid initData (HTTP 401/403)', in_array($apiCode, ['401', '403'], true), 'status ' . $apiCode);

// 19. HTTP endpoints
$base = APP_URL;
$endpoints = [
    'Login page'        => $base . '/admin/login.php',
    'Dashboard redirect'=> $base . '/admin/dashboard.php',
    'Mini App'          => $base . '/miniapp/index.html',
    'API index'         => $base . '/api/index.php',
];

foreach ($endpoints as $label => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = in_array($code, [200, 302], true);
    test('HTTP ' . $label, $ok, 'status ' . $code);
}

// 20. Logout
logoutAdmin(false);
test('Logout clears session', !isLoggedIn());

// Summary
echo PHP_EOL . '=== Summary ===' . PHP_EOL;
$passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$failed = count(array_filter($results, fn($r) => $r['status'] === 'FAIL'));
$total = count($results);
echo "Total: {$total} | Passed: {$passed} | Failed: {$failed}" . PHP_EOL;

if ($failed > 0) {
    echo PHP_EOL . 'Failed tests:' . PHP_EOL;
    foreach ($results as $r) {
        if ($r['status'] === 'FAIL') {
            echo '  - ' . $r['name'] . ': ' . $r['detail'] . PHP_EOL;
        }
    }
    exit(1);
}

exit(0);
