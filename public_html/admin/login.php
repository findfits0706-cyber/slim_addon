<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';

if (admin_logged_in()) {
    redirect(base_path('/admin/dashboard.php'));
}

$error = '';
$adminCssVersion = (string)filemtime(__DIR__ . '/../assets/css/admin.css');
$adminJsVersion = (string)filemtime(__DIR__ . '/../assets/js/admin.js');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = '不正な送信です。';
    } else {
        try {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $error = 'ユーザー名とパスワードを入力してください。';
            } elseif (attempt_admin_login($username, $password)) {
                redirect(base_path('/admin/dashboard.php'));
            } else {
                $error = 'ログイン情報が正しくありません。';
            }
        } catch (Throwable $e) {
            error_log('Admin login error: ' . $e->getMessage());
            $error = APP_DEBUG
                ? $e->getMessage()
                : 'ログイン処理に失敗しました。時間をおいて再度お試しください。';
        }
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>管理ログイン | Find Pilates</title>
  <link rel="stylesheet" href="<?= h(asset_url('/assets/css/admin.css') . '?v=' . $adminCssVersion) ?>">
</head>
<body>
  <main class="admin-wrap">
    <section class="admin-card admin-card--narrow">
      <h1>Find Pilates 管理ログイン</h1>
      <?php if ($error !== ''): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>ユーザー名<input name="username" value="<?= h($_POST['username'] ?? '') ?>" required></label>
        <label>パスワード<input type="password" name="password" required></label>
        <button type="submit">ログイン</button>
      </form>
    </section>
  </main>
  <script src="<?= h(asset_url('/assets/js/admin.js') . '?v=' . $adminJsVersion) ?>"></script>
</body>
</html>
