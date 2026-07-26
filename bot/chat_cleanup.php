<?php

declare(strict_types=1);

/** Idle time after which bot clears its own messages in the private chat. */
const BOT_CHAT_IDLE_SECONDS = 300;

function botChatEnsureTable(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    db()->exec(
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

    $ready = true;
}

function botChatTouch(int|string $chatId): void
{
    botChatEnsureTable();
    $id = (int) $chatId;
    $now = time();

    $stmt = db()->prepare(
        'INSERT INTO bot_chat_activity (chat_id, last_activity) VALUES (:chat_id, :ts)
         ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)'
    );
    $stmt->execute(['chat_id' => $id, 'ts' => $now]);
}

/**
 * @param list<int> $messageIds
 */
function botChatTrackMessages(int|string $chatId, array $messageIds): void
{
    botChatEnsureTable();
    $id = (int) $chatId;
    $now = time();
    $stmt = db()->prepare(
        'INSERT IGNORE INTO bot_chat_messages (chat_id, message_id, created_at)
         VALUES (:chat_id, :message_id, :created_at)'
    );

    foreach ($messageIds as $messageId) {
        $messageId = (int) $messageId;
        if ($messageId <= 0) {
            continue;
        }
        $stmt->execute([
            'chat_id'     => $id,
            'message_id'  => $messageId,
            'created_at'  => $now,
        ]);
    }

    botChatTouch($chatId);
}

/**
 * Extract Telegram message_id values from any common API response shape.
 *
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

        // sendMediaGroup wrapper: ['status'=>'ok','result'=> full API body]
        // full API body: ['ok'=>true,'result'=> Message|Message[]]
        foreach (['result', 'data'] as $key) {
            if (!isset($node[$key])) {
                continue;
            }
            $child = $node[$key];
            if (!is_array($child)) {
                continue;
            }
            // List of messages
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
    botChatEnsureTable();
    $stmt = db()->prepare('SELECT message_id FROM bot_chat_messages WHERE chat_id = :chat_id');
    $stmt->execute(['chat_id' => (int) $chatId]);
    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $ids[] = (int) $id;
    }

    return $ids;
}

function botChatLastActivity(int|string $chatId): int
{
    botChatEnsureTable();
    $stmt = db()->prepare('SELECT last_activity FROM bot_chat_activity WHERE chat_id = :chat_id');
    $stmt->execute(['chat_id' => (int) $chatId]);
    $value = $stmt->fetchColumn();

    return $value === false ? 0 : (int) $value;
}

/**
 * Delete tracked bot messages if the chat was idle longer than $idleSeconds.
 */
function botChatPurgeIfIdle(TelegramClient $client, int|string $chatId, int $idleSeconds = BOT_CHAT_IDLE_SECONDS): bool
{
    botChatEnsureTable();
    $id = (int) $chatId;
    $last = botChatLastActivity($id);
    if ($last <= 0) {
        return false;
    }

    if ((time() - $last) < $idleSeconds) {
        return false;
    }

    $ids = botChatLoadMessageIds($id);
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

    $delMsg = db()->prepare('DELETE FROM bot_chat_messages WHERE chat_id = :chat_id');
    $delMsg->execute(['chat_id' => $id]);
    $delAct = db()->prepare('DELETE FROM bot_chat_activity WHERE chat_id = :chat_id');
    $delAct->execute(['chat_id' => $id]);

    // Legacy file sessions (if any).
    $legacy = APP_ROOT . '/storage/bot_chats/chat_' . $id . '.json';
    if (is_file($legacy)) {
        @unlink($legacy);
    }

    return true;
}

/**
 * Purge all idle chat sessions (daemon / cron / webhook sweep).
 */
function botChatPurgeAllIdle(TelegramClient $client, int $idleSeconds = BOT_CHAT_IDLE_SECONDS): int
{
    botChatEnsureTable();
    $cutoff = time() - $idleSeconds;
    $stmt = db()->query(
        'SELECT chat_id FROM bot_chat_activity WHERE last_activity > 0 AND last_activity <= ' . (int) $cutoff
    );
    $purged = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $chatId) {
        if (botChatPurgeIfIdle($client, (int) $chatId, $idleSeconds)) {
            $purged++;
        }
    }

    // Also purge legacy JSON sessions.
    $dir = APP_ROOT . '/storage/bot_chats';
    if (is_dir($dir)) {
        foreach (glob($dir . '/chat_*.json') ?: [] as $file) {
            $base = basename($file, '.json');
            $chatId = substr($base, strlen('chat_'));
            if ($chatId === '' || !preg_match('/^-?[0-9]+$/', $chatId)) {
                continue;
            }
            $raw = @file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $last = is_array($data) ? (int) ($data['last_activity'] ?? 0) : 0;
            if ($last > 0 && (time() - $last) >= $idleSeconds) {
                $ids = [];
                foreach ($data['messages'] ?? [] as $mid) {
                    $ids[] = (int) $mid;
                }
                if ($ids !== []) {
                    botChatTrackMessages((int) $chatId, $ids);
                    // Force activity old enough:
                    db()->prepare('UPDATE bot_chat_activity SET last_activity = :ts WHERE chat_id = :chat_id')
                        ->execute(['ts' => time() - $idleSeconds - 1, 'chat_id' => (int) $chatId]);
                }
                if (botChatPurgeIfIdle($client, (int) $chatId, $idleSeconds)) {
                    $purged++;
                }
            }
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

/** @deprecated kept for old tests */
function botChatStorageDir(): string
{
    $dir = APP_ROOT . '/storage/bot_chats';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}
