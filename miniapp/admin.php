<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/admin/includes/auth.php';

startSession();

$bridge = trim((string) ($_GET['bridge'] ?? ''));

if ($bridge !== '' && loginAdminViaBridgeToken($bridge)) {
    header('Location: admin.php');
    exit;
}

if (!isLoggedIn() && !tryRememberLogin()) {
    header('Location: index.php?admin=1');
    exit;
}

$admin = getCurrentAdmin();
if ($admin === null) {
    header('Location: index.php?admin=1');
    exit;
}

$pdo = db();
$stats = [
    'total_cars'  => (int) $pdo->query('SELECT COUNT(*) FROM cars WHERE deleted_at IS NULL')->fetchColumn(),
    'added_today' => (int) $pdo->query('SELECT COUNT(*) FROM cars WHERE deleted_at IS NULL AND DATE(created_at) = CURDATE()')->fetchColumn(),
    'clients'     => (int) $pdo->query("SELECT COUNT(DISTINCT contact_phone) FROM cars WHERE deleted_at IS NULL AND contact_phone IS NOT NULL AND TRIM(contact_phone) <> ''")->fetchColumn(),
    'searches'    => (int) $pdo->query('SELECT COUNT(*) FROM search_history WHERE DATE(searched_at) = CURDATE()')->fetchColumn(),
];

$cssPath = __DIR__ . '/assets/css/style.css';
$jsCore = __DIR__ . '/assets/js/miniapp-core.js';
$jsAdmin = __DIR__ . '/assets/js/admin-panel.js';
$v = max(
    is_file($cssPath) ? filemtime($cssPath) : 1,
    is_file($jsCore) ? filemtime($jsCore) : 1,
    is_file($jsAdmin) ? filemtime($jsAdmin) : 1
);

$adminName = (string) ($admin['full_name'] ?: $admin['username']);
$adminInitial = mb_strtoupper(mb_substr($adminName, 0, 1));
?>
<!DOCTYPE html>
<html lang="tg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Admin — <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int) $v ?>">
</head>
<body>
    <div id="app" class="admin-mobile-app" data-page="admin">
        <header class="admin-mobile-header wow-card">
            <div class="admin-mobile-user">
                <div class="admin-mobile-avatar"><?= htmlspecialchars($adminInitial, ENT_QUOTES, 'UTF-8') ?></div>
                <div>
                    <p class="admin-mobile-kicker">Admin Panel</p>
                    <h1><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
            </div>
            <button type="button" class="admin-logout-btn" id="admin-logout-btn" aria-label="Logout">⎋</button>
        </header>

        <section class="admin-mobile-stats">
            <article class="admin-stat-card">
                <span>🚗</span>
                <strong><?= $stats['total_cars'] ?></strong>
                <small>Мошинҳо</small>
            </article>
            <article class="admin-stat-card">
                <span>✦</span>
                <strong><?= $stats['added_today'] ?></strong>
                <small>Имрӯз</small>
            </article>
            <article class="admin-stat-card">
                <span>👥</span>
                <strong><?= $stats['clients'] ?></strong>
                <small>Мизоҷон</small>
            </article>
            <article class="admin-stat-card">
                <span>🔍</span>
                <strong><?= $stats['searches'] ?></strong>
                <small>Ҷустуҷӯ</small>
            </article>
        </section>

        <section class="admin-mobile-menu">
            <a href="../admin/cars/index.php" class="admin-menu-tile wow-card">
                <span class="admin-menu-icon">🚘</span>
                <span class="admin-menu-label">Мошинҳо</span>
            </a>
            <a href="../admin/cars/add.php" class="admin-menu-tile wow-card admin-menu-tile-accent">
                <span class="admin-menu-icon">➕</span>
                <span class="admin-menu-label">Илова</span>
            </a>
            <a href="../admin/users/index.php" class="admin-menu-tile wow-card">
                <span class="admin-menu-icon">📞</span>
                <span class="admin-menu-label">Корбарон</span>
            </a>
            <a href="../admin/dashboard.php" class="admin-menu-tile wow-card">
                <span class="admin-menu-icon">📊</span>
                <span class="admin-menu-label">Dashboard</span>
            </a>
            <a href="../admin/settings/index.php" class="admin-menu-tile wow-card">
                <span class="admin-menu-icon">⚙️</span>
                <span class="admin-menu-label">Танзимот</span>
            </a>
            <a href="index.php" class="admin-menu-tile wow-card">
                <span class="admin-menu-icon">🔍</span>
                <span class="admin-menu-label">Ҷустуҷӯ</span>
            </a>
        </section>

        <footer class="admin-mobile-footer">
            <a href="index.php" class="btn-ghost">← Бозгашт ба Mini App</a>
        </footer>
    </div>
    <script src="assets/js/miniapp-core.js?v=<?= (int) $v ?>"></script>
    <script src="assets/js/admin-panel.js?v=<?= (int) $v ?>"></script>
</body>
</html>
