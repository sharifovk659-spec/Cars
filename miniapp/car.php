<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/telegram.php';

$vin = strtoupper(trim($_GET['vin'] ?? ''));
$pageTitle = $vin !== '' ? 'Мошин ' . $vin : 'Мошин';
$cssPath = __DIR__ . '/assets/css/style.css';
$jsCorePath = __DIR__ . '/assets/js/miniapp-core.js';
$jsCarPath = __DIR__ . '/assets/js/car.js';
$assetVer = max(
    is_file($cssPath) ? filemtime($cssPath) : 1,
    is_file($jsCorePath) ? filemtime($jsCorePath) : 1,
    is_file($jsCarPath) ? filemtime($jsCarPath) : 1
);
?>
<!DOCTYPE html>
<html lang="tg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — Telegram Cars</title>
    <link rel="preconnect" href="<?= htmlspecialchars(rtrim(APP_URL, '/'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int) $assetVer ?>">
</head>
<body>
    <div id="app" data-page="car" data-vin="<?= htmlspecialchars($vin, ENT_QUOTES, 'UTF-8') ?>">
        <div id="state-loading" class="state-screen">
            <div class="loader"></div>
            <p>Боркунӣ...</p>
        </div>

        <div id="state-preview" class="state-screen hidden">
            <div class="preview-card surface-card">
                <div class="preview-icon">⚡</div>
                <h2>Режими санҷиш</h2>
                <p>Mini App-ро дар Telegram кушед.</p>
                <code class="vin-code preview-vin" id="preview-vin"></code>
                <a class="btn-primary" id="preview-bot-link" href="https://t.me/InovaCarsBot" target="_blank" rel="noopener">@InovaCarsBot</a>
            </div>
        </div>

        <div id="state-error" class="state-screen hidden">
            <div class="state-icon error">!</div>
            <h2>Хатогӣ</h2>
            <p id="error-text"></p>
            <button type="button" class="btn-primary" id="retry-btn">Дубора кӯшиш</button>
        </div>

        <div id="state-not-found" class="state-screen hidden">
            <div class="state-icon">🔍</div>
            <h2>Мошин ёфт нашуд</h2>
            <p>VIN: <code id="not-found-vin"></code></p>
            <button type="button" class="btn-primary" id="back-search-btn">Ҷустуҷӯ</button>
        </div>

        <div id="state-car" class="screen hidden">
            <div class="car-hero">
                <div class="gallery-wrap">
                    <div class="gallery-track" id="gallery-track"></div>
                    <div class="gallery-dots" id="gallery-dots"></div>
                    <div class="gallery-counter" id="gallery-counter">1 / 1</div>
                    <div id="gallery-empty" class="gallery-empty hidden">📷 Сурат нест</div>
                </div>
            </div>

            <div class="car-content">
                <div class="car-hero-meta">
                    <p class="car-eyebrow">Telegram Cars</p>
                    <h1 id="car-name"></h1>
                    <code class="vin-code vin-chip" id="car-vin"></code>
                </div>

                <div class="logistics-sheet surface-card wow-card">
                    <div class="sheet-row">
                        <span class="sheet-label"><span class="sheet-icon" aria-hidden="true">🏷️</span>Модел :</span>
                        <strong id="car-name-sheet"></strong>
                    </div>
                    <div class="sheet-row">
                        <span class="sheet-label"><span class="sheet-icon" aria-hidden="true">📍</span>Шарджа :</span>
                        <strong id="car-sharja"></strong>
                    </div>
                    <div class="sheet-row">
                        <span class="sheet-label"><span class="sheet-icon" aria-hidden="true">⬆️</span>Боргири дар :</span>
                        <strong id="car-upload-status" class="upload-type-value"></strong>
                    </div>
                </div>

                <div class="notes-block surface-card wow-card hidden" id="notes-block">
                    <h3>Эзоҳ</h3>
                    <p id="car-notes"></p>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/miniapp-core.js?v=<?= (int) $assetVer ?>"></script>
    <script src="assets/js/car.js?v=<?= (int) $assetVer ?>"></script>
</body>
</html>
