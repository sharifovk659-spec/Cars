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

/** @return array<string, string> */
function carFieldLabels(): array
{
    return [
        'name'           => 'Мошина',
        'vin_code'       => 'VinCode',
        'receive_date'   => 'Шарджа',
        'upload_date'    => 'Боргирии шуд',
        'upload_number'  => 'Числои боргири',
        'vagon'          => 'Вагон',
        'treiler'        => 'Трейлер',
        'photos'         => 'Суратҳо',
        'status'         => 'Статус',
        'contact_name'   => 'Контакт',
        'contact_phone'  => 'Телефон',
        'description'    => 'Тавсиф',
        'notes'          => 'Эзоҳ',
    ];
}

function carFieldLabel(string $key): string
{
    return carFieldLabels()[$key] ?? $key;
}

/** @return array<string, string> */
function carDefaultFormInput(): array
{
    return [
        'vin_code'      => '',
        'name'          => '',
        'description'   => '',
        'receive_date'  => date('Y-m-d'),
        'upload_date'   => '',
        'upload_number' => '',
        'vagon'         => '',
        'treiler'       => '',
        'status'        => 'available',
        'contact_name'  => '',
        'contact_phone' => '',
        'notes'         => '',
    ];
}
