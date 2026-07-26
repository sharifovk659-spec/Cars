<?php

declare(strict_types=1);

/**
 * Background loop: every 30s purge bot chats idle for 5+ minutes.
 * Uses JSON session files only in the loop — no MySQL (Hostinger drops idle DB sockets).
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
$logFile = $dir . '/daemon.log';
@file_put_contents($pidFile, (string) getmypid());

$client = new TelegramClient($token);
$cycles = 0;

while (true) {
    $cycles++;
    try {
        $purged = botChatPurgeAllIdle($client, BOT_CHAT_IDLE_SECONDS);
        $line = date('Y-m-d H:i:s') . " cycle={$cycles} purged={$purged}\n";
        if ($purged > 0 || $cycles % 10 === 0) {
            @file_put_contents($logFile, $line, FILE_APPEND);
        }
    } catch (Throwable $e) {
        error_log('bot chat cleanup daemon: ' . $e->getMessage());
        @file_put_contents(
            $logFile,
            date('Y-m-d H:i:s') . ' ERROR ' . $e->getMessage() . "\n",
            FILE_APPEND
        );
    }

    sleep(30);
}
