<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

/**
 * Санҷиши пайвасти базаи маълумот
 *
 * @return array{ok: bool, message: string}
 */
function testDatabaseConnection(): array
{
    try {
        $pdo = db();
        $stmt = $pdo->query('SELECT 1 AS connected');
        $result = $stmt->fetch();

        if ($result && (int) $result['connected'] === 1) {
            return ['ok' => true, 'message' => 'Пайвасти базаи маълумот муваффақ аст'];
        }

        return ['ok' => false, 'message' => 'Пайвасти базаи маълумот номуваффақ аст'];
    } catch (PDOException $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}
