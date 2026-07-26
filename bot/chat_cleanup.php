<?php

declare(strict_types=1);

/** Idle time after which bot clears its own messages in the private chat. */
const BOT_CHAT_IDLE_SECONDS = 300;

function botChatStorageDir(): string
{
    $dir = APP_ROOT . '/storage/bot_chats';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function botChatSessionPath(int|string $chatId): string
{
    return botChatStorageDir() . '/chat_' . (int) $chatId . '.json';
}

/**
 * @return array{last_activity: int, messages: list<int>}
 */
function botChatLoadFileSession(int|string $chatId): array
{
    $path = botChatSessionPath($chatId);
    if (!is_file($path)) {
        return ['last_activity' => 0, 'messages' => []];
    }

    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return ['last_activity' => 0, 'messages' => []];
    }

    $messages = [];
    foreach ($data['messages'] ?? [] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $messages[$id] = $id;
        }
    }

    return [
        'last_activity' => (int) ($data['last_activity'] ?? 0),
        'messages'      => array_values($messages),
    ];
}

/**
 * @param list<int> $messageIds
 */
function botChatSaveFileSession(int|string $chatId, int $lastActivity, array $messageIds): void
{
    $map = [];
    foreach ($messageIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $map[$id] = $id;
        }
    }

    @file_put_contents(
        botChatSessionPath($chatId),
        json_encode([
            'last_activity' => $lastActivity,
            'messages'      => array_values($map),
        ], JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function botChatEnsureTable(): void
{
    if (!empty($GLOBALS['bot_chat_tables_ready'])) {
        return;
    }

    try {
        dbEnsureConnected()->exec(
            'CREATE TABLE IF NOT EXISTS `bot_chat_messages` (
                `chat_id` BIGINT NOT NULL,
                `message_id` BIGINT NOT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`chat_id`, `message_id`),
                KEY `idx_bot_chat_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db()->exec(
            'CREATE TABLE IF NOT EXISTS `bot_chat_activity` (
                `chat_id` BIGINT NOT NULL,
                `last_activity` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`chat_id`),
                KEY `idx_bot_chat_activity_last` (`last_activity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $GLOBALS['bot_chat_tables_ready'] = true;
    } catch (Throwable $e) {
        error_log('botChatEnsureTable: ' . $e->getMessage());
    }
}

function botChatResetTableCache(): void
{
    $GLOBALS['bot_chat_tables_ready'] = false;
}

function botChatTouch(int|string $chatId): void
{
    $id = (int) $chatId;
    $now = time();
    $session = botChatLoadFileSession($id);
    botChatSaveFileSession($id, $now, $session['messages']);

    try {
        botChatEnsureTable();
        $stmt = db()->prepare(
            'INSERT INTO bot_chat_activity (chat_id, last_activity) VALUES (:chat_id, :ts)
             ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)'
        );
        $stmt->execute(['chat_id' => $id, 'ts' => $now]);
    } catch (Throwable $e) {
        error_log('botChatTouch db: ' . $e->getMessage());
    }
}

/**
 * @param list<int> $messageIds
 */
function botChatTrackMessages(int|string $chatId, array $messageIds): void
{
    $id = (int) $chatId;
    $now = time();
    $session = botChatLoadFileSession($id);
    $map = [];
    foreach ($session['messages'] as $mid) {
        $map[(int) $mid] = (int) $mid;
    }
    foreach ($messageIds as $messageId) {
        $messageId = (int) $messageId;
        if ($messageId > 0) {
            $map[$messageId] = $messageId;
        }
    }
    botChatSaveFileSession($id, $now, array_values($map));

    try {
        botChatEnsureTable();
        $stmt = db()->prepare(
            'INSERT IGNORE INTO bot_chat_messages (chat_id, message_id, created_at)
             VALUES (:chat_id, :message_id, :created_at)'
        );
        foreach ($map as $messageId) {
            $stmt->execute([
                'chat_id'    => $id,
                'message_id' => $messageId,
                'created_at' => $now,
            ]);
        }
        $act = db()->prepare(
            'INSERT INTO bot_chat_activity (chat_id, last_activity) VALUES (:chat_id, :ts)
             ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)'
        );
        $act->execute(['chat_id' => $id, 'ts' => $now]);
    } catch (Throwable $e) {
        error_log('botChatTrackMessages db: ' . $e->getMessage());
    }
}

/**
 * @param array<string, mixed>|null $apiResponse
 */
function botChatTrackFromApiResult(int|string $chatId, ?array $apiResponse): void
{
    if ($apiResponse === null) {
        return;
    }

    $ids = botChatExtractMessageIds($apiResponse);
    if ($ids !== []) {
        botChatTrackMessages($chatId, $ids);
    }
}

/**
 * @param array<string, mixed> $payload
 * @return list<int>
 */
function botChatExtractMessageIds(array $payload): array
{
    $ids = [];
    $queue = [$payload];

    while ($queue !== []) {
        $node = array_shift($queue);
        if (!is_array($node)) {
            continue;
        }

        if (isset($node['message_id']) && is_numeric($node['message_id'])) {
            $ids[(int) $node['message_id']] = (int) $node['message_id'];
        }

        foreach (['result', 'data'] as $key) {
            if (!isset($node[$key]) || !is_array($node[$key])) {
                continue;
            }
            $child = $node[$key];
            if (array_is_list($child)) {
                foreach ($child as $item) {
                    if (is_array($item)) {
                        $queue[] = $item;
                    }
                }
            } else {
                $queue[] = $child;
            }
        }
    }

    return array_values($ids);
}

/**
 * @return list<int>
 */
function botChatLoadMessageIds(int|string $chatId): array
{
    return botChatLoadFileSession($chatId)['messages'];
}

function botChatLastActivity(int|string $chatId): int
{
    return botChatLoadFileSession($chatId)['last_activity'];
}

/**
 * File-based purge — used by daemon (no MySQL in the loop).
 */
function botChatPurgeIfIdle(TelegramClient $client, int|string $chatId, int $idleSeconds = BOT_CHAT_IDLE_SECONDS): bool
{
    $id = (int) $chatId;
    $session = botChatLoadFileSession($id);
    $last = (int) ($session['last_activity'] ?? 0);
    if ($last <= 0 || (time() - $last) < $idleSeconds) {
        return false;
    }

    $ids = $session['messages'] ?? [];
    if ($ids !== []) {
        foreach (array_chunk($ids, 100) as $chunk) {
            $ok = $client->deleteMessages($id, $chunk);
            if ($ok === null) {
                foreach ($chunk as $messageId) {
                    $client->deleteMessages($id, [$messageId]);
                    usleep(40000);
                }
            }
            usleep(60000);
        }
    }

    $path = botChatSessionPath($id);
    if (is_file($path)) {
        @unlink($path);
    }

    try {
        botChatEnsureTable();
        db()->prepare('DELETE FROM bot_chat_messages WHERE chat_id = :chat_id')->execute(['chat_id' => $id]);
        db()->prepare('DELETE FROM bot_chat_activity WHERE chat_id = :chat_id')->execute(['chat_id' => $id]);
    } catch (Throwable $e) {
        // File purge already done — DB cleanup is best-effort.
        error_log('botChatPurgeIfIdle db cleanup: ' . $e->getMessage());
    }

    return true;
}

/**
 * Purge all idle chats from JSON session files (daemon-safe, no MySQL required).
 */
function botChatPurgeAllIdle(TelegramClient $client, int $idleSeconds = BOT_CHAT_IDLE_SECONDS): int
{
    $purged = 0;
    foreach (glob(botChatStorageDir() . '/chat_*.json') ?: [] as $file) {
        $base = basename($file, '.json');
        $chatId = substr($base, strlen('chat_'));
        if ($chatId === '' || !preg_match('/^-?[0-9]+$/', $chatId)) {
            continue;
        }
        if (botChatPurgeIfIdle($client, (int) $chatId, $idleSeconds)) {
            $purged++;
        }
    }

    return $purged;
}

function botChatSweepIdleInBackground(TelegramClient $client): void
{
    try {
        botChatPurgeAllIdle($client, BOT_CHAT_IDLE_SECONDS);
    } catch (Throwable $e) {
        error_log('botChatSweepIdleInBackground: ' . $e->getMessage());
    }
}
