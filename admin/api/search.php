<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/search.php';
require_once __DIR__ . '/../includes/ui.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

$type = trim((string) ($_GET['type'] ?? 'vin'));
if (!in_array($type, adminSearchTypes(), true)) {
    $type = 'vin';
}

$rawQuery = (string) ($_GET['q'] ?? '');
$prepared = prepareAdminSearchQuery($type, $rawQuery);

if (!$prepared['ok']) {
    $messages = [
        'empty'        => __('dashboard.search_err_empty'),
        'short'        => __('dashboard.search_err_short'),
        'digits_only'  => __('dashboard.search_err_digits'),
        'digits_short' => __('dashboard.search_err_digits_short'),
    ];
    $errorKey = (string) ($prepared['error'] ?? 'empty');

    echo json_encode([
        'ok'      => false,
        'error'   => $errorKey,
        'message' => $messages[$errorKey] ?? __('dashboard.search_err_empty'),
        'query'   => $prepared['query'],
        'type'    => $prepared['type'],
        'count'   => 0,
        'cars'    => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $cars = searchAdminCars(db(), $prepared['query'], 15, $prepared['type']);
    $payload = array_map(static fn (array $car): array => adminSearchCarPayload($car), $cars);

    echo json_encode([
        'ok'      => true,
        'error'   => null,
        'message' => null,
        'query'   => $prepared['query'],
        'type'    => $prepared['type'],
        'count'   => count($payload),
        'cars'    => $payload,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'error'   => 'server',
        'message' => __('dashboard.search_err_server'),
        'query'   => $prepared['query'],
        'type'    => $prepared['type'],
        'count'   => 0,
        'cars'    => [],
    ], JSON_UNESCAPED_UNICODE);
}
