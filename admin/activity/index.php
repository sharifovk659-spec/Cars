<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

$stmt = db()->query(
    'SELECT al.*, a.full_name AS admin_name
     FROM activity_logs al
     LEFT JOIN admins a ON a.id = al.admin_id
     ORDER BY al.created_at DESC
     LIMIT 50'
);
$logs = $stmt->fetchAll();

renderAdminHeader('Журнал действий', 'activity');
?>

<section class="glass-card animate-in">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Админ</th>
                    <th>Действие</th>
                    <th>Объект</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="muted">Записей пока нет</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e(formatDateTime($log['created_at'])) ?></td>
                            <td><?= e($log['admin_name'] ?? '—') ?></td>
                            <td><?= e($log['action']) ?></td>
                            <td><?= e(($log['entity_type'] ?? '') . ' #' . ($log['entity_id'] ?? '')) ?></td>
                            <td><code><?= e($log['ip_address'] ?? '') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php renderAdminFooter(); ?>
