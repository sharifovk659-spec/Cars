<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/telegram.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'mini_app_home'      => miniAppHomeUrl(),
    'mini_app_car_example' => miniAppCarUrl('90775'),
    'menu_button_text'   => miniAppMenuButtonText(),
    'menu_button_url'    => miniAppHomeUrl(),
    'web_app_car_format' => APP_URL . '/miniapp/car.php?vin={VIN}',
    'bot_setup'          => APP_URL . '/bot/set_menu_button.php',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
