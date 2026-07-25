<?php

declare(strict_types=1);

const ADMIN_LOCALES = ['ru', 'tj', 'en'];
const ADMIN_LOCALE_COOKIE = 'tc_admin_locale';
const ADMIN_LOCALE_DEFAULT = 'ru';

/** @var array<string, string>|null */
$GLOBALS['_admin_translations'] = null;

function adminBootstrapLocale(): void
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], ADMIN_LOCALES, true)) {
        adminSetLocale((string) $_GET['lang']);
    }
}

function adminSetLocale(string $locale): void
{
    if (!in_array($locale, ADMIN_LOCALES, true)) {
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        startSession();
    }

    $_SESSION['admin_locale'] = $locale;
    $GLOBALS['_admin_translations'] = null;

    if (!headers_sent()) {
        setcookie(ADMIN_LOCALE_COOKIE, $locale, [
            'expires'  => time() + 365 * 86400,
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}

function adminLocale(): string
{
    if (!empty($_SESSION['admin_locale']) && in_array($_SESSION['admin_locale'], ADMIN_LOCALES, true)) {
        return (string) $_SESSION['admin_locale'];
    }

    if (!empty($_COOKIE[ADMIN_LOCALE_COOKIE]) && in_array($_COOKIE[ADMIN_LOCALE_COOKIE], ADMIN_LOCALES, true)) {
        return (string) $_COOKIE[ADMIN_LOCALE_COOKIE];
    }

    return ADMIN_LOCALE_DEFAULT;
}

/** @return array<string, string> */
function adminTranslations(): array
{
    if ($GLOBALS['_admin_translations'] !== null) {
        return $GLOBALS['_admin_translations'];
    }

    $locale = adminLocale();
    $path = __DIR__ . '/../lang/' . $locale . '.php';

    if (!is_file($path)) {
        $path = __DIR__ . '/../lang/' . ADMIN_LOCALE_DEFAULT . '.php';
    }

    /** @var array<string, string> $strings */
    $strings = require $path;
    $GLOBALS['_admin_translations'] = $strings;

    return $strings;
}

function __(string $key, array $replace = []): string
{
    $strings = adminTranslations();
    $text = $strings[$key] ?? $key;

    foreach ($replace as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }

    return $text;
}

/** @return array<string, string> */
function adminJsStrings(): array
{
    return [
        'delete_confirm'      => __('js.delete_confirm'),
        'main_photo'          => __('js.main_photo'),
        'move_back'           => __('js.move_back'),
        'move_forward'        => __('js.move_forward'),
        'remove_photo'        => __('js.remove_photo'),
        'min_one_photo'       => __('js.min_one_photo'),
        'min_one_photo_remain'=> __('js.min_one_photo_remain'),
        'file_type'           => __('js.file_type'),
        'file_size'           => __('js.file_size'),
        'compressing_photos'  => __('js.compressing_photos'),
        'publishing_car'      => __('js.publishing_car'),
        'upload_progress'     => __('js.upload_progress'),
        'max_photos'          => __('js.max_photos'),
        'theme_light'         => __('theme.light'),
        'theme_dark'          => __('theme.dark'),
        'signing_in'          => __('auth.signing_in'),
        'vin_found'           => __('js.vin_found'),
        'upload_status_vagon' => __('js.upload_status_vagon'),
        'upload_status_treiler' => __('js.upload_status_treiler'),
        'dashboard_search_no_results' => __('dashboard.search_no_results'),
        'dashboard_open'            => __('dashboard.open'),
        'dashboard_no_photo'        => __('dashboard.no_photo'),
        'common_dash'               => __('common.dash'),
        'dashboard_search_typing'   => __('dashboard.search_typing'),
        'dashboard_receive'         => __('dashboard.receive'),
        'dashboard_upload'          => __('dashboard.upload'),
        'dashboard_photos_count'    => __('dashboard.photos_count'),
        'dashboard_contact'         => __('cars.contact'),
    ];
}

function adminLocaleSwitcher(): void
{
    $current = adminLocale();
    $setLocaleUrl = adminUrl('set-locale.php');
    $labels = [
        'ru' => 'Русский',
        'tj' => 'Тоҷикӣ',
        'en' => 'English',
    ];
    ?>
    <select class="locale-switch" id="localeSwitcher" aria-label="<?= e(__('locale.label')) ?>">
        <?php foreach ($labels as $code => $label): ?>
            <option value="<?= e($code) ?>"<?= $current === $code ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <script>
    (function () {
        var select = document.getElementById('localeSwitcher');
        if (!select) return;
        select.addEventListener('change', function () {
            var redirect = window.location.pathname + window.location.search + window.location.hash;
            window.location.href = '<?= e($setLocaleUrl) ?>?lang=' + encodeURIComponent(select.value)
                + '&redirect=' + encodeURIComponent(redirect);
        });
    })();
    </script>
    <?php
}

function adminThemeToggleButton(): string
{
    $lightLabel = e(__('theme.light'));
    $darkLabel = e(__('theme.dark'));

    return <<<HTML
<button type="button" class="theme-toggle" id="themeToggle" data-theme-state="dark" aria-label="{$lightLabel}">
    <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4"></circle>
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
    </svg>
    <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
    </svg>
</button>
HTML;
}

/** @return array<int, array{href: string, label: string, icon: string, key: string}> */
function adminNavItems(): array
{
    return [
        ['key' => 'dashboard',      'href' => adminUrl('dashboard.php'),            'label' => __('nav.dashboard'),      'icon' => '⌂'],
        ['key' => 'cars',           'href' => adminUrl('cars/index.php'),           'label' => __('nav.cars'),           'icon' => '🚗'],
        ['key' => 'cars-add',       'href' => adminUrl('cars/add.php'),             'label' => __('nav.cars_add'),       'icon' => '＋'],
        ['key' => 'users',          'href' => adminUrl('users/index.php'),          'label' => __('nav.users'),          'icon' => '👤'],
        ['key' => 'search-history', 'href' => adminUrl('search-history/index.php'), 'label' => __('nav.search_history'), 'icon' => '🔍'],
        ['key' => 'activity',       'href' => adminUrl('activity/index.php'),       'label' => __('nav.activity'),       'icon' => '📋'],
        ['key' => 'settings',       'href' => adminUrl('settings/index.php'),       'label' => __('nav.settings'),       'icon' => '⚙'],
        ['key' => 'logout',         'href' => adminUrl('logout.php'),               'label' => __('nav.logout'),         'icon' => '⎋'],
    ];
}

function adminActivityLabel(string $action): string
{
    $key = 'activity.' . $action;
    $label = __($key);

    return $label === $key ? $action : $label;
}
