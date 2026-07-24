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
    <link rel="stylesheet" href="<?= e(adminUrl('assets/css/login.css')) ?>">
</head>
<body class="login-body">
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

    <script src="<?= e(adminUrl('assets/js/login.js')) ?>"></script>
</body>
</html>
