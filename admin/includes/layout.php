<?php

declare(strict_types=1);

function renderAdminHeader(string $title, string $activeKey = ''): void
{
    $admin = getCurrentAdmin();
    $navItems = adminNavItems();
    $flash = flashGet();
    $adminCssPath = __DIR__ . '/../assets/css/admin.css';
    $themeCssPath = __DIR__ . '/../assets/css/theme.css';
    ?>
<!DOCTYPE html>
<html lang="<?= e(adminLocale()) ?>">
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
                    <span><?= e(__('brand.admin_panel')) ?></span>
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
                <a href="<?= e(adminUrl('logout.php')) ?>" class="logout-link"><?= e(__('nav.logout')) ?></a>
            </div>
        </aside>

        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <div class="admin-main">
            <header class="topbar glass">
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="<?= e(__('theme.menu')) ?>">
                    <span></span><span></span><span></span>
                </button>
                <div class="topbar-title">
                    <h1><?= e($title) ?></h1>
                </div>
                <div class="topbar-meta">
                    <?php adminLocaleSwitcher(); ?>
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
    <script>window.ADMIN_I18N = <?= json_encode(adminJsStrings(), JSON_UNESCAPED_UNICODE) ?>;</script>
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
