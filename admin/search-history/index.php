<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireAuth();

renderPlaceholderPage(__('nav.search_history'), 'search-history', __('search_history.placeholder'));
