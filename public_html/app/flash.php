<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

function flash_set(string $type, string $message): void
{
    ensure_session_started();
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_take(): array
{
    ensure_session_started();
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}
