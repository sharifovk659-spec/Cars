<?php

declare(strict_types=1);

require_once __DIR__ . '/settings.php';

/**
 * Validate Telegram WebApp initData
 * @return array<string, string>|null
 */
function validateTelegramInitData(string $initData, string $botToken): ?array
{
    if ($initData === '' || $botToken === '') {
        return null;
    }

    parse_str($initData, $params);

    $hash = $params['hash'] ?? '';
    unset($params['hash']);

    if ($hash === '') {
        return null;
    }

    ksort($params);
    $dataCheck = [];

    foreach ($params as $key => $value) {
        $dataCheck[] = $key . '=' . $value;
    }

    $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
    $calculated = hash_hmac('sha256', implode("\n", $dataCheck), $secretKey);

    if (!hash_equals($calculated, $hash)) {
        return null;
    }

    $authDate = (int) ($params['auth_date'] ?? 0);

    if ($authDate > 0 && (time() - $authDate) > 86400) {
        return null;
    }

    /** @var array<string, string> $params */
    return $params;
}

/**
 * @return array<string, mixed>|null
 */
function getTelegramUserFromInitData(string $initData, string $botToken): ?array
{
    $params = validateTelegramInitData($initData, $botToken);

    if ($params === null || empty($params['user'])) {
        return null;
    }

    /** @var array<string, mixed>|null $user */
    $user = json_decode($params['user'], true);

    return is_array($user) ? $user : null;
}

function requireTelegramInitData(): array
{
    $initData = $_SERVER['HTTP_X_TELEGRAM_INIT_DATA']
        ?? $_GET['initData']
        ?? $_POST['initData']
        ?? '';

    $token = getBotToken();

    if ($initData === '' || $token === '') {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $params = validateTelegramInitData($initData, $token);

    if ($params === null) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid initData'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $params;
}
