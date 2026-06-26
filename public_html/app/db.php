<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    return $pdo;
}

function db_health_check(): array
{
    try {
        $pdo = db();
        $pdo->query('SELECT 1');

        return [
            'ok' => true,
            'message' => 'DB connection is available.',
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => db_error_message($e),
        ];
    }
}

function db_error_message(Throwable $e): string
{
    if (function_exists('app_public_error_message')) {
        return app_public_error_message($e);
    }

    $message = $e->getMessage();

    if (stripos($message, 'could not find driver') !== false) {
        return 'PHPのMySQL PDOドライバ（pdo_mysql）が有効ではありません。確認環境のphp.iniで pdo_mysql を有効化してください。';
    }

    if (stripos($message, 'Access denied') !== false) {
        return 'DB接続に失敗しました。DBユーザー名・パスワードをご確認ください。';
    }

    if (stripos($message, 'Unknown database') !== false) {
        return 'DBが見つかりません。DB名をご確認ください。';
    }

    if (stripos($message, 'Base table or view not found') !== false) {
        return '必要なDBテーブルが見つかりません。schema.sql またはマイグレーションの反映状況をご確認ください。';
    }

    return APP_DEBUG ? $message : 'DB接続またはDB処理に失敗しました。';
}
