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

function adminThemeToggleButton(): string
{
    return <<<'HTML'
<button type="button" class="theme-toggle" id="themeToggle" data-theme-state="dark" aria-label="Светлая тема">
    <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4"></circle>
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
    </svg>
    <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
    </svg>
</button>
HTML;
}

function renderAdminHeader(string $title, string $activeKey = ''): void
{
    $admin = getCurrentAdmin();
    $navItems = adminNavItems();
    $flash = flashGet();
    $adminCssPath = __DIR__ . '/../assets/css/admin.css';
    $themeCssPath = __DIR__ . '/../assets/css/theme.css';
    $themeJsPath = __DIR__ . '/../assets/js/theme.js';
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — <?= e(APP_NAME) ?></title>
    <script>(function(){try{var t=localStorage.getItem('admin-theme');if(t==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
    <link rel="stylesheet" href="<?= e(adminUrl('assets/css/admin.css?v=' . (is_file($adminCssPath) ? filemtime($adminCssPath) : '1'))) ?>">
    <link rel="stylesheet" href="<?= e(adminUrl('assets/css/theme.css?v=' . (is_file($themeCssPath) ? filemtime($themeCssPath) : '1'))) ?>">
</head>
<body class="admin-body">
    <div id="themeCurtain" class="theme-curtain" aria-hidden="true"></div>
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
                    <?= adminThemeToggleButton() ?>
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
    $themeJsPath = __DIR__ . '/../assets/js/theme.js';
    $adminJsPath = __DIR__ . '/../assets/js/admin.js';
    ?>
            </main>
        </div>
    </div>
    <script src="<?= e(adminUrl('assets/js/theme.js?v=' . (is_file($themeJsPath) ? filemtime($themeJsPath) : '1'))) ?>"></script>
    <script src="<?= e(adminUrl('assets/js/admin.js?v=' . (is_file($adminJsPath) ? filemtime($adminJsPath) : '1'))) ?>"></script>
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
