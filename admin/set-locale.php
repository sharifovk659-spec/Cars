<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

startSession();

$lang = trim($_GET['lang'] ?? '');

if (in_array($lang, ADMIN_LOCALES, true)) {
    adminSetLocale($lang);
}

$redirect = trim($_GET['redirect'] ?? '');
$fallback = adminUrl('dashboard.php');

if ($redirect === '' || str_contains($redirect, '..')) {
    redirect($fallback);
}

if (str_starts_with($redirect, '/')) {
    redirect($redirect);
}

$appHost = parse_url(APP_URL, PHP_URL_HOST);
$parsed = parse_url($redirect);

if (
    is_array($parsed)
    && ($parsed['host'] ?? '') === $appHost
    && str_contains((string) ($parsed['path'] ?? ''), '/admin')
) {
    redirect($redirect);
}

redirect($fallback);
