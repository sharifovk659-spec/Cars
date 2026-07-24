<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/telegram.php';

$vin = strtoupper(trim($_GET['vin'] ?? ''));
$pageTitle = $vin !== '' ? 'Мошин ' . $vin : 'Мошин';
?>
<!DOCTYPE html>
<html lang="tg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — Telegram Cars</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div id="app" data-page="car" data-vin="<?= htmlspecialchars($vin, ENT_QUOTES, 'UTF-8') ?>">
        <div id="state-loading" class="state-screen">
            <div class="loader"></div>
            <p>Боркунӣ...</p>
        </div>

        <div id="state-preview" class="state-screen hidden">
            <div class="preview-card neon-card">
                <div class="preview-icon">⚡</div>
                <h2>Режими санҷиш</h2>
                <p>Mini App-ро дар Telegram кушед барои дидани маълумоти пурра.</p>
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
                <div class="gallery-wrap neon-frame">
                    <div class="gallery-track" id="gallery-track"></div>
                    <div class="gallery-dots" id="gallery-dots"></div>
                    <div class="gallery-counter" id="gallery-counter">1 / 1</div>
                    <div id="gallery-empty" class="gallery-empty hidden">📷 Сурат нест</div>
                </div>
            </div>

            <div class="car-content">
                <div class="car-head">
                    <h1 id="car-name"></h1>
                    <span class="status-badge" id="car-status"></span>
                </div>
                <code class="vin-code" id="car-vin"></code>

                <div class="info-grid">
                    <div class="info-item surface-card">
                        <span>📅 Рӯзи қабул</span>
                        <strong id="car-receive"></strong>
                    </div>
                    <div class="info-item surface-card">
                        <span>📤 Рӯзи боргирӣ</span>
                        <strong id="car-upload"></strong>
                    </div>
                    <div class="info-item surface-card">
                        <span>👤 Контакт</span>
                        <strong id="car-contact"></strong>
                    </div>
                    <div class="info-item surface-card phone-item">
                        <span>📞 Телефон</span>
                        <strong id="car-phone"></strong>
                    </div>
                </div>

                <div class="notes-block surface-card hidden" id="notes-block">
                    <h3>Эзоҳ</h3>
                    <p id="car-notes"></p>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/miniapp-core.js"></script>
    <script src="assets/js/car.js"></script>
</body>
</html>
