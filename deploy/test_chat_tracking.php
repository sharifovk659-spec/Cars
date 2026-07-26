<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/telegram.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../bot/TelegramClient.php';
require_once __DIR__ . '/../bot/helpers.php';

botChatEnsureTable();

echo "tables ok\n";

botChatTrackMessages(999001, [111, 222]);
$fakePhoto = ['ok' => true, 'result' => ['message_id' => 555, 'chat' => ['id' => 999001]]];
botChatTrackFromApiResult(999001, $fakePhoto);

$fakeAlbum = [
    'status' => 'ok',
    'result' => [
        'ok' => true,
        'result' => [
            ['message_id' => 701],
            ['message_id' => 702],
            ['message_id' => 703],
        ],
    ],
];
botChatTrackFromApiResult(999002, $fakeAlbum);

echo 'chat 999001 ids=' . json_encode(botChatLoadMessageIds(999001)) . ' last=' . botChatLastActivity(999001) . PHP_EOL;
echo 'chat 999002 ids=' . json_encode(botChatLoadMessageIds(999002)) . ' last=' . botChatLastActivity(999002) . PHP_EOL;
echo 'extract album=' . json_encode(botChatExtractMessageIds($fakeAlbum)) . PHP_EOL;

// cleanup test rows
db()->exec('DELETE FROM bot_chat_messages WHERE chat_id IN (999001,999002)');
db()->exec('DELETE FROM bot_chat_activity WHERE chat_id IN (999001,999002)');
echo "cleanup ok\n";
