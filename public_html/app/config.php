<?php
declare(strict_types=1);

// Keep /app protected by .htaccess or move it outside public_html in production.

define('APP_NAME', 'Find Pilates Trial Reservation');
define('APP_URL', 'https://findpilates.jp');
define('APP_BASE_PATH', '');

date_default_timezone_set('Asia/Tokyo');

$privateConfigPath = dirname(__DIR__, 2) . '/config/findpilates.php';
$privateConfig = is_file($privateConfigPath) ? require $privateConfigPath : [];
if (!is_array($privateConfig)) {
    $privateConfig = [];
}

function app_config_value(array $privateConfig, string $key, string $envName, string $default = ''): string
{
    $envValue = getenv($envName);
    if ($envValue !== false && $envValue !== '') {
        return (string)$envValue;
    }

    return (string)($privateConfig[$key] ?? $default);
}

define('DB_HOST', app_config_value($privateConfig, 'db_host', 'FINDPILATES_DB_HOST'));
define('DB_NAME', app_config_value($privateConfig, 'db_name', 'FINDPILATES_DB_NAME'));
define('DB_USER', app_config_value($privateConfig, 'db_user', 'FINDPILATES_DB_USER'));
define('DB_PASS', app_config_value($privateConfig, 'db_pass', 'FINDPILATES_DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

define('ADMIN_EMAIL', app_config_value($privateConfig, 'admin_email', 'FINDPILATES_ADMIN_EMAIL', 'findsportsclub@outlook.jp'));
define('FROM_EMAIL', app_config_value($privateConfig, 'from_email', 'FINDPILATES_FROM_EMAIL', 'info@findsports.jp'));
define('FROM_NAME', app_config_value($privateConfig, 'from_name', 'FINDPILATES_FROM_NAME', 'Find Pilates'));

define('BOOKING_WINDOW_DAYS', 60);

// Set true only in development.
define('APP_DEBUG', false);

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e): void {
        error_log('Uncaught application error: ' . $e->getMessage());

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $message = app_public_error_message($e);
        echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>処理を完了できませんでした | Find Pilates</title>';
        echo '<style>body{margin:0;background:#f8f5f1;color:#2f2a28;font-family:Meiryo,sans-serif;line-height:1.8}.box{max-width:720px;margin:12vh auto;padding:28px;background:#fff;border:1px solid rgba(47,42,40,.14);border-radius:8px;box-shadow:0 18px 48px rgba(47,42,40,.08)}.note{color:#766e68}.btn{display:inline-block;margin-top:18px;padding:12px 18px;border-radius:999px;background:#2f2a28;color:#fff;text-decoration:none;font-weight:700}</style>';
        echo '</head><body><main class="box">';
        echo '<h1>処理を完了できませんでした</h1>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        echo '<p class="note">通信状態をご確認のうえ、画面を再読み込みしてもう一度お試しください。保存操作の場合、二重登録を避けるため、一覧画面で反映状況を確認してから再実行してください。</p>';
        echo '<a class="btn" href="javascript:history.back()">前の画面へ戻る</a>';
        echo '</main></body></html>';
    });
}

function app_public_error_message(Throwable $e): string
{
    $message = $e->getMessage();

    if (stripos($message, 'could not find driver') !== false) {
        return 'PHPのMySQL PDOドライバ（pdo_mysql）が有効ではありません。確認環境のPHP設定をご確認ください。';
    }
    if (stripos($message, 'server has gone away') !== false || stripos($message, 'Lost connection') !== false || stripos($message, 'SQLSTATE[HY000] [2002]') !== false) {
        return 'DBサーバーとの通信が一時的に切断されました。通信状態を確認してから再度お試しください。';
    }
    if (stripos($message, 'Access denied') !== false) {
        return 'DB接続に失敗しました。DBユーザー名・パスワードをご確認ください。';
    }
    if (stripos($message, 'Base table or view not found') !== false) {
        return '必要なDBテーブルが見つかりません。マイグレーションの反映状況をご確認ください。';
    }

    return APP_DEBUG ? $message : '一時的な通信エラー、またはサーバー側の処理エラーが発生しました。';
}
