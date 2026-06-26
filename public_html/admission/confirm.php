<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./');
    exit;
}

$data = collect_form_data($_POST);
$data['csrf_token'] = post_string('csrf_token');
$errors = [];
$photo = ['ok' => false, 'path' => '', 'filename' => '', 'mime' => '', 'error' => ''];

$hasPhotoData = $data['photo_data'] !== '';
$hasPhotoFile = isset($_FILES['photo_file']) && is_uploaded_file($_FILES['photo_file']['tmp_name'] ?? '');

if (($config['photo']['required'] ?? true) && !$hasPhotoData && !$hasPhotoFile) {
    $errors['photo'] = '顔写真を撮影またはアップロードしてください。';
}

if ($hasPhotoData || $hasPhotoFile) {
    $photo = handle_photo($config, $data, $_FILES);
    if (!$photo['ok']) {
        $errors['photo'] = $photo['error'] ?: '顔写真の保存に失敗しました。';
    } else {
        $data['photo_token'] = $photo['filename'];
    }
}

$errors = array_merge(validate_form($config, $data, true), $errors);
$fees = calculate_fees($config, $data);
$appliedCampaigns = array_filter(array_map(
    static function (array $campaign): string {
        if ((string)($campaign['name'] ?? '') !== '') {
            return (string)$campaign['name'];
        }
        if ((string)($campaign['code'] ?? '') !== '') {
            return (string)$campaign['code'];
        }
        return 'キャンペーン';
    },
    is_array($fees['campaign_discounts'] ?? null) ? $fees['campaign_discounts'] : []
));

if (!empty($errors)) {
    if (!empty($photo['path'])) {
        delete_tmp_file($photo['path']);
    }
    ?>
    <!doctype html>
    <html lang="ja">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
      <title>入力内容の確認 - <?= h($config['site']['name']) ?></title>
      <link rel="stylesheet" href="./css/style.css">
    </head>
    <body>
      <header class="hero">
        <div class="wrap">
          <div class="brandline"><span class="mark">F</span><?= h($config['site']['name']) ?></div>
          <h1>入力内容をご確認ください</h1>
          <p class="lead">未入力または確認が必要な項目があります。前の画面に戻り、該当箇所を修正してください。</p>
        </div>
      </header>
      <main class="wrap layout">
        <section class="panel">
          <fieldset>
            <legend>確認が必要な項目</legend>
            <div class="alert danger show">
              <ul>
                <?php foreach ($errors as $message): ?>
                  <li><?= h($message) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="confirm-actions">
              <button class="btn ghost" type="button" onclick="history.back()">戻って修正する</button>
              <a class="btn" href="./">最初から入力する</a>
            </div>
          </fieldset>
        </section>
      </main>
    </body>
    </html>
    <?php
    exit;
}

$_SESSION['admission_form'] = [
    'data' => $data,
    'fees' => $fees,
    'photo' => $photo,
    'idempotency_key' => $_SESSION['admission_form']['idempotency_key'] ?? bin2hex(random_bytes(32)),
    'created_at' => time(),
];

$token = csrf_token();
$age = calculate_age($data['birth']);
$healthLabels = health_check_labels($config, $data['health_checks']);
$photoRelativePath = !empty($photo['filename']) ? './photo-preview.php?file=' . rawurlencode($photo['filename']) : '';

function confirm_rows(array $rows): void
{
    foreach ($rows as [$label, $value]) {
        if ($value === '' || $value === null) {
            continue;
        }
        echo '<tr><th>' . h($label) . '</th><td>' . nl2br(h((string)$value)) . '</td></tr>';
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>入力内容の確認 - <?= h($config['site']['name']) ?></title>
  <link rel="stylesheet" href="./css/style.css">
</head>
<body>

<header class="hero">
  <div class="wrap">
    <div class="brandline"><span class="mark">F</span><?= h($config['site']['name']) ?></div>
    <h1>入力内容の確認</h1>
    <p class="lead">まだ送信は完了していません。内容をご確認のうえ「この内容で送信する」を押してください。</p>
    <div class="badges">
      <span class="badge">サーバー側で料金再計算済み</span>
      <span class="badge">送信前確認</span>
    </div>
  </div>
</header>

<main class="wrap layout">
  <section class="panel">
    <form action="./send.php" method="post">
      <input type="hidden" name="csrf_token" value="<?= h($token) ?>">

      <fieldset>
        <legend>確認内容</legend>

        <div class="confirm-section">
          <div class="review-head">
            <h2>利用形態・プラン</h2>
            <button class="btn ghost mini-btn" type="button" onclick="history.back()">修正する</button>
          </div>
          <table class="confirm-table"><tbody>
            <?php confirm_rows([
                ['利用形態', use_type_label($data)],
                ['現在、本館をご利用中ですか？', $data['use_type'] === 'add' ? main_member_status_label($data) : ''],
                ['本館会員番号', $data['use_type'] === 'add' ? ($data['main_member_number'] ?: '未入力') : ''],
                ['選択プラン', $fees['course_label']],
                ['内容', $fees['course_description']],
                ['月間利用可能回数', $fees['monthly_visits'] . '回'],
            ]); ?>
          </tbody></table>
        </div>

        <div class="confirm-section">
          <div class="review-head">
            <h2>料金</h2>
            <button class="btn ghost mini-btn" type="button" onclick="history.back()">修正する</button>
          </div>
          <table class="confirm-table"><tbody>
            <?php confirm_rows([
                ['利用開始希望日', date_label($data['start_date'])],
                ['初月計算区分', (string)($fees['proration']['label'] ?? '')],
                ['初月の利用可能回数', $fees['initial_visits'] . '回'],
                ['通常月会費', yen($fees['monthly_fee'])],
                ['初月会費合計', yen($fees['current_month_fee'])],
                ['本館初月会費（日割）', $data['use_type'] === 'add' ? yen($fees['main_club_initial_fee']) : ''],
                ['Find Pilates初月会費', yen($fees['pilates_current_month_fee'])],
                ['入会費', !empty($fees['join_fee']) ? yen($fees['join_fee']) : ''],
                ['手数料', !empty($fees['processing_fee']) ? yen($fees['processing_fee']) : ''],
                ['翌月会費', !empty($fees['next_month_fee']) ? yen($fees['next_month_fee']) : ''],
                ['翌月本館会費', !empty($fees['main_club_next_month_fee']) ? yen($fees['main_club_next_month_fee']) : ''],
                ['翌月Find Pilates種別', !empty($fees['pilates_next_month_fee']) ? yen($fees['pilates_next_month_fee']) : ''],
                ['本館通常月会費', $data['use_type'] === 'add' ? yen($fees['base_monthly_fee']) : ''],
                ['Find Pilates種別', $data['use_type'] === 'add' ? yen($fees['addon_fee']) : ''],
                ['キャンペーン値引', !empty($fees['campaign_discount']) ? '-' . yen($fees['campaign_discount']) : ''],
                ['適用キャンペーン', implode('、', $appliedCampaigns)],
                ['初回概算合計', yen($fees['initial_total'])],
                ['紹介・キャンペーンコード', $data['campaign_code'] ?: 'なし'],
            ]); ?>
          </tbody></table>
          <p class="fine"><?= h($config['texts']['price_notice']) ?></p>
        </div>

        <div class="confirm-section">
          <div class="review-head">
            <h2>来店希望日時</h2>
            <button class="btn ghost mini-btn" type="button" onclick="history.back()">修正する</button>
          </div>
          <table class="confirm-table"><tbody>
            <?php for ($i = 1; $i <= 3; $i++): ?>
              <tr>
                <th>第<?= h((string)$i) ?>希望</th>
                <td><?= h(date_label($data['procedure_date_' . $i]) ?: '未入力') ?> <?= h(time_slot_label($config, $data['procedure_time_' . $i])) ?></td>
              </tr>
            <?php endfor; ?>
          </tbody></table>
        </div>

        <div class="confirm-section">
          <div class="review-head">
            <h2>お客様情報</h2>
            <button class="btn ghost mini-btn" type="button" onclick="history.back()">修正する</button>
          </div>
          <table class="confirm-table"><tbody>
            <?php confirm_rows([
                ['氏名', $data['name']],
                ['フリガナ', $data['kana']],
                ['生年月日', date_label($data['birth']) . ($age === null ? '' : '（' . $age . '歳）')],
                ['性別', $data['gender'] ?: '未入力'],
                ['電話番号種別', ($data['phone_type'] ?? '') === 'mobile' ? '携帯TEL' : (($data['phone_type'] ?? '') === 'home' ? '自宅TEL' : '未入力')],
                ['電話番号', $data['phone']],
                ['メールアドレス', $data['email']],
                ['郵便番号', $data['postal_code'] ? '〒' . $data['postal_code'] : '未入力'],
                ['住所', $data['address']],
                ['緊急連絡先', $data['emergency_name'] . '（' . $data['emergency_relationship'] . '） ' . $data['emergency_phone']],
                ['保護者氏名', $data['guardian_name'] ?: '該当なし'],
            ]); ?>
          </tbody></table>
        </div>

        <div class="confirm-section">
          <div class="review-head">
            <h2>健康確認・規約同意</h2>
            <button class="btn ghost mini-btn" type="button" onclick="history.back()">修正する</button>
          </div>
          <table class="confirm-table"><tbody>
            <tr>
              <th>健康確認</th>
              <td>
                <ul>
                  <?php foreach ($healthLabels as $label): ?>
                    <li><?= h($label) ?></li>
                  <?php endforeach; ?>
                </ul>
              </td>
            </tr>
            <tr><th>補足</th><td><?= nl2br(h($data['medical_memo'] ?: 'なし')) ?></td></tr>
            <tr><th>規約同意</th><td>同意済み</td></tr>
          </tbody></table>
        </div>

        <div class="confirm-section">
          <div class="review-head">
            <h2>顔写真・連絡事項</h2>
            <button class="btn ghost mini-btn" type="button" onclick="history.back()">修正する</button>
          </div>
          <?php if ($photoRelativePath): ?>
            <img class="photo-confirm" src="<?= h($photoRelativePath) ?>" alt="顔写真プレビュー">
          <?php endif; ?>
          <table class="confirm-table"><tbody>
            <?php confirm_rows([
                ['顔写真', $photoRelativePath ? '登録済み' : '未登録'],
                ['連絡事項', $data['remarks'] ?: 'なし'],
            ]); ?>
          </tbody></table>
        </div>

        <div class="confirm-actions">
          <button class="btn ghost" type="button" onclick="history.back()">戻って修正する</button>
          <button class="btn" type="submit">この内容で送信する</button>
        </div>
      </fieldset>
    </form>
  </section>
</main>

</body>
</html>
