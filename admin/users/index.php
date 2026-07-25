<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../includes/clients.php';

requireAuth();

$pdo = db();
$tab = ($_GET['tab'] ?? 'clients') === 'telegram' ? 'telegram' : 'clients';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$stats = clientStats($pdo);

if ($tab === 'telegram') {
    $result = listTelegramUsers($pdo, $search, $page, $perPage);
} else {
    $result = listCarClients($pdo, $search, $page, $perPage);
}

$items = $result['items'];
$total = $result['total'];
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$queryBase = array_filter([
    'tab' => $tab !== 'clients' ? $tab : null,
    'q'   => $search !== '' ? $search : null,
], static fn ($value) => $value !== null && $value !== '');

renderAdminHeader(__('nav.users'), 'users');
?>

<section class="stats-grid users-stats animate-in">
    <article class="stat-card glass">
        <div class="stat-icon blue">👥</div>
        <div>
            <span class="stat-label"><?= e(__('users.stats.clients')) ?></span>
            <strong class="stat-value"><?= $stats['total'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass">
        <div class="stat-icon green">📞</div>
        <div>
            <span class="stat-label"><?= e(__('users.stats.with_name')) ?></span>
            <strong class="stat-value"><?= $stats['with_phone'] ?></strong>
        </div>
    </article>
    <article class="stat-card glass">
        <div class="stat-icon cyan">🤖</div>
        <div>
            <span class="stat-label"><?= e(__('users.stats.telegram')) ?></span>
            <strong class="stat-value"><?= $stats['telegram'] ?></strong>
        </div>
    </article>
</section>

<section class="glass-card users-panel animate-in" style="--delay: 0.08s">
    <div class="users-tabs" role="tablist">
        <a href="<?= e(adminUrl('users/index.php?' . http_build_query(array_merge($queryBase, ['tab' => null, 'page' => null])))) ?>"
           class="users-tab<?= $tab === 'clients' ? ' active' : '' ?>">
            <?= e(__('users.tab.clients')) ?>
        </a>
        <a href="<?= e(adminUrl('users/index.php?' . http_build_query(array_merge($queryBase, ['tab' => 'telegram', 'page' => null])))) ?>"
           class="users-tab<?= $tab === 'telegram' ? ' active' : '' ?>">
            <?= e(__('users.tab.telegram')) ?>
        </a>
    </div>

    <form method="get" class="users-search">
        <?php if ($tab === 'telegram'): ?>
            <input type="hidden" name="tab" value="telegram">
        <?php endif; ?>
        <label class="users-search-field">
            <span aria-hidden="true">🔍</span>
            <input type="search"
                   name="q"
                   value="<?= e($search) ?>"
                   placeholder="<?= e($tab === 'telegram' ? __('users.search.telegram') : __('users.search.clients')) ?>">
        </label>
        <button type="submit" class="btn-primary sm"><?= e(__('cars.apply')) ?></button>
        <?php if ($search !== ''): ?>
            <a href="<?= e(adminUrl('users/index.php' . ($tab === 'telegram' ? '?tab=telegram' : ''))) ?>" class="btn-ghost sm">
                <?= e(__('cars.reset')) ?>
            </a>
        <?php endif; ?>
    </form>

    <div class="card-head users-card-head">
        <h2>
            <?= e($tab === 'telegram' ? __('users.list.telegram') : __('users.list.clients')) ?>
            <span class="count-badge"><?= $total ?></span>
        </h2>
        <?php if ($tab === 'clients'): ?>
            <p class="muted users-hint"><?= e(__('users.hint.clients')) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($items === []): ?>
        <div class="users-empty">
            <div class="users-empty-icon">👤</div>
            <h3><?= e(__('users.empty.title')) ?></h3>
            <p class="muted"><?= e($tab === 'telegram' ? __('users.empty.telegram') : __('users.empty.clients')) ?></p>
            <?php if ($tab === 'clients'): ?>
                <a href="<?= e(adminUrl('cars/add.php')) ?>" class="btn-primary sm"><?= e(__('cars.add_btn')) ?></a>
            <?php endif; ?>
        </div>
    <?php elseif ($tab === 'clients'): ?>
        <div class="users-grid">
            <?php foreach ($items as $index => $client): ?>
                <article class="user-card wow-user-card" style="--delay: <?= min($index * 0.04, 0.4) ?>s">
                    <div class="user-card-top">
                        <div class="user-avatar" aria-hidden="true"><?= e($client['initials']) ?></div>
                        <div class="user-card-meta">
                            <h3><?= e($client['display_name']) ?></h3>
                            <a href="tel:<?= e(preg_replace('/\s+/', '', $client['contact_phone']) ?? $client['contact_phone']) ?>"
                               class="user-phone-link">
                                📞 <?= e($client['contact_phone']) ?>
                            </a>
                        </div>
                    </div>
                    <div class="user-card-stats">
                        <span class="user-pill"><?= e(__('users.cars_count')) ?>: <strong><?= (int) $client['cars_count'] ?></strong></span>
                        <?php if ($client['latest_vin'] !== ''): ?>
                            <span class="user-pill user-pill-vin"><?= e($client['latest_vin']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($client['latest_car_name'] !== ''): ?>
                        <p class="user-latest-car"><?= e($client['latest_car_name']) ?></p>
                    <?php endif; ?>
                    <div class="user-card-actions">
                        <a href="<?= e(adminCarUrl('view.php', ['id' => $client['latest_car_id']])) ?>" class="btn-ghost sm">
                            <?= e(__('btn.view')) ?>
                        </a>
                        <a href="<?= e(adminUrl('cars/index.php?' . http_build_query(['phone' => $client['contact_phone']]))) ?>"
                           class="btn-primary sm">
                            <?= e(__('users.all_cars')) ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="users-grid">
            <?php foreach ($items as $index => $user): ?>
                <article class="user-card wow-user-card user-card-telegram" style="--delay: <?= min($index * 0.04, 0.4) ?>s">
                    <div class="user-card-top">
                        <div class="user-avatar user-avatar-telegram" aria-hidden="true"><?= e($user['initials']) ?></div>
                        <div class="user-card-meta">
                            <h3><?= e($user['display_name']) ?></h3>
                            <?php if ($user['username'] !== ''): ?>
                                <span class="user-telegram-handle">@<?= e($user['username']) ?></span>
                            <?php else: ?>
                                <span class="user-telegram-handle">ID <?= e($user['telegram_id']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="user-card-stats">
                        <span class="user-pill"><?= e(__('users.searches_count')) ?>: <strong><?= (int) $user['searches_count'] ?></strong></span>
                        <?php if ($user['last_search_at']): ?>
                            <span class="user-pill"><?= e(formatDateTime($user['last_search_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination users-pagination" aria-label="Pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php
                $pageQuery = array_merge($queryBase, ['page' => $p > 1 ? $p : null]);
                $pageQuery = array_filter($pageQuery, static fn ($v) => $v !== null);
                ?>
                <a href="<?= e(adminUrl('users/index.php?' . http_build_query($pageQuery))) ?>"
                   class="page-link<?= $p === $page ? ' active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>

<?php
renderAdminFooter();
