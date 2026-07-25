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

function formatUploadDisplayDate(?string $date, string $fallback = ''): string
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

    return 'Боргири дар';
}

function carUploadLoadType(array $car): ?string
{
    if (trim((string) ($car['vagon'] ?? '')) !== '') {
        return 'Вагон';
    }

    if (trim((string) ($car['treiler'] ?? '')) !== '') {
        return 'Трейлер';
    }

    return null;
}

function carUploadTypeParts(array $car, string $type): string
{
    $segments = [$type];
    $number = trim((string) ($car['upload_number'] ?? ''));
    $date = formatUploadDisplayDate($car['upload_date'] ?? null);

    if ($number !== '') {
        $segments[] = $number;
    }

    if ($date !== '') {
        $segments[] = $date;
    }

    return count($segments) > 1 ? implode(' · ', $segments) : $type;
}

/**
 * @return array{label: string, type: string, number: string, date: string, text: string}|null
 */
function carUploadDisplayParts(array $car): ?array
{
    $type = carUploadLoadType($car);

    if ($type === null) {
        return null;
    }

    $number = trim((string) ($car['upload_number'] ?? ''));
    $date = formatUploadDisplayDate($car['upload_date'] ?? null);

    return [
        'label'  => carUploadSheetLabel(),
        'type'   => $type,
        'number' => $number,
        'date'   => $date,
        'text'   => carUploadTypeParts($car, $type),
    ];
}

function buildBotUploadCaptionLine(array $car): string
{
    $display = carUploadDisplayParts($car);

    if ($display === null) {
        if (!empty($car['upload_date'])) {
            $label = htmlspecialchars(carFieldLabel('upload_date'), ENT_QUOTES, 'UTF-8');
            $date = htmlspecialchars(formatUploadDisplayDate($car['upload_date']), ENT_QUOTES, 'UTF-8');

            return '⬆️ <b>' . $label . ':</b> <b>' . $date . '</b>';
        }

        return '⬆️ Ҳоло боргирӣ нашудааст';
    }

    $label = htmlspecialchars($display['label'], ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars($display['type'], ENT_QUOTES, 'UTF-8');
    $segments = ['<b>' . $type . '</b>'];

    if ($display['number'] !== '') {
        $segments[] = '№ <code>' . htmlspecialchars($display['number'], ENT_QUOTES, 'UTF-8') . '</code>';
    }

    if ($display['date'] !== '') {
        $segments[] = '<b>' . htmlspecialchars($display['date'], ENT_QUOTES, 'UTF-8') . '</b>';
    }

    return '⬆️ <b>' . $label . ':</b> ' . implode(' · ', $segments);
}

function carUploadTypeLabel(array $car): string
{
    $vagon = trim((string) ($car['vagon'] ?? ''));
    $treiler = trim((string) ($car['treiler'] ?? ''));

    if ($vagon !== '') {
        return carUploadTypeParts($car, 'Вагон');
    }

    if ($treiler !== '') {
        return carUploadTypeParts($car, 'Трейлер');
    }

    if (!empty($car['upload_date'])) {
        return carFieldLabel('upload_date');
    }

    return '—';
}

function carUploadStatusLabel(array $car): string
{
    $vagon = trim((string) ($car['vagon'] ?? ''));
    $treiler = trim((string) ($car['treiler'] ?? ''));

    if ($vagon !== '') {
        $value = carUploadTypeParts($car, 'вагон');

        return 'Боргир дар ' . $value;
    }

    if ($treiler !== '') {
        $value = carUploadTypeParts($car, 'трейлер');

        return 'Боргир дар ' . $value;
    }

    return carUploadTypeLabel($car);
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
        'upload_display'      => carUploadDisplayParts($car),
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
