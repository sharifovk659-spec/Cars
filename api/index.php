<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'service' => 'api',
    'version' => '1.0',
    'status'  => 'ready',
], JSON_UNESCAPED_UNICODE);
