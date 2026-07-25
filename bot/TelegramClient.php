<?php

declare(strict_types=1);

class TelegramClient
{
    public function __construct(
        private readonly string $token
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function request(string $method, array $params = []): ?array
    {
        if ($this->token === '') {
            return null;
        }

        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_NOSIGNAL       => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('Telegram cURL error: ' . $error);
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
            error_log('Telegram API error: ' . $response);
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): ?array
    {
        $result = $this->request('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $options));

        if ($result !== null) {
            return $result;
        }

        $plain = $options;
        unset($plain['parse_mode']);

        return $this->request('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text'    => strip_tags($text),
        ], $plain));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function sendPhoto(int|string $chatId, string $photoPath, string $caption = '', array $options = []): ?array
    {
        $result = $this->sendPhotoRequest($chatId, $photoPath, $caption, $options, true);

        if ($result !== null || $caption === '') {
            return $result;
        }

        return $this->sendPhotoRequest($chatId, $photoPath, strip_tags($caption), $options, false);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function sendPhotoRequest(
        int|string $chatId,
        string $photoPath,
        string $caption,
        array $options,
        bool $useHtml
    ): ?array {
        $params = array_merge([
            'chat_id' => $chatId,
        ], $options);

        if ($caption !== '') {
            $params['caption'] = $caption;

            if ($useHtml) {
                $params['parse_mode'] = 'HTML';
            }
        }

        if (is_file($photoPath)) {
            $params['photo'] = new CURLFile($photoPath);
        } else {
            $params['photo'] = $photoPath;
        }

        return $this->request('sendPhoto', $params);
    }

    /**
     * @param list<string> $photoPaths
     * @param array<string, mixed> $options
     */
    public function sendMediaGroup(int|string $chatId, array $photoPaths, string $caption = '', array $options = []): ?array
    {
        if ($photoPaths === []) {
            return null;
        }

        $media = [];
        $params = ['chat_id' => $chatId];

        foreach ($photoPaths as $index => $path) {
            $attachName = 'photo' . $index;
            $item = [
                'type'  => 'photo',
                'media' => 'attach://' . $attachName,
            ];

            if ($index === 0 && $caption !== '') {
                $item['caption'] = $caption;
                $item['parse_mode'] = 'HTML';
            }

            $media[] = $item;

            if (is_file($path)) {
                $params[$attachName] = new CURLFile($path);
            }
        }

        $params['media'] = json_encode($media, JSON_UNESCAPED_UNICODE);

        return $this->request('sendMediaGroup', array_merge($params, $options));
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): ?array
    {
        $params = ['callback_query_id' => $callbackQueryId];

        if ($text !== '') {
            $params['text'] = $text;
        }

        return $this->request('answerCallbackQuery', $params);
    }

    /**
     * @param array<string, mixed> $menuButton
     */
    public function setChatMenuButton(array $menuButton, int|string|null $chatId = null): ?array
    {
        $params = [
            'menu_button' => json_encode($menuButton, JSON_UNESCAPED_UNICODE),
        ];

        if ($chatId !== null) {
            $params['chat_id'] = $chatId;
        }

        return $this->request('setChatMenuButton', $params);
    }
}
