<?php

declare(strict_types=1);

/**
 * Background loop: every 30s purge bot chats idle for 5+ minutes.
 * Reconnects MySQL every cycle — Hostinger closes idle connections ("server has gone away").
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
        // Fresh DB connection every cycle (prevents MySQL gone away).
        dbEnsureConnected();
        // Reset table-ensure cache after reconnect.
        botChatResetTableCache();

        $purged = botChatPurgeAllIdle($client, BOT_CHAT_IDLE_SECONDS);
        if ($purged > 0) {
            @file_put_contents(
                $logFile,
                date('Y-m-d H:i:s') . " purged={$purged} cycle={$cycles}\n",
                FILE_APPEND
            );
        }
    } catch (Throwable $e) {
        error_log('bot chat cleanup daemon: ' . $e->getMessage());
        @file_put_contents(
            $logFile,
            date('Y-m-d H:i:s') . ' ERROR ' . $e->getMessage() . "\n",
            FILE_APPEND
        );
        // Force reconnect next loop.
        try {
            db(true);
        } catch (Throwable $ignored) {
        }
    }

    sleep(30);
}
