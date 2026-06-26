<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./');
    exit;
}

if (!verify_csrf_token(post_string('csrf_token'))) {
    http_response_code(400);
    exit('不正な送信です。ページを再読み込みしてもう一度お試しください。');
}

$formSession = $_SESSION['admission_form'] ?? null;
if (!is_array($formSession) || empty($formSession['data']) || empty($formSession['fees'])) {
    header('Location: ./');
    exit;
}

$data = $formSession['data'];
$fees = calculate_fees($config, $data);
$photo = $formSession['photo'] ?? ['ok' => false, 'path' => '', 'filename' => '', 'mime' => '', 'error' => ''];
$idempotencyKey = (string)($formSession['idempotency_key'] ?? '');

$createdAt = (int)($formSession['created_at'] ?? 0);
if ($createdAt <= 0 || (time() - $createdAt) > 3600) {
    if (!empty($photo['path'])) {
        delete_tmp_file($photo['path']);
    }
    unset($_SESSION['admission_form']);
    http_response_code(400);
    ?>
    <!doctype html>
    <html lang="ja">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
      <title>送信期限切れ - <?= h($config['site']['name']) ?></title>
      <link rel="stylesheet" href="./css/style.css">
    </head>
    <body>
      <header class="hero">
        <div class="wrap">
          <div class="brandline"><span class="mark">F</span><?= h($config['site']['name']) ?></div>
          <h1>送信期限が切れました</h1>
          <p class="lead">安全のため、確認画面を開いてから時間が経過した申込データを破棄しました。</p>
        </div>
      </header>
      <main class="wrap layout">
        <section class="panel">
          <fieldset>
            <legend>再入力のお願い</legend>
            <p>お手数ですが、最初からもう一度入力してください。</p>
            <div class="confirm-actions"><a class="btn" href="./">入会受付フォームへ戻る</a></div>
          </fieldset>
        </section>
      </main>
    </body>
    </html>
    <?php
    exit;
}

$existingRecord = $idempotencyKey !== '' ? admission_find_record_by_idempotency_key($idempotencyKey) : null;
$record = $existingRecord;
$archivedPhoto = $photo;
$dbSaved = $existingRecord !== null;
$saveError = '';

if ($record === null) {
    try {
        $archivedPhoto = archive_admission_photo($config, $photo);
        $record = build_admission_record($config, $data, $fees, $archivedPhoto, [
            'status' => 'new',
            'mail_status' => [
                'admin_sent' => false,
                'user_sent' => false,
                'saved_before_mail' => true,
            ],
        ]);
        $record = admission_save_record_to_db($record, $idempotencyKey);
        $dbSaved = true;
    } catch (Throwable $e) {
        $archivedPath = (string)($archivedPhoto['archived_path'] ?? '');
        if ($archivedPath !== '' && $archivedPath !== (string)($photo['path'] ?? '')) {
            delete_tmp_file($archivedPath);
        }
        $saveError = $e->getMessage();
        error_log('Admission DB save failed: ' . $saveError);
    }
}

$adminBody = build_admin_mail_body($config, $data, $fees, $archivedPhoto);
$userBody = build_user_mail_body($config, $data, $fees);

$safeSubjectName = preg_replace('/[\r\n]+/', ' ', (string)$data['name']) ?? '';
$adminSubject = $config['mail']['admin_subject_prefix']
    . '：' . $safeSubjectName
    . '：' . date_label($data['start_date']);
$userSubject = $config['mail']['user_subject'];

$attachments = [];
$attachmentPath = (string)($archivedPhoto['archived_path'] ?? $archivedPhoto['path'] ?? '');
if (!empty($archivedPhoto['ok']) && $attachmentPath !== '' && is_file($attachmentPath)) {
    $attachments[] = [
        'path' => $attachmentPath,
        'filename' => $archivedPhoto['filename'] ?: basename($attachmentPath),
        'mime' => $archivedPhoto['mime'] ?: 'image/jpeg',
    ];
}

$adminSent = false;
$userSent = false;

if ($dbSaved && $existingRecord === null) {
    $adminSent = send_mail_utf8(
        (string)$config['mail']['admin_to'],
        $adminSubject,
        $adminBody,
        (string)$config['mail']['from'],
        (string)$config['mail']['from_name'],
        $attachments
    );

    if (!empty($data['email'])) {
        $userSent = send_mail_utf8(
            (string)$data['email'],
            $userSubject,
            $userBody,
            (string)$config['mail']['from'],
            (string)$config['mail']['from_name']
        );
    }

    if (is_array($record)) {
        $record['mail_status'] = [
            'admin_sent' => $adminSent,
            'user_sent' => $userSent,
            'saved_before_mail' => true,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        admission_update_mail_status((string)$record['id'], $record['mail_status']);
    }
}

$success = $dbSaved;

if ($success) {
    if (!empty($photo['path']) && (string)$photo['path'] !== $attachmentPath) {
        delete_tmp_file($photo['path']);
    }
    unset($_SESSION['admission_form']);
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= $success ? '送信完了' : '送信エラー' ?> - <?= h($config['site']['name']) ?></title>
  <link rel="stylesheet" href="./css/style.css">
</head>
<body>

<header class="hero">
  <div class="wrap">
    <div class="brandline"><span class="mark">F</span><?= h($config['site']['name']) ?></div>
    <?php if ($success): ?>
      <h1>入会受付を送信しました</h1>
      <p class="lead">内容確認後、3営業日以内に店頭手続き希望日の確認・調整についてご連絡いたします。</p>
      <div class="badges">
        <span class="badge">送信完了</span>
        <span class="badge"><?= $userSent ? '自動返信メール送信済み' : '控えメール未送信' ?></span>
      </div>
    <?php else: ?>
      <h1>送信できませんでした</h1>
      <p class="lead">申込情報を保存できませんでした。時間をおいて再送信するか、店舗へお問い合わせください。</p>
      <div class="badges"><span class="badge">送信エラー</span></div>
    <?php endif; ?>
  </div>
</header>

<main class="wrap layout">
  <section class="panel">
    <fieldset>
      <?php if ($success): ?>
        <legend>受付内容</legend>
        <?php if ($userSent): ?>
          <div class="alert ok show">入力いただいたメールアドレス宛に控えメールを送信しました。</div>
        <?php else: ?>
          <div class="alert warn show">受付は保存済みです。メール送信に失敗した、または二重送信防止により再送信を省略しました。</div>
        <?php endif; ?>

        <div class="confirm-section">
          <h2>お申し込み内容</h2>
          <table class="confirm-table"><tbody>
            <tr><th>氏名</th><td><?= h($data['name']) ?></td></tr>
            <tr><th>受付ID</th><td><?= h((string)($record['id'] ?? '')) ?></td></tr>
            <tr><th>選択内容</th><td><?= h($fees['course_label']) ?></td></tr>
            <tr><th>利用開始希望日</th><td><?= h(date_label($data['start_date'])) ?></td></tr>
            <tr><th>初回概算合計</th><td><?= h(yen($fees['initial_total'])) ?></td></tr>
            <tr><th>第1希望</th><td><?= h(date_label($data['procedure_date_1'])) ?> <?= h(time_slot_label($config, $data['procedure_time_1'])) ?></td></tr>
          </tbody></table>
        </div>

        <div class="confirm-section">
          <h2>店頭手続き時に行うこと</h2>
          <ul>
            <?php foreach ($config['procedure_info']['things_to_do'] as $item): ?>
              <li><?= h($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="confirm-section">
          <h2>お持ちいただくもの</h2>
          <ul>
            <?php foreach ($config['procedure_info']['things_to_bring'] as $item): ?>
              <li><?= h($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="confirm-actions">
          <a class="btn" href="../">トップページへ戻る</a>
        </div>
      <?php else: ?>
        <legend>送信状況</legend>
        <div class="alert danger show">申込情報を保存できませんでした。<?= h($saveError) ?></div>
        <div class="confirm-section">
          <table class="confirm-table"><tbody>
            <tr><th>管理者宛メール</th><td><?= $adminSent ? '送信済み' : '未送信または失敗' ?></td></tr>
            <tr><th>控えメール</th><td><?= $userSent ? '送信済み' : '未送信または失敗' ?></td></tr>
          </tbody></table>
        </div>
        <div class="confirm-actions">
          <form action="./send.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <button class="btn" type="submit">再送信する</button>
          </form>
          <a class="btn ghost" href="./">入会受付フォームへ戻る</a>
        </div>
      <?php endif; ?>
    </fieldset>
  </section>
</main>

</body>
</html>
