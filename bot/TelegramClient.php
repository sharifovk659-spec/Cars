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
    public function request(string $method, array $params = [], int $timeout = 30): ?array
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
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => max(15, $timeout),
            CURLOPT_NOSIGNAL       => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('Telegram cURL error [' . $method . ']: ' . $error);
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
            error_log('Telegram API error [' . $method . ']: ' . $response);
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
     * Send main car image as document so Telegram Photos viewer has no left/right swipe to other chat photos.
     *
     * @param array<string, mixed> $options
     */
    public function sendIsolatedImage(
        int|string $chatId,
        string $photoPath,
        string $caption = '',
        array $options = [],
        string $fileName = 'car.jpg'
    ): ?array {
        $result = $this->sendIsolatedImageRequest($chatId, $photoPath, $caption, $options, true, $fileName);
        if ($result !== null || $caption === '') {
            return $result;
        }

        return $this->sendIsolatedImageRequest($chatId, $photoPath, strip_tags($caption), $options, false, $fileName);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function sendIsolatedImageRequest(
        int|string $chatId,
        string $photoPath,
        string $caption,
        array $options,
        bool $useHtml,
        string $fileName
    ): ?array {
        if (!is_file($photoPath)) {
            return null;
        }

        $mime = mime_content_type($photoPath) ?: 'image/jpeg';
        if (!str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $fileName) ?: 'car.jpg';
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $safeName)) {
            $safeName .= '.jpg';
        }

        $params = array_merge([
            'chat_id' => $chatId,
            'disable_content_type_detection' => 'true',
            'document' => new CURLFile($photoPath, $mime, $safeName),
        ], $options);

        if ($caption !== '') {
            $params['caption'] = $caption;
            if ($useHtml) {
                $params['parse_mode'] = 'HTML';
            }
        }

        return $this->request('sendDocument', $params, 90);
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

        return $this->request('sendPhoto', $params, 90);
    }

    /**
     * @param list<string> $photoPaths
     * @param array<string, mixed> $options
     */
    public function sendMediaGroup(int|string $chatId, array $photoPaths, string $caption = '', array $options = []): ?array
    {
        $paths = [];
        foreach ($photoPaths as $path) {
            if (is_string($path) && is_file($path) && filesize($path) > 0) {
                $paths[] = $path;
            }
        }

        if ($paths === []) {
            return null;
        }

        // Telegram albums accept at most 10 items.
        $paths = array_slice($paths, 0, 10);

        $result = $this->sendMediaGroupRequest($chatId, $paths, $caption, $options, true);
        if ($result !== null) {
            return $result;
        }

        if ($caption !== '') {
            $result = $this->sendMediaGroupRequest($chatId, $paths, strip_tags($caption), $options, false);
            if ($result !== null) {
                return $result;
            }
        }

        return $this->sendMediaGroupRequest($chatId, $paths, '', $options, false);
    }

    /**
     * @param list<string> $photoPaths
     * @param array<string, mixed> $options
     */
    private function sendMediaGroupRequest(
        int|string $chatId,
        array $photoPaths,
        string $caption,
        array $options,
        bool $useHtml
    ): ?array {
        $media = [];
        $params = array_merge(['chat_id' => $chatId], $options);

        foreach ($photoPaths as $index => $path) {
            $attachName = 'photo' . $index;
            $item = [
                'type'  => 'photo',
                'media' => 'attach://' . $attachName,
            ];

            if ($index === 0 && $caption !== '') {
                $item['caption'] = $caption;
                if ($useHtml) {
                    $item['parse_mode'] = 'HTML';
                }
            }

            $media[] = $item;
            $params[$attachName] = new CURLFile($path);
        }

        $params['media'] = json_encode($media, JSON_UNESCAPED_UNICODE);

        return $this->request('sendMediaGroup', $params, 120);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): ?array
    {
        $params = ['callback_query_id' => $callbackQueryId];

        if ($text !== '') {
            $params['text'] = mb_substr($text, 0, 200);
        }
        if ($showAlert) {
            $params['show_alert'] = true;
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
