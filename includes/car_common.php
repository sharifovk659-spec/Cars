<?php

declare(strict_types=1);

/** @return array<string, string> */
function carStatusLabels(): array
{
    return [
        'available' => 'Доступен',
        'reserved'  => 'В обработке',
        'sold'      => 'Продан',
        'archived'  => 'Архив',
    ];
}

function carStatusLabel(string $status): string
{
    return carStatusLabels()[$status] ?? $status;
}

function formatDate(?string $date, string $fallback = '—'): string
{
    if ($date === null || $date === '') {
        return $fallback;
    }

    $timestamp = strtotime($date);

    return $timestamp ? date('d.m.Y', $timestamp) : $fallback;
}
