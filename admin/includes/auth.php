<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

const REMEMBER_COOKIE = 'tc_admin_remember';
const REMEMBER_DAYS = 30;

function startSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** @return array<string, mixed>|null */
function getCurrentAdmin(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    static $admin = null;

    if ($admin === null) {
        $stmt = db()->prepare(
            'SELECT id, username, full_name, email, is_active
             FROM admins
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch() ?: null;

        if ($admin === null) {
            logoutAdmin(false);
        }
    }

    return $admin;
}

function requireAuth(): void
{
    startSession();

    if (isLoggedIn()) {
        getCurrentAdmin();
        return;
    }

    if (tryRememberLogin()) {
        return;
    }

    redirect(adminUrl('login.php'));
}

function tryRememberLogin(): bool
{
    if (empty($_COOKIE[REMEMBER_COOKIE])) {
        return false;
    }

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);

    if (count($parts) !== 2) {
        return false;
    }

    [$adminId, $token] = $parts;

    if (!ctype_digit($adminId) || $token === '') {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT id, username, full_name, email, remember_token, remember_expires
         FROM admins
         WHERE id = :id AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute(['id' => (int) $adminId]);
    $admin = $stmt->fetch();

    if (!$admin || empty($admin['remember_token']) || empty($admin['remember_expires'])) {
        return false;
    }

    if (strtotime($admin['remember_expires']) < time()) {
        clearRememberToken((int) $admin['id']);
        return false;
    }

    if (!hash_equals($admin['remember_token'], hash('sha256', $token))) {
        return false;
    }

    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];

    return true;
}

function setRememberToken(int $adminId): void
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);

    $stmt = db()->prepare(
        'UPDATE admins SET remember_token = :token, remember_expires = :expires WHERE id = :id'
    );
    $stmt->execute([
        'token'   => $hash,
        'expires' => $expires,
        'id'      => $adminId,
    ]);

    if (!headers_sent()) {
        setcookie(
            REMEMBER_COOKIE,
            $adminId . ':' . $token,
            [
                'expires'  => time() + REMEMBER_DAYS * 86400,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}

function clearRememberToken(int $adminId): void
{
    $stmt = db()->prepare(
        'UPDATE admins SET remember_token = NULL, remember_expires = NULL WHERE id = :id'
    );
    $stmt->execute(['id' => $adminId]);

    if (!headers_sent()) {
        setcookie(REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/** @return array{success: bool, error?: string} */
function loginAdmin(string $email, string $password, bool $remember): array
{
    $stmt = db()->prepare(
        'SELECT id, username, full_name, email, password_hash, is_active
         FROM admins
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => trim($email)]);
    $admin = $stmt->fetch();

    if (!$admin || !(int) $admin['is_active']) {
        return ['success' => false, 'error' => 'Email ё парол нодуруст аст'];
    }

    if (!password_verify($password, $admin['password_hash'])) {
        return ['success' => false, 'error' => 'Email ё парол нодуруст аст'];
    }

    startSession();

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];

    $update = db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
    $update->execute(['id' => $admin['id']]);

    if ($remember) {
        setRememberToken((int) $admin['id']);
    }

    logActivity((int) $admin['id'], 'admin_login', 'admin', (int) $admin['id']);

    return ['success' => true];
}

function logoutAdmin(bool $redirect = true): void
{
    startSession();

    $adminId = $_SESSION['admin_id'] ?? null;

    if ($adminId) {
        logActivity((int) $adminId, 'admin_logout', 'admin', (int) $adminId);
        clearRememberToken((int) $adminId);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies') && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if ($redirect && !headers_sent()) {
        redirect(adminUrl('login.php'));
    }
}
