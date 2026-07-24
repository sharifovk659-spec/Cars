<?php

declare(strict_types=1);

/**
 * UI helpers — action buttons, icons
 */

function adminCarUrl(string $page, array $params = []): string
{
    $query = $params !== [] ? '?' . http_build_query($params) : '';

    return adminUrl('cars/' . ltrim($page, '/')) . $query;
}

function adminIcon(string $name): string
{
    $icons = [
        'view' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c-5.5 0-9.5 5-9.5 7s4 7 9.5 7 9.5-5 9.5-7-4-7-9.5-7Zm0 11.5a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Z"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a1.4 1.4 0 0 0 0-2L16.5 5a1.4 1.4 0 0 0-2 0L4 15.5V20Zm2-1.5v-2.3L14.8 7.4l2.3 2.3L8.3 18.5H6Z"/></svg>',
        'delete' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7h4v2h-1v10.5A1.5 1.5 0 0 1 17.5 21h-11A1.5 1.5 0 0 1 5 19.5V9H4V7h4Zm2 0h8V5.5h-8V7Zm-1 2v10.5h10V9H9Z"/></svg>',
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.8 6.3 5.1 12l5.7 5.7 1.4-1.4L8.9 13H19v-2H8.9l3.3-3.3-1.4-1.4Z"/></svg>',
    ];

    return $icons[$name] ?? '';
}

function renderCarActionButtons(int $carId, string $carName, string $size = 'md'): void
{
    $viewUrl = adminCarUrl('view.php', ['id' => $carId]);
    $editUrl = adminCarUrl('edit.php', ['id' => $carId]);
    $sizeClass = $size === 'sm' ? ' action-btns-sm' : '';
    ?>
    <div class="action-btns<?= $sizeClass ?>">
        <a href="<?= e($viewUrl) ?>" class="btn-icon btn-icon-view" title="Просмотр" aria-label="Просмотр <?= e($carName) ?>">
            <?= adminIcon('view') ?>
        </a>
        <a href="<?= e($editUrl) ?>" class="btn-icon btn-icon-edit" title="Редактировать" aria-label="Редактировать <?= e($carName) ?>">
            <?= adminIcon('edit') ?>
        </a>
        <button type="button"
                class="btn-icon btn-icon-delete btn-delete"
                data-id="<?= $carId ?>"
                data-name="<?= e($carName) ?>"
                title="Удалить"
                aria-label="Удалить <?= e($carName) ?>">
            <?= adminIcon('delete') ?>
        </button>
    </div>
    <?php
}
