<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

startSession();

if (isLoggedIn()) {
    redirect(adminUrl('dashboard.php'));
}

redirect(adminUrl('login.php'));
