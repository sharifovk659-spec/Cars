<?php

declare(strict_types=1);

/** @return array<string, string> */
function carStatusLabels(): array
{
    if (function_exists('__')) {
        return [
            'available' => __('status.available'),
            'reserved'  => __('status.reserved'),
            'sold'      => __('status.sold'),
            'archived'  => __('status.archived'),
        ];
    }

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
    if (function_exists('__')) {
        return [
            'name'           => __('field.name'),
            'vin_code'       => __('field.vin_code'),
            'receive_date'   => __('field.receive_date'),
            'upload_date'    => __('field.upload_date'),
            'upload_number'  => __('field.upload_number'),
            'vagon'          => __('field.vagon'),
            'treiler'        => __('field.treiler'),
            'photos'         => __('field.photos'),
            'status'         => __('field.status'),
            'contact_name'   => __('field.contact_name'),
            'contact_phone'  => __('field.contact_phone'),
            'description'    => __('field.description'),
            'notes'          => __('field.notes'),
        ];
    }

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
