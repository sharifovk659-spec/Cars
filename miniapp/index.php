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

        <div id="state-search" class="screen search-screen">
            <div class="search-shell">
                <div class="search-card wow-card">
                    <div class="search-card-glow" aria-hidden="true"></div>
                    <div class="search-card-header">
                        <div class="search-brand-mark" aria-hidden="true">🚗</div>
                        <button type="button" class="admin-entry-btn" id="admin-entry-btn" aria-label="Admin panel" title="Admin">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4Zm0 4.9a3 3 0 0 1 3 3v1h1a1 1 0 1 1 0 2h-1v1a3 3 0 1 1-2 0v-1h-1a1 1 0 1 1 0-2h1v-1a3 3 0 0 1 3-3Z"/></svg>
                        </button>
                    </div>
                    <div class="search-card-body">
                        <h1 id="company-name">Telegram Cars</h1>
                        <p class="search-subtitle">VIN Code ё 5 рақами охиринро ворид кунед</p>
                        <form id="search-form" class="search-form">
                            <label class="search-input-wrap" for="search-input">
                                <span class="search-input-icon" aria-hidden="true">🔍</span>
                                <input type="text" id="search-input" placeholder="VIN Code" autocomplete="off" maxlength="17" inputmode="text">
                            </label>
                            <p class="search-tip">Мисол: 2515 ё тамоми VIN</p>
                            <button type="submit" class="btn-primary neon-btn search-submit-btn">Ҷустуҷӯ</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div id="state-admin-login" class="screen hidden">
            <div class="admin-login-shell">
                <button type="button" class="admin-login-back" id="admin-login-back">← Бозгашт</button>
                <div class="admin-login-card wow-card">
                    <div class="admin-login-brand">
                        <div class="admin-login-glow">🛡️</div>
                        <h2>Admin Panel</h2>
                        <p>Логин ва паролро ворид кунед</p>
                    </div>
                    <form id="admin-login-form" class="admin-login-form">
                        <label class="admin-field">
                            <span>Логин</span>
                            <input type="text" id="admin-login-input" autocomplete="username" placeholder="Email ё username" required>
                        </label>
                        <label class="admin-field">
                            <span>Парол</span>
                            <input type="password" id="admin-password-input" autocomplete="current-password" placeholder="••••••••" required>
                        </label>
                        <label class="admin-remember">
                            <input type="checkbox" id="admin-remember-input" value="1">
                            <span>Маро дар хотир дор</span>
                        </label>
                        <p id="admin-login-error" class="admin-login-error hidden"></p>
                        <button type="submit" class="btn-primary neon-btn admin-login-submit">Даромад</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/miniapp-core.js?v=<?= (int) $v ?>"></script>
    <script src="assets/js/app.js?v=<?= (int) $v ?>"></script>
</body>
</html>
