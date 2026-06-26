<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';

ensure_session_started();

function admin_logged_in(): bool
{
    $userId = (int)($_SESSION['admin_user_id'] ?? 0);
    return $userId > 0 && (string)($_SESSION['admin_username'] ?? '') !== '';
}

function admin_user(): ?array
{
    if (!admin_logged_in()) {
        return null;
    }

    return [
        'id' => (int)$_SESSION['admin_user_id'],
        'username' => (string)($_SESSION['admin_username'] ?? ''),
        'display_name' => (string)($_SESSION['admin_display_name'] ?? ''),
    ];
}

function attempt_admin_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash, display_name FROM admin_users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, (string)$user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int)$user['id'];
        $_SESSION['admin_username'] = (string)$user['username'];
        $_SESSION['admin_display_name'] = (string)$user['display_name'];
        $_SESSION['admin_authenticated_at'] = time();
        $_SESSION['admin_last_activity_at'] = time();

        return true;
    }

    return false;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect(base_path('/admin/login.php'));
    }

    $_SESSION['admin_last_activity_at'] = time();
}
