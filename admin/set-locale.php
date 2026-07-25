<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

startSession();

$lang = trim($_GET['lang'] ?? '');

if (in_array($lang, ADMIN_LOCALES, true)) {
    adminSetLocale($lang);
}

$redirect = trim($_GET['redirect'] ?? '');

if ($redirect === '' || str_contains($redirect, '..') || !str_starts_with($redirect, '/')) {
    redirect(adminUrl('dashboard.php'));
}

redirect($redirect);
