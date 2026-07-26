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
        $detailed = $this->requestDetailed($method, $params, $timeout);

        return ($detailed['ok'] ?? false) ? ($detailed['data'] ?? null) : null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{ok: bool, data?: array<string, mixed>, kind?: string, description?: string}
     */
    private function requestDetailed(string $method, array $params = [], int $timeout = 30): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'kind' => 'config', 'description' => 'empty token'];
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
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('Telegram cURL error [' . $method . ']: ' . $error);
            $kind = in_array($errno, [CURLE_OPERATION_TIMEDOUT, 28], true)
                || (defined('CURLE_OPERATION_TIMEOUTED') && $errno === CURLE_OPERATION_TIMEOUTED)
                ? 'timeout'
                : 'transport';

            return ['ok' => false, 'kind' => $kind, 'description' => $error];
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
            error_log('Telegram API error [' . $method . ']: ' . $response);
            $description = '';
            if (is_array($decoded)) {
                $description = (string) ($decoded['description'] ?? $response);
            } else {
                $description = (string) $response;
            }

            return ['ok' => false, 'kind' => 'api', 'description' => $description];
        }

        return ['ok' => true, 'data' => $decoded];
    }

    private function isSafeMediaGroupRetryError(string $description): bool
    {
        $text = strtolower($description);

        return str_contains($text, 'parse')
            || str_contains($text, 'caption')
            || str_contains($text, 'entities')
            || str_contains($text, 'message is too long');
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
     * Send image as document so Telegram does not join the chat Photos gallery
     * (no left/right swipe into other VIN photos). Still shows a large image preview.
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

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $fileName) ?: 'car.jpg';
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $safeName)) {
            $safeName .= '.jpg';
        }

        // octet-stream + disable_content_type_detection keeps the file out of Photos gallery.
        $params = array_merge([
            'chat_id' => $chatId,
            'disable_content_type_detection' => true,
            'document' => new CURLFile($photoPath, 'application/octet-stream', $safeName),
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
     * Send album. Never retries after timeout/transport errors — Telegram may already
     * have accepted the album and a retry would create duplicates.
     *
     * Uses document items by default so images stay out of the chat Photos gallery
     * (swipe stays within this car album only, not previous VIN searches).
     *
     * @param list<string> $photoPaths
     * @param array<string, mixed> $options
     * @return array{status: 'ok'|'failed'|'uncertain', result: ?array}
     */
    public function sendMediaGroup(
        int|string $chatId,
        array $photoPaths,
        string $caption = '',
        array $options = [],
        bool $asDocument = true,
        string $fileNamePrefix = 'car'
    ): array {
        $paths = [];
        $seen = [];
        foreach ($photoPaths as $path) {
            if (!is_string($path) || !is_file($path) || filesize($path) <= 0) {
                continue;
            }
            $key = strtolower($path);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $paths[] = $path;
        }

        if ($paths === []) {
            return ['status' => 'failed', 'result' => null];
        }

        // Telegram albums accept at most 10 items.
        $paths = array_slice($paths, 0, 10);

        $attempts = [];
        if ($caption !== '') {
            $attempts[] = ['caption' => $caption, 'html' => true];
            $attempts[] = ['caption' => strip_tags($caption), 'html' => false];
            $attempts[] = ['caption' => '', 'html' => false];
        } else {
            $attempts[] = ['caption' => '', 'html' => false];
        }

        foreach ($attempts as $index => $attempt) {
            $detailed = $this->sendMediaGroupRequest(
                $chatId,
                $paths,
                $attempt['caption'],
                $options,
                $attempt['html'],
                $asDocument,
                $fileNamePrefix
            );

            if ($detailed['ok'] ?? false) {
                return ['status' => 'ok', 'result' => $detailed['data'] ?? null];
            }

            $kind = (string) ($detailed['kind'] ?? 'api');
            if ($kind === 'timeout' || $kind === 'transport') {
                // Album may already be delivered — do not retry.
                return ['status' => 'uncertain', 'result' => null];
            }

            $description = (string) ($detailed['description'] ?? '');
            $hasMore = isset($attempts[$index + 1]);
            if ($hasMore && $this->isSafeMediaGroupRetryError($description)) {
                continue;
            }

            return ['status' => 'failed', 'result' => null];
        }

        return ['status' => 'failed', 'result' => null];
    }

    /**
     * @param list<string> $photoPaths
     * @param array<string, mixed> $options
     * @return array{ok: bool, data?: array<string, mixed>, kind?: string, description?: string}
     */
    private function sendMediaGroupRequest(
        int|string $chatId,
        array $photoPaths,
        string $caption,
        array $options,
        bool $useHtml,
        bool $asDocument = true,
        string $fileNamePrefix = 'car'
    ): array {
        $media = [];
        $params = array_merge(['chat_id' => $chatId], $options);
        $prefix = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $fileNamePrefix) ?: 'car';

        foreach ($photoPaths as $index => $path) {
            $attachName = 'file' . $index;
            $item = [
                'type'  => $asDocument ? 'document' : 'photo',
                'media' => 'attach://' . $attachName,
            ];

            if ($asDocument) {
                $item['disable_content_type_detection'] = true;
            }

            if ($index === 0 && $caption !== '') {
                $item['caption'] = $caption;
                if ($useHtml) {
                    $item['parse_mode'] = 'HTML';
                }
            }

            $media[] = $item;

            if ($asDocument) {
                $ext = 'jpg';
                if (preg_match('/\.(jpe?g|png|webp)$/i', $path, $m)) {
                    $ext = strtolower($m[1]);
                    if ($ext === 'jpeg') {
                        $ext = 'jpg';
                    }
                }
                $safeName = $prefix . '_' . ($index + 1) . '.' . $ext;
                $params[$attachName] = new CURLFile($path, 'application/octet-stream', $safeName);
            } else {
                $params[$attachName] = new CURLFile($path);
            }
        }

        $params['media'] = json_encode($media, JSON_UNESCAPED_UNICODE);

        return $this->requestDetailed('sendMediaGroup', $params, 120);
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

        return $this->request('answerCallbackQuery', $params, 8);
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
