<?php

declare(strict_types=1);

function normalizeClientPhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function clientDisplayName(?string $name, string $phone): string
{
    $name = trim((string) $name);

    return $name !== '' ? $name : $phone;
}

function clientInitials(?string $name, string $phone): string
{
    $name = trim((string) $name);

    if ($name !== '') {
        $parts = preg_split('/\s+/u', $name) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($phone, 0, 1));
    }

    return mb_strtoupper(mb_substr(preg_replace('/\D+/', '', $phone) ?: $phone, -2));
}

/** @return array{total: int, with_phone: int, telegram: int} */
function clientStats(PDO $pdo): array
{
    $carsTotal = (int) $pdo->query(
        "SELECT COUNT(DISTINCT contact_phone)
         FROM cars
         WHERE deleted_at IS NULL
           AND contact_phone IS NOT NULL
           AND TRIM(contact_phone) <> ''"
    )->fetchColumn();

    $withName = (int) $pdo->query(
        "SELECT COUNT(DISTINCT contact_phone)
         FROM cars
         WHERE deleted_at IS NULL
           AND contact_phone IS NOT NULL
           AND TRIM(contact_phone) <> ''
           AND contact_name IS NOT NULL
           AND TRIM(contact_name) <> ''"
    )->fetchColumn();

    $telegram = (int) $pdo->query('SELECT COUNT(*) FROM telegram_users')->fetchColumn();

    return [
        'total'     => $carsTotal,
        'with_phone'=> $withName,
        'telegram'  => $telegram,
    ];
}

/**
 * @return array{items: list<array<string, mixed>>, total: int}
 */
function listCarClients(PDO $pdo, string $search = '', int $page = 1, int $perPage = 12): array
{
    $page = max(1, $page);
    $perPage = max(1, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = [
        'c.deleted_at IS NULL',
        'c.contact_phone IS NOT NULL',
        "TRIM(c.contact_phone) <> ''",
    ];
    $params = [];

    if ($search !== '') {
        $where[] = '(c.contact_name LIKE :search_name OR c.contact_phone LIKE :search_phone OR c.vin_code LIKE :search_vin)';
        $like = '%' . $search . '%';
        $params['search_name'] = $like;
        $params['search_phone'] = $like;
        $params['search_vin'] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) FROM (
        SELECT c.contact_phone
        FROM cars c
        WHERE {$whereSql}
        GROUP BY c.contact_phone
    ) grouped_clients";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $listSql = "SELECT
            c.contact_phone,
            (
                SELECT c2.contact_name
                FROM cars c2
                WHERE c2.deleted_at IS NULL
                  AND c2.contact_phone = c.contact_phone
                  AND c2.contact_name IS NOT NULL
                  AND TRIM(c2.contact_name) <> ''
                ORDER BY c2.updated_at DESC, c2.id DESC
                LIMIT 1
            ) AS contact_name,
            COUNT(*) AS cars_count,
            MAX(c.created_at) AS last_car_at,
            (
                SELECT c3.id
                FROM cars c3
                WHERE c3.deleted_at IS NULL
                  AND c3.contact_phone = c.contact_phone
                ORDER BY c3.created_at DESC, c3.id DESC
                LIMIT 1
            ) AS latest_car_id,
            (
                SELECT c4.vin_code
                FROM cars c4
                WHERE c4.deleted_at IS NULL
                  AND c4.contact_phone = c.contact_phone
                ORDER BY c4.created_at DESC, c4.id DESC
                LIMIT 1
            ) AS latest_vin,
            (
                SELECT c5.name
                FROM cars c5
                WHERE c5.deleted_at IS NULL
                  AND c5.contact_phone = c.contact_phone
                ORDER BY c5.created_at DESC, c5.id DESC
                LIMIT 1
            ) AS latest_car_name
        FROM cars c
        WHERE {$whereSql}
        GROUP BY c.contact_phone
        ORDER BY last_car_at DESC
        LIMIT :limit OFFSET :offset";

    $listStmt = $pdo->prepare($listSql);
    foreach ($params as $key => $value) {
        $listStmt->bindValue(':' . $key, $value);
    }
    $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();

    $items = [];

    foreach ($listStmt->fetchAll() as $row) {
        $phone = trim((string) $row['contact_phone']);
        $name = trim((string) ($row['contact_name'] ?? ''));

        $items[] = [
            'contact_phone'   => $phone,
            'contact_name'    => $name,
            'display_name'    => clientDisplayName($name, $phone),
            'initials'        => clientInitials($name, $phone),
            'cars_count'      => (int) $row['cars_count'],
            'last_car_at'     => (string) $row['last_car_at'],
            'latest_car_id'   => (int) $row['latest_car_id'],
            'latest_vin'      => (string) ($row['latest_vin'] ?? ''),
            'latest_car_name' => (string) ($row['latest_car_name'] ?? ''),
        ];
    }

    return [
        'items' => $items,
        'total' => $total,
    ];
}

/**
 * @return array{items: list<array<string, mixed>>, total: int}
 */
function listTelegramUsers(PDO $pdo, string $search = '', int $page = 1, int $perPage = 12): array
{
    $page = max(1, $page);
    $perPage = max(1, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(tu.username LIKE :search_username OR tu.first_name LIKE :search_first OR tu.last_name LIKE :search_last OR CAST(tu.telegram_id AS CHAR) LIKE :search_id)';
        $like = '%' . $search . '%';
        $params['search_username'] = $like;
        $params['search_first'] = $like;
        $params['search_last'] = $like;
        $params['search_id'] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM telegram_users tu WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $listSql = "SELECT
            tu.id,
            tu.telegram_id,
            tu.username,
            tu.first_name,
            tu.last_name,
            tu.language_code,
            tu.created_at,
            tu.updated_at,
            (
                SELECT COUNT(*)
                FROM search_history sh
                WHERE sh.user_id = tu.id
            ) AS searches_count,
            (
                SELECT sh.searched_at
                FROM search_history sh
                WHERE sh.user_id = tu.id
                ORDER BY sh.searched_at DESC
                LIMIT 1
            ) AS last_search_at
        FROM telegram_users tu
        WHERE {$whereSql}
        ORDER BY tu.updated_at DESC
        LIMIT :limit OFFSET :offset";

    $listStmt = $pdo->prepare($listSql);
    foreach ($params as $key => $value) {
        $listStmt->bindValue(':' . $key, $value);
    }
    $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();

    $items = [];

    foreach ($listStmt->fetchAll() as $row) {
        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $fullName = trim($first . ' ' . $last);
        $username = trim((string) ($row['username'] ?? ''));

        $items[] = [
            'id'             => (int) $row['id'],
            'telegram_id'    => (string) $row['telegram_id'],
            'username'       => $username,
            'display_name'   => $fullName !== '' ? $fullName : ($username !== '' ? '@' . $username : 'ID ' . $row['telegram_id']),
            'initials'       => clientInitials($fullName !== '' ? $fullName : $username, (string) $row['telegram_id']),
            'searches_count' => (int) $row['searches_count'],
            'last_search_at' => $row['last_search_at'] ? (string) $row['last_search_at'] : null,
            'created_at'     => (string) $row['created_at'],
        ];
    }

    return [
        'items' => $items,
        'total' => $total,
    ];
}
