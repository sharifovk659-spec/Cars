<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? null;

    if (!verifyCsrf($token)) {
        http_response_code(403);
        exit('CSRF token invalid.');
    }
}
