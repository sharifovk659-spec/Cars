<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="tg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <h1><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
    <p>Лоиҳа омода аст.</p>
</body>
</html>
