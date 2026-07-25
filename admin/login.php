<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

startSession();

if (isLoggedIn()) {
    redirect(adminUrl('dashboard.php'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($email === '' || $password === '') {
        $error = 'Email ва паролро ворид кунед';
    } else {
        $result = loginAdmin($email, $password, $remember);

        if ($result['success']) {
            redirect(adminUrl('dashboard.php'));
        }

        $error = $result['error'] ?? 'Хатогии воридшавӣ';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — <?= e(APP_NAME) ?></title>
    <script>(function(){try{var t=localStorage.getItem('admin-theme');if(t==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <?php
    $loginCss = __DIR__ . '/assets/css/login.css';
    $themeCss = __DIR__ . '/assets/css/theme.css';
    ?>
    <link rel="stylesheet" href="<?= e(adminUrl('assets/css/login.css?v=' . (is_file($loginCss) ? filemtime($loginCss) : '1'))) ?>">
    <link rel="stylesheet" href="<?= e(adminUrl('assets/css/theme.css?v=' . (is_file($themeCss) ? filemtime($themeCss) : '1'))) ?>">
</head>
<body class="login-body">
    <div id="themeCurtain" class="theme-curtain" aria-hidden="true"></div>
    <button type="button" class="theme-toggle theme-toggle-floating" id="themeToggle" data-theme-state="dark" aria-label="Светлая тема">
        <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
        </svg>
        <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
    </button>
    <div class="login-bg"></div>
    <div class="login-grid"></div>

    <div class="login-wrap">
        <div class="login-card glass animate-up">
            <div class="login-brand">
                <div class="brand-glow">TC</div>
                <h1>Telegram Cars</h1>
                <p>Premium Admin Panel</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="login-alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="login-form" autocomplete="on">
                <?= csrfField() ?>

                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" required
                           value="<?= e($_POST['email'] ?? '') ?>"
                           placeholder="admin@example.com">
                </label>

                <label class="field">
                    <span>Password</span>
                    <input type="password" name="password" required
                           placeholder="••••••••">
                </label>

                <label class="remember">
                    <input type="checkbox" name="remember" value="1"
                        <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    <span>Запомнить меня</span>
                </label>

                <button type="submit" class="btn-login">
                    <span>Войти</span>
                </button>
            </form>
        </div>

        <p class="login-footer">&copy; <?= date('Y') ?> Telegram Cars</p>
    </div>

    <script src="<?= e(adminUrl('assets/js/theme.js?v=' . (is_file(__DIR__ . '/assets/js/theme.js') ? filemtime(__DIR__ . '/assets/js/theme.js') : '1'))) ?>"></script>
    <script src="<?= e(adminUrl('assets/js/login.js')) ?>"></script>
</body>
</html>
