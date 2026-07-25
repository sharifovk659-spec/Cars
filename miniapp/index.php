<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/app.php';
$cssPath = __DIR__ . '/assets/css/style.css';
$jsCore = __DIR__ . '/assets/js/miniapp-core.js';
$jsApp = __DIR__ . '/assets/js/app.js';
$v = max(
    is_file($cssPath) ? filemtime($cssPath) : 1,
    is_file($jsCore) ? filemtime($jsCore) : 1,
    is_file($jsApp) ? filemtime($jsApp) : 1
);
?>
<!DOCTYPE html>
<html lang="tg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Telegram Cars</title>
    <link rel="preconnect" href="<?= htmlspecialchars(rtrim(APP_URL, '/'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int) $v ?>">
</head>
<body>
    <div id="app" data-page="home">
        <div id="state-loading" class="state-screen hidden">
            <div class="loader"></div>
            <p>Боркунӣ...</p>
        </div>

        <div id="state-preview" class="state-screen hidden">
            <div class="preview-card surface-card">
                <div class="preview-icon">⚡</div>
                <h2>Режими санҷиш</h2>
                <p>Mini App-ро дар Telegram кушоед.</p>
                <a class="btn-primary" href="https://t.me/InovaCarsBot" target="_blank" rel="noopener">@InovaCarsBot</a>
            </div>
        </div>

        <div id="state-error" class="state-screen hidden">
            <div class="state-icon error">!</div>
            <h2>Хатогӣ</h2>
            <p id="error-text"></p>
        </div>

        <div id="state-not-found" class="state-screen hidden">
            <div class="state-icon">🔍</div>
            <h2>Мошин ёфт нашуд</h2>
            <button type="button" class="btn-primary" id="back-search-btn">Боз кӯшиш</button>
        </div>

        <div id="state-search" class="screen">
            <div class="hero neon-hero">
                <img id="company-logo" class="company-logo hidden" alt="">
                <h1 id="company-name">Telegram Cars</h1>
                <p>VIN Code ё 5 рақами охиринро ворид кунед</p>
            </div>
            <form id="search-form" class="search-form">
                <input type="text" id="search-input" placeholder="VIN Code" autocomplete="off" maxlength="17">
                <button type="submit" class="btn-primary neon-btn">Ҷустуҷӯ</button>
            </form>
        </div>
    </div>
    <script src="assets/js/miniapp-core.js?v=<?= (int) $v ?>"></script>
    <script src="assets/js/app.js?v=<?= (int) $v ?>"></script>
</body>
</html>
