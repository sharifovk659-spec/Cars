<?php

declare(strict_types=1);

/**
 * HTTP cron for Hostinger: clear idle bot chats every minute.
 * URL: /api/cron_bot_chats.php?key=TOKEN
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/telegram.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/bot/TelegramClient.php';
require_once dirname(__DIR__) . '/bot/helpers.php';

header('Content-Type: text/plain; charset=UTF-8');

$token = getBotToken();
$expected = hash('sha256', $token . ':bot_chat_cleanup');
$provided = (string) ($_GET['key'] ?? '');

if ($token === '' || $provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$client = new TelegramClient($token);
$purged = botChatPurgeAllIdle($client, BOT_CHAT_IDLE_SECONDS);

echo 'OK purged=' . $purged . "\n";
