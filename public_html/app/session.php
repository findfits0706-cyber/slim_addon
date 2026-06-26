<?php
declare(strict_types=1);

function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $configuredPath = (string)ini_get('session.save_path');
    $fallbackPath = realpath(__DIR__ . '/../data');
    $sessionPath = $fallbackPath !== false ? $fallbackPath . DIRECTORY_SEPARATOR . 'sessions' : (__DIR__ . '/../data/sessions');

    if ($configuredPath === '' || !is_dir($configuredPath)) {
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0775, true);
        }
        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            session_save_path($sessionPath);
        }
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
