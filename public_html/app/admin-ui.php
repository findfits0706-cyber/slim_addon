<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/csrf.php';

function admin_nav_items(): array
{
    return [
        base_path('/admin/dashboard.php') => 'ダッシュボード',
        base_path('/admin/slots.php') => '体験枠管理',
        base_path('/admin/closures.php') => '休館日・停止',
        base_path('/admin/schedule-templates.php') => 'テンプレート',
        base_path('/admin/schedule-import.php') => 'CSV取込',
        base_path('/admin/bookings.php') => '体験申込一覧',
        base_path('/admin/admissions.php') => '入会受付',
        base_path('/admin/extension.php') => 'Edge接続',
        base_path('/admin/campaigns.php') => 'キャンペーン設定',
    ];
}

function render_admin_header(string $title, string $bodyClass = ''): void
{
    $user = admin_user();
    $flashMessages = flash_take();
    $adminCssVersion = (string)filemtime(__DIR__ . '/../assets/css/admin.css');

    echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($title) . '</title><link rel="stylesheet" href="' . h(asset_url('/assets/css/admin.css') . '?v=' . $adminCssVersion) . '"></head><body' . ($bodyClass !== '' ? ' class="' . h($bodyClass) . '"' : '') . '>';
    echo '<div class="admin-shell">';
    echo '<aside class="admin-sidebar">';
    echo '<div class="admin-header__brand">Find Pilates 管理画面</div>';
    echo '<nav class="admin-nav" aria-label="管理メニュー">';
    $currentPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    foreach (admin_nav_items() as $url => $label) {
        $navPath = parse_url($url, PHP_URL_PATH) ?: $url;
        $isCurrent = $currentPath === $navPath;
        echo '<a href="' . h($url) . '"' . ($isCurrent ? ' aria-current="page" class="is-current"' : '') . '>' . h($label) . '</a>';
    }
    echo '</nav>';
    echo '<form class="admin-logout-form" method="post" action="' . h(base_path('/admin/logout.php')) . '">';
    echo '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    echo '<button type="submit">ログアウト</button>';
    echo '</form>';
    echo '</aside>';
    echo '<div class="admin-main">';
    echo '<header class="admin-topbar">';
    echo '<div><p class="admin-topbar__eyebrow">FIND PILATES ADMIN</p><h1>' . h($title) . '</h1></div>';
    if ($user) {
        echo '<div class="admin-user">' . h($user['display_name'] !== '' ? $user['display_name'] : $user['username']) . '</div>';
    }
    echo '</header><main class="admin-wrap">';

    foreach ($flashMessages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $type = (string)($message['type'] ?? 'info');
        $body = (string)($message['message'] ?? '');
        if ($body !== '') {
            echo '<div class="flash flash--' . h($type) . '">' . h($body) . '</div>';
        }
    }
}

function render_admin_footer(): void
{
    $adminJsVersion = (string)filemtime(__DIR__ . '/../assets/js/admin.js');
    echo '</main></div></div><script src="' . h(asset_url('/assets/js/admin.js') . '?v=' . $adminJsVersion) . '"></script></body></html>';
}
