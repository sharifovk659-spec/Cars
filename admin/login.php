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

    $login = trim($_POST['login'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($login === '' || $password === '') {
        $error = __('auth.error_empty');
    } else {
        $result = loginAdmin($login, $password, $remember);

        if ($result['success']) {
            redirect(adminUrl('dashboard.php'));
        }

        $error = $result['error'] ?? __('auth.error_generic');
    }
}

$loginCss = __DIR__ . '/assets/css/login.css';
$themeCss = __DIR__ . '/assets/css/theme.css';
$themeJs = __DIR__ . '/assets/js/theme.js';
?>
<!DOCTYPE html>
<html lang="<?= e(adminLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__('auth.login_title')) ?> — <?= e(APP_NAME) ?></title>
    <script>(function(){try{var t=localStorage.getItem('admin-theme');if(t==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="<?= e(adminUrl('assets/css/login.css?v=' . (is_file($loginCss) ? filemtime($loginCss) : '1'))) ?>">
    <link rel="stylesheet" href="<?= e(adminUrl('assets/css/theme.css?v=' . (is_file($themeCss) ? filemtime($themeCss) : '1'))) ?>">
</head>
<body class="login-body">
    <div id="themeCurtain" class="theme-curtain" aria-hidden="true"></div>
    <div class="login-top-actions">
        <?php adminLocaleSwitcher(); ?>
        <?= adminThemeToggleButton() ?>
    </div>
    <div class="login-bg"></div>
    <div class="login-grid"></div>

    <div class="login-wrap">
        <div class="login-card glass animate-up">
            <div class="login-brand">
                <div class="brand-glow">TC</div>
                <h1><?= e(APP_NAME) ?></h1>
                <p><?= e(__('brand.admin_panel')) ?></p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="login-alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="login-form" autocomplete="on">
                <?= csrfField() ?>

                <label class="field">
                    <span><?= e(__('auth.login')) ?></span>
                    <input type="text" name="login" required autocomplete="username"
                           value="<?= e($_POST['login'] ?? $_POST['email'] ?? '') ?>"
                           placeholder="admin">
                </label>

                <label class="field">
                    <span><?= e(__('auth.password')) ?></span>
                    <input type="password" name="password" required
                           placeholder="••••••••">
                </label>

                <label class="remember">
                    <input type="checkbox" name="remember" value="1"
                        <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    <span><?= e(__('auth.remember')) ?></span>
                </label>

                <button type="submit" class="btn-login">
                    <span><?= e(__('auth.sign_in')) ?></span>
                </button>
            </form>
        </div>

        <p class="login-footer">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></p>
    </div>

    <script>window.ADMIN_I18N = <?= json_encode(adminJsStrings(), JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="<?= e(adminUrl('assets/js/theme.js?v=' . (is_file($themeJs) ? filemtime($themeJs) : '1'))) ?>"></script>
    <script src="<?= e(adminUrl('assets/js/login.js')) ?>"></script>
</body>
</html>
