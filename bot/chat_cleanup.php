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
    return botChatStorageDir() . '/chat_' . preg_replace('/[^0-9-]/', '', (string) $chatId) . '.json';
}

/**
 * @return array{last_activity: int, messages: list<int>}
 */
function botChatLoadSession(int|string $chatId): array
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
 * @param array{last_activity?: int, messages?: list<int>} $session
 */
function botChatSaveSession(int|string $chatId, array $session): void
{
    $messages = [];
    foreach ($session['messages'] ?? [] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $messages[$id] = $id;
        }
    }

    $payload = [
        'last_activity' => (int) ($session['last_activity'] ?? time()),
        'messages'      => array_values($messages),
    ];

    @file_put_contents(
        botChatSessionPath($chatId),
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function botChatTouch(int|string $chatId): void
{
    $session = botChatLoadSession($chatId);
    $session['last_activity'] = time();
    botChatSaveSession($chatId, $session);
}

/**
 * @param list<int> $messageIds
 */
function botChatTrackMessages(int|string $chatId, array $messageIds): void
{
    $session = botChatLoadSession($chatId);
    $map = [];
    foreach ($session['messages'] as $id) {
        $map[(int) $id] = (int) $id;
    }
    foreach ($messageIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $map[$id] = $id;
        }
    }

    $session['messages'] = array_values($map);
    $session['last_activity'] = time();
    botChatSaveSession($chatId, $session);
}

/**
 * @param array<string, mixed>|null $apiResponse Full Telegram API response or media-group wrapper.
 */
function botChatTrackFromApiResult(int|string $chatId, ?array $apiResponse): void
{
    if ($apiResponse === null) {
        return;
    }

    $result = $apiResponse['result'] ?? null;
    if ($result === null && isset($apiResponse['status'])) {
        // sendMediaGroup wrapper: ['status' => ..., 'result' => full API response]
        $nested = $apiResponse['result'] ?? null;
        if (is_array($nested)) {
            $result = $nested['result'] ?? $nested;
        }
    }

    $ids = [];
    if (is_array($result)) {
        if (isset($result['message_id'])) {
            $ids[] = (int) $result['message_id'];
        } else {
            foreach ($result as $item) {
                if (is_array($item) && isset($item['message_id'])) {
                    $ids[] = (int) $item['message_id'];
                }
            }
        }
    }

    if ($ids !== []) {
        botChatTrackMessages($chatId, $ids);
    }
}

/**
 * Delete tracked bot messages if the chat was idle longer than $idleSeconds.
 */
function botChatPurgeIfIdle(TelegramClient $client, int|string $chatId, int $idleSeconds = BOT_CHAT_IDLE_SECONDS): bool
{
    $session = botChatLoadSession($chatId);
    $last = (int) ($session['last_activity'] ?? 0);
    if ($last <= 0) {
        return false;
    }

    if ((time() - $last) < $idleSeconds) {
        return false;
    }

    $ids = $session['messages'] ?? [];
    if ($ids !== []) {
        foreach (array_chunk($ids, 100) as $chunk) {
            $client->deleteMessages($chatId, $chunk);
            usleep(50000);
        }
    }

    botChatSaveSession($chatId, ['last_activity' => 0, 'messages' => []]);

    return true;
}

/**
 * Purge all idle chat sessions (for cron).
 */
function botChatPurgeAllIdle(TelegramClient $client, int $idleSeconds = BOT_CHAT_IDLE_SECONDS): int
{
    $dir = botChatStorageDir();
    $purged = 0;
    foreach (glob($dir . '/chat_*.json') ?: [] as $file) {
        $base = basename($file, '.json');
        $chatId = substr($base, strlen('chat_'));
        if ($chatId === '' || !preg_match('/^-?[0-9]+$/', $chatId)) {
            continue;
        }
        if (botChatPurgeIfIdle($client, $chatId, $idleSeconds)) {
            $purged++;
        }
    }

    return $purged;
}
