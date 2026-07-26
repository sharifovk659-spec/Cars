<?php

declare(strict_types=1);

/**
 * Background loop: every 30s purge bot chats idle for 5+ minutes.
 * Started on deploy with nohup so chats clear even if the user never returns.
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/telegram.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/bot/TelegramClient.php';
require_once dirname(__DIR__) . '/bot/helpers.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$token = getBotToken();
if ($token === '') {
    fwrite(STDERR, "Bot token missing\n");
    exit(1);
}

$dir = botChatStorageDir();
$pidFile = $dir . '/daemon.pid';
@file_put_contents($pidFile, (string) getmypid());

$client = new TelegramClient($token);

while (true) {
    try {
        botChatPurgeAllIdle($client, BOT_CHAT_IDLE_SECONDS);
    } catch (Throwable $e) {
        error_log('bot chat cleanup daemon: ' . $e->getMessage());
    }

    sleep(30);
}
