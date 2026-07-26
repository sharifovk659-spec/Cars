<?php

declare(strict_types=1);

/**
 * Cron: clear idle bot chats (default every minute).
 * Example crontab:
 *   * * * * * php /path/to/carsbot/deploy/cleanup_bot_chats.php >/dev/null 2>&1
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/telegram.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/bot/TelegramClient.php';
require_once dirname(__DIR__) . '/bot/helpers.php';

$token = getBotToken();
if ($token === '') {
    fwrite(STDERR, "Bot token missing\n");
    exit(1);
}

$client = new TelegramClient($token);
$purged = botChatPurgeAllIdle($client, BOT_CHAT_IDLE_SECONDS);

echo 'Purged idle chats: ' . $purged . PHP_EOL;
