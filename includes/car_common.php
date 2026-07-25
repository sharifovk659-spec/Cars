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
        'name'           => 'Модел',
        'vin_code'       => 'VIN Code',
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

function carUploadSheetLabel(): string
{
    if (function_exists('__')) {
        return __('field.upload_vagon_treiler');
    }

    return 'Боргири шуд дар';
}

function carUploadTypeLabel(array $car): string
{
    $vagon = trim((string) ($car['vagon'] ?? ''));
    $treiler = trim((string) ($car['treiler'] ?? ''));

    if ($vagon !== '') {
        return 'Вагон';
    }

    if ($treiler !== '') {
        return 'Трейлер';
    }

    if (!empty($car['upload_date'])) {
        return carFieldLabel('upload_date');
    }

    return '—';
}

function carUploadStatusLabel(array $car): string
{
    $type = carUploadTypeLabel($car);

    if ($type === 'Вагон') {
        return 'Боргир шуд дар вагон';
    }

    if ($type === 'Трейлер') {
        return 'Боргир шуд дар трейлер';
    }

    return $type;
}

/** @return list<array{label: string, value: string}> */
function carAdminSheetLines(array $car): array
{
    $labels = carFieldLabels();
    $uploadValue = carUploadTypeLabel($car);

    return [
        ['label' => $labels['name'], 'value' => (string) ($car['name'] ?? '—')],
        ['label' => $labels['receive_date'], 'value' => formatDate($car['receive_date'] ?? null)],
        ['label' => carUploadSheetLabel(), 'value' => $uploadValue],
    ];
}

/** @return array<string, mixed> */
function carLookupPayload(array $car): array
{
    return [
        'id'            => (int) $car['id'],
        'vin_code'      => (string) $car['vin_code'],
        'name'          => (string) ($car['name'] ?? ''),
        'receive_date'  => (string) ($car['receive_date'] ?? ''),
        'upload_date'   => (string) ($car['upload_date'] ?? ''),
        'upload_number' => (string) ($car['upload_number'] ?? ''),
        'vagon'         => (string) ($car['vagon'] ?? ''),
        'treiler'       => (string) ($car['treiler'] ?? ''),
        'status'        => (string) ($car['status'] ?? 'available'),
        'contact_name'  => (string) ($car['contact_name'] ?? ''),
        'contact_phone' => (string) ($car['contact_phone'] ?? ''),
        'notes'         => (string) ($car['notes'] ?? ''),
        'upload_status_label' => carUploadStatusLabel($car),
        'upload_type_label'   => carUploadTypeLabel($car),
        'sheet'         => carAdminSheetLines($car),
    ];
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
