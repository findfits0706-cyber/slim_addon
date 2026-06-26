<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/session.php';

ensure_session_started();

$completed = !empty($_SESSION['trial_booking_completed']);
$bookingSummary = $_SESSION['trial_booking_summary'] ?? null;
unset($_SESSION['trial_booking_completed']);
unset($_SESSION['trial_booking_summary']);
$trialInfoCssVersion = (string)filemtime(__DIR__ . '/../assets/css/trial-info.css');
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>お申し込みありがとうございました | Find Pilates</title>
  <link rel="stylesheet" href="<?= h(asset_url('/assets/css/trial.css')) ?>">
  <link rel="stylesheet" href="<?= h(asset_url('/assets/css/trial-info.css') . '?v=' . $trialInfoCssVersion) ?>">
</head>
<body>
  <main class="trial-page">
    <div class="trial-container">
      <section class="trial-card trial-thanks-card">
        <p class="section-label">THANK YOU</p>
        <h1 class="trial-title">お申し込みありがとうございました</h1>
        <p class="trial-lead" style="text-align:center;">
          <?= $completed ? '送信を受け付けました。内容確認後、担当よりご連絡いたします。' : '申込完了後に表示されるページです。' ?>
        </p>

        <?php if (is_array($bookingSummary)): ?>
          <div class="selected-summary-card trial-booking-summary">
            <p class="selected-summary-label">お申し込み内容</p>
            <p class="selected-summary-main"><?= h((string)$bookingSummary['booking_date_label']) ?> <?= h((string)$bookingSummary['booking_time_label']) ?></p>
            <p class="selected-summary-sub"><?= h((string)$bookingSummary['genre_label']) ?> / 講師 <?= h((string)$bookingSummary['instructor_name']) ?></p>
          </div>
        <?php endif; ?>

        <section class="trial-day-guide" aria-labelledby="trial-day-guide-title">
          <h2 class="trial-day-guide__title" id="trial-day-guide-title">体験当日のご案内</h2>
          <div class="trial-day-guide__grid">
            <div class="trial-day-guide__block">
              <h3 class="trial-day-guide__heading">ご来館時間</h3>
              <p class="trial-day-guide__text">ご予約時間の<span class="trial-day-guide__emphasis">10分前</span>を目安にお越しください。</p>
            </div>
            <div class="trial-day-guide__block">
              <h3 class="trial-day-guide__heading">服装</h3>
              <p class="trial-day-guide__text">伸縮性があり、<span class="trial-day-guide__emphasis">身体にほどよくフィットする服装</span>でご参加ください。</p>
              <p class="trial-day-guide__text">ゆったりしすぎる服、裾の広い服、ジーンズなどの伸縮性がない服装は、身体の動きを確認しにくく、マシンに引っかかる場合があるため、おすすめしておりません。</p>
              <p class="trial-day-guide__text">更衣スペースはありますが、数に限りがあります。可能な方は、あらかじめ運動できる服装でお越しください。</p>
            </div>
            <div class="trial-day-guide__block trial-day-guide__block--wide">
              <h3 class="trial-day-guide__heading">お持ち物</h3>
              <p class="trial-day-guide__text">必ずお持ちいただくもの：</p>
              <ul class="trial-day-guide__list">
                <li>グリップソックス（滑り止めの付いた靴下）</li>
                <li>スマートフォン</li>
              </ul>
              <p class="trial-day-guide__text">必要に応じてお持ちいただくもの：</p>
              <ul class="trial-day-guide__list">
                <li>タオル</li>
                <li>お飲み物</li>
              </ul>
              <p class="trial-day-guide__text">安全にマシンをご利用いただくため、<span class="trial-day-guide__emphasis">グリップソックス（滑り止めの付いた靴下）の着用が必要</span>です。お持ちでない場合は、店頭でもご購入いただけます。</p>
              <p class="trial-day-guide__text">室内シューズは必要ありません。</p>
              <p class="trial-day-guide__text">館内に給水設備はありませんので、お飲み物をご利用になる方は、あらかじめご用意ください。</p>
              <p class="trial-day-guide__text">本人確認書類は体験時には必要ありません。</p>
            </div>
          </div>
        </section>

        <p style="margin-top:32px;"><a class="trial-simple-button" href="<?= h(base_path('/')) ?>">ホームページに戻る</a></p>
      </section>
    </div>
  </main>
</body>
</html>
