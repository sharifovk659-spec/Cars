<?php

declare(strict_types=1);

/** @var array<int, array{href: string, label: string, icon: string, key: string}> */
function adminNavItems(): array
{
    return [
        ['key' => 'dashboard',      'href' => adminUrl('dashboard.php'),           'label' => 'Главная',           'icon' => '⌂'],
        ['key' => 'cars',           'href' => adminUrl('cars/index.php'),          'label' => 'Машины',            'icon' => '🚗'],
        ['key' => 'cars-add',       'href' => adminUrl('cars/add.php'),            'label' => 'Добавить машину',   'icon' => '＋'],
        ['key' => 'users',          'href' => adminUrl('users/index.php'),         'label' => 'Пользователи',      'icon' => '👤'],
        ['key' => 'search-history', 'href' => adminUrl('search-history/index.php'), 'label' => 'История поиска',    'icon' => '🔍'],
        ['key' => 'activity',       'href' => adminUrl('activity/index.php'),        'label' => 'Журнал действий',   'icon' => '📋'],
        ['key' => 'settings',       'href' => adminUrl('settings/index.php'),        'label' => 'Настройки',         'icon' => '⚙'],
        ['key' => 'logout',         'href' => adminUrl('logout.php'),                'label' => 'Выход',             'icon' => '⎋'],
    ];
}

function renderAdminHeader(string $title, string $activeKey = ''): void
{
    $admin = getCurrentAdmin();
    $navItems = adminNavItems();
    $flash = flashGet();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(adminUrl('assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
    <div class="admin-bg"></div>
    <div class="admin-overlay"></div>

    <div class="admin-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">TC</div>
                <div>
                    <strong>Telegram Cars</strong>
                    <span>Admin Panel</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($navItems as $item): ?>
                    <?php if ($item['key'] === 'logout'): continue; endif; ?>
                    <a href="<?= e($item['href']) ?>"
                       class="nav-link<?= $activeKey === $item['key'] ? ' active' : '' ?>">
                        <span class="nav-icon"><?= $item['icon'] ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-footer">
                <div class="admin-mini">
                    <div class="admin-avatar"><?= e(strtoupper(substr($admin['full_name'] ?: $admin['username'], 0, 1))) ?></div>
                    <div>
                        <strong><?= e($admin['full_name'] ?: $admin['username']) ?></strong>
                        <span><?= e($admin['email']) ?></span>
                    </div>
                </div>
                <a href="<?= e(adminUrl('logout.php')) ?>" class="logout-link">Выход</a>
            </div>
        </aside>

        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <div class="admin-main">
            <header class="topbar glass">
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Меню">
                    <span></span><span></span><span></span>
                </button>
                <div class="topbar-title">
                    <h1><?= e($title) ?></h1>
                </div>
                <div class="topbar-meta">
                    <span class="date-badge"><?= date('d.m.Y') ?></span>
                </div>
            </header>

            <main class="content">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> animate-in">
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>
    <?php
}

function renderAdminFooter(): void
{
    ?>
            </main>
        </div>
    </div>
    <script src="<?= e(adminUrl('assets/js/admin.js')) ?>"></script>
</body>
</html>
    <?php
}

function renderPlaceholderPage(string $title, string $activeKey, string $message): void
{
    requireAuth();
    renderAdminHeader($title, $activeKey);
    ?>
    <section class="glass-card animate-in">
        <h2><?= e($title) ?></h2>
        <p class="muted"><?= e($message) ?></p>
    </section>
    <?php
    renderAdminFooter();
}
