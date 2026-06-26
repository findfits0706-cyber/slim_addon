<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/trial.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$trialCssVersion = (string)filemtime(__DIR__ . '/../assets/css/trial.css');
$trialMobileCssVersion = (string)filemtime(__DIR__ . '/../assets/css/trial-mobile.css');
$trialInfoCssVersion = (string)filemtime(__DIR__ . '/../assets/css/trial-info.css');
$trialJsVersion = (string)filemtime(__DIR__ . '/../assets/js/trial.js');
$csrfToken = csrf_token();
$dbStatus = db_health_check();
$slots = [];
$loadError = '';
$emptyStateMessage = '';
$dateOptions = [];
$calendarMonths = [];
$initialDate = '';
$initialGenre = 'pilates';
$today = new DateTimeImmutable('today');
$bookingWindowEnd = $today->modify('first day of next month')->modify('last day of this month');
$bookingWindowDays = max(1, (int)$today->diff($bookingWindowEnd)->format('%a'));

if ($dbStatus['ok']) {
    try {
        $slots = list_slot_instances('pilates', $bookingWindowDays);
        if ($slots === []) {
            $emptyStateMessage = '現在、受付中のマシンピラティス体験枠はありません。公開設定をご確認ください。';
        } else {
            $dateGenres = [];
            foreach ($slots as $slot) {
                $date = (string)$slot['booking_date'];
                $genre = (string)$slot['genre'];

                if (!isset($dateOptions[$date])) {
                    $dateOptions[$date] = [
                        'date' => $date,
                        'label' => (string)$slot['booking_date_label'],
                        'genres' => [],
                    ];
                }

                $dateOptions[$date]['genres'][$genre] = true;
                $dateGenres[$date][$genre] = true;
            }

            $dateOptions = array_values($dateOptions);
            $initialDate = (string)$dateOptions[0]['date'];
            $availableGenres = array_keys($dateGenres[$initialDate] ?? []);
            if ($availableGenres !== [] && !in_array('pilates', $availableGenres, true)) {
                $initialGenre = (string)$availableGenres[0];
            }

            $datesByMonth = [];
            foreach ($dateOptions as $dateOption) {
                $date = (string)$dateOption['date'];
                $monthKey = substr($date, 0, 7);
                $datesByMonth[$monthKey][$date] = $dateOption;
            }

            $monthCursor = $today->modify('first day of this month');
            $lastCalendarMonth = $today->modify('first day of next month');

            while ($monthCursor <= $lastCalendarMonth) {
                $monthKey = $monthCursor->format('Y-m');
                $monthDates = $datesByMonth[$monthKey] ?? [];
                $monthStart = new DateTimeImmutable($monthKey . '-01');
                $monthEnd = $monthStart->modify('last day of this month');
                $startWeekday = (int)$monthStart->format('w');
                $daysInMonth = (int)$monthEnd->format('j');
                $weeks = [];
                $week = array_fill(0, 7, null);

                for ($i = 0; $i < $startWeekday; $i++) {
                    $week[$i] = ['type' => 'empty'];
                }

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), $day)->format('Y-m-d');
                    $weekday = (int)(new DateTimeImmutable($date))->format('w');
                    $option = $monthDates[$date] ?? null;

                    $week[$weekday] = [
                        'type' => $option === null ? 'inactive' : 'active',
                        'day' => $day,
                        'date' => $date,
                        'option' => $option,
                    ];

                    if ($weekday === 6 || $day === $daysInMonth) {
                        for ($fill = $weekday + 1; $fill < 7; $fill++) {
                            $week[$fill] = ['type' => 'empty'];
                        }
                        $weeks[] = $week;
                        $week = array_fill(0, 7, null);
                    }
                }

                $calendarMonths[] = [
                    'label' => $monthStart->format('Y年n月'),
                    'weeks' => $weeks,
                ];

                $monthCursor = $monthCursor->modify('first day of next month');
            }
        }
    } catch (Throwable $e) {
        $loadError = APP_DEBUG
            ? $e->getMessage()
            : '体験枠の読み込みに失敗しました。時間をおいて再度お試しください。';
    }
} else {
    $loadError = $dbStatus['message'];
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>体験・見学のお申し込み | Find Pilates</title>
  <meta name="description" content="Find Pilatesの体験・見学申し込みページです。カレンダーから日程を選び、空き枠を確認してお申し込みください。">
  <link rel="stylesheet" href="<?= h(asset_url('/assets/css/trial.css') . '?v=' . $trialCssVersion) ?>">
  <link rel="stylesheet" href="<?= h(asset_url('/assets/css/trial-mobile.css') . '?v=' . $trialMobileCssVersion) ?>">
  <link rel="stylesheet" href="<?= h(asset_url('/assets/css/trial-info.css') . '?v=' . $trialInfoCssVersion) ?>">
  <style>
    .slider-track{display:block}.slider-panel{display:none;width:100%}.slider-panel.is-active{display:block}
    .calendar-browser{margin-top:18px}.calendar-nav{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.calendar-nav-title{margin:0;color:#2f2a28;font-size:18px;font-weight:700;letter-spacing:.04em}.calendar-nav-button{width:44px;height:44px;border:1px solid rgba(47,42,40,.14);border-radius:999px;background:#fff;color:#2f2a28;font-size:18px;font-weight:700;line-height:1;cursor:pointer;-webkit-appearance:none}.calendar-nav-button:disabled{opacity:.35;cursor:not-allowed}.calendar-window{overflow:hidden}.calendar-strip{display:block;white-space:nowrap;font-size:0;-webkit-transition:-webkit-transform .28s ease;transition:transform .28s ease}.calendar-month-slide{display:inline-block;width:100%;vertical-align:top;white-space:normal;font-size:16px}.calendar-month-slide:not(.is-calendar-active){pointer-events:none}
    .member-guide{margin-top:18px}.member-guide-toggle{width:100%;min-height:52px;padding:12px 18px;border:1px solid rgba(47,42,40,.14);border-radius:999px;background:#fff;color:#2f2a28;font-weight:700;text-align:center;cursor:pointer;-webkit-appearance:none}.member-guide-panel{margin-top:14px}.member-guide-action{margin:18px 0 0}.member-guide-button{display:inline-block;min-height:0;padding:12px 18px;border:1px solid rgba(47,42,40,.14);border-radius:999px;background:#fff;color:#2f2a28;font-weight:700;line-height:1.5;text-decoration:none;-webkit-appearance:none}
    @media(max-width:520px){.calendar-weekdays,.calendar-grid{display:flex;flex-wrap:wrap;gap:0;margin-right:-2px;margin-left:-2px}.calendar-weekdays span,.calendar-grid>*{flex:0 0 calc(14.285714% - 4px);width:calc(14.285714% - 4px);margin:2px}.calendar-cell,.calendar-date{height:40px;min-height:0;aspect-ratio:auto}.date-card{align-items:center;justify-content:center;padding:0;text-align:center}}
    @media(max-width:390px){.calendar-weekdays span,.calendar-grid>*{flex-basis:calc(14.285714% - 3px);width:calc(14.285714% - 3px);margin:1.5px}.calendar-cell,.calendar-date{height:36px}}
  </style>
</head>
<body>
  <main class="trial-page">
    <div class="trial-container">
      <header class="trial-header">
        <p class="trial-logo">FIND PILATES</p>
        <h1 class="trial-title">体験・見学のお申し込み</h1>
        <p class="trial-lead">ご希望の日付と時間を選び、お客様情報をご入力ください。</p>
        <div class="women-only-note" role="note" aria-label="女性専用スタジオのご案内">
          <span class="women-only-note__badge">女性専用スタジオ</span>
          <p class="women-only-note__text">体験・見学を含め、女性の方のみご利用いただけます。</p>
        </div>
        <a href="<?= h(base_path('/')) ?>" class="back-link">ホームページに戻る</a>
      </header>

      <?php if ($loadError !== ''): ?>
        <section class="trial-card">
          <p class="error-box"><?= h($loadError) ?></p>
        </section>
      <?php endif; ?>

      <?php if ($emptyStateMessage !== ''): ?>
        <section class="trial-card">
          <p class="info-box"><?= h($emptyStateMessage) ?></p>
        </section>
      <?php endif; ?>

      <form class="trial-form" action="<?= h(base_path('/trial/submit.php')) ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

        <section class="trial-card slider-shell" data-step-shell>
          <ol class="slider-progress" aria-label="申し込みステップ">
            <li class="is-active" data-progress-step="1"><span>01</span><strong>日時選択</strong></li>
            <li data-progress-step="2"><span>02</span><strong>情報入力</strong></li>
            <li data-progress-step="3"><span>03</span><strong>確認・送信</strong></li>
          </ol>

          <div class="slider-viewport">
            <div class="slider-track" data-slider-track>
              <section class="slider-panel is-active" data-step-panel="1" aria-labelledby="date-title">
                <div class="section-head">
                  <p class="section-label">STEP 01</p>
                  <h2 id="date-title" class="section-title">日付と時間を選択してください</h2>
                  <p class="section-text">受付中の日程から、ご希望の内容と時間をお選びください。</p>
                </div>

                <div class="calendar-browser" id="dateList" aria-label="受付中の日付一覧">
                  <div class="calendar-nav" aria-label="月の切り替え">
                    <button type="button" class="calendar-nav-button" data-calendar-prev aria-label="前の月へ">◀</button>
                    <p class="calendar-nav-title" id="calendarCurrentMonth"><?= h((string)($calendarMonths[0]['label'] ?? '')) ?></p>
                    <button type="button" class="calendar-nav-button" data-calendar-next aria-label="次の月へ">▶</button>
                  </div>

                  <div class="calendar-window">
                    <div class="calendar-stack calendar-strip" data-calendar-strip>
                  <?php foreach ($calendarMonths as $monthIndex => $calendarMonth): ?>
                    <section class="calendar-month calendar-month-slide<?= $monthIndex === 0 ? ' is-calendar-active' : '' ?>" data-calendar-month data-month-label="<?= h($calendarMonth['label']) ?>" aria-hidden="<?= $monthIndex === 0 ? 'false' : 'true' ?>">
                      <div class="calendar-month-head">
                        <h3 class="calendar-month-title"><?= h($calendarMonth['label']) ?></h3>
                      </div>
                      <div class="calendar-weekdays" aria-hidden="true">
                        <span>日</span><span>月</span><span>火</span><span>水</span><span>木</span><span>金</span><span>土</span>
                      </div>
                      <div class="calendar-grid">
                        <?php foreach ($calendarMonth['weeks'] as $week): ?>
                          <?php foreach ($week as $cell): ?>
                            <?php if (($cell['type'] ?? '') === 'active'): ?>
                              <?php
                              $dateOption = $cell['option'];
                              $date = (string)$dateOption['date'];
                              $genres = array_keys($dateOption['genres']);
                              $isActive = $date === $initialDate;
                              ?>
                              <button
                                type="button"
                                class="date-card calendar-date<?= $isActive ? ' is-active' : '' ?>"
                                data-date-card="<?= h($date) ?>"
                                data-genres="<?= h(implode(',', $genres)) ?>"
                                aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
                              >
                                <span class="calendar-date-day"><?= h((string)$cell['day']) ?></span>
                              </button>
                            <?php elseif (($cell['type'] ?? '') === 'inactive'): ?>
                              <div class="calendar-cell calendar-cell-inactive" aria-hidden="true">
                                <span class="calendar-date-day"><?= h((string)$cell['day']) ?></span>
                              </div>
                            <?php else: ?>
                              <div class="calendar-cell calendar-cell-empty" aria-hidden="true"></div>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        <?php endforeach; ?>
                      </div>
                    </section>
                  <?php endforeach; ?>
                    </div>
                  </div>
                </div>

                <div class="slot-panel">
                  <div class="section-head section-head-compact">
                    <p class="section-label">SELECT</p>
                    <h3 class="section-title section-title-small">選択した日付で予約できる内容</h3>
                    <p class="section-text" id="selectedDateText">ご希望の日付を選択してください。</p>
                  </div>

                  <div class="menu-grid menu-grid-compact" id="genreList">
                    <?php foreach (['pilates' => genre_label('pilates')] as $value => $label): ?>
                      <label class="menu-card menu-card-compact<?= $value === $initialGenre ? ' is-active' : '' ?>" data-genre-card="<?= h($value) ?>">
                        <input type="radio" name="genre" value="<?= h($value) ?>"<?= $value === $initialGenre ? ' checked' : '' ?>>
                        <span class="menu-badge"><?= h($label) ?></span>
                        <h3 class="menu-title"><?= h($label) ?></h3>
                      </label>
                    <?php endforeach; ?>
                  </div>

                  <div class="member-guide">
                    <button type="button" class="member-guide-toggle" data-member-guide-toggle aria-expanded="false" aria-controls="memberGuidePanel">既存クラブメンバーの方</button>
                    <div class="notice-box member-guide-panel" id="memberGuidePanel" data-member-guide-panel hidden>
                      <p class="section-text">ファインドスポーツクラブメンバーの方は、マイページまたは総合フロントより直接お申込みください。</p>
                      <p class="member-guide-action">
                        <a class="member-guide-button" href="https://www.isslim.jp/slim/web/d/index.php/?c=Y5UZ6PqMsA&amp;f=00001" target="_blank" rel="noopener">マイページはこちら</a>
                      </p>
                      <p class="section-text">※レッスン予約画面より<br>カテゴリ「その他のオプション」からお選びいただけます。</p>
                    </div>
                  </div>

                  <div class="section-head section-head-compact">
                    <h3 class="section-title section-title-small">受付中の時間</h3>
                    <p class="section-text">ご希望の枠をひとつ選択してください。</p>
                  </div>

                  <div class="slot-list" id="slotList">
                    <?php foreach ($slots as $slot): ?>
                      <label class="slot-card<?= $slot['full'] ? ' is-full' : '' ?>" data-genre="<?= h($slot['genre']) ?>" data-date="<?= h($slot['booking_date']) ?>">
                        <input type="radio" name="slot_id" value="<?= h($slot['slot_id']) ?>" <?= $slot['full'] ? 'disabled' : 'required' ?>>
                        <span class="slot-main">
                          <span class="slot-date"><?= h($slot['booking_time_label']) ?></span>
                          <span class="slot-meta"><?= h($slot['lesson_name']) ?> / 講師 <?= h($slot['instructor_name']) ?></span>
                        </span>
                        <span class="slot-status">残り枠：<?= $slot['full'] ? '×' : ((int)$slot['remaining'] <= 2 ? '△' : '○') ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>

                  <div class="empty-message" id="emptyMessage">選択中の日付・内容で受付中の時間はありません。</div>
                </div>

                <div class="slider-actions">
                  <div class="step-summary-text" id="stepOneSummary">日時を選択すると次へ進めます。</div>
                  <button type="button" class="slider-button slider-button-primary" id="stepOneNext" disabled>情報入力へ</button>
                </div>
              </section>

              <section class="slider-panel" data-step-panel="2" aria-labelledby="customer-title">
                <div class="section-head">
                  <p class="section-label">STEP 02</p>
                  <h2 id="customer-title" class="section-title">お客様情報を入力してください</h2>
                  <p class="section-text">ご連絡に必要な情報をご入力ください。</p>
                </div>

                <div class="selected-summary-card" id="selectedSummaryCard">
                  <p class="selected-summary-label">選択中の予約内容</p>
                  <p class="selected-summary-main" id="selectedSummaryDate">未選択</p>
                  <p class="selected-summary-sub" id="selectedSummaryMeta">日時を選択してください</p>
                </div>

                <div class="form-grid">
                  <div class="form-field"><label class="form-label" for="customer_name">お名前<span class="required">必須</span></label><input class="form-input" type="text" id="customer_name" name="customer_name" autocomplete="name" required></div>
                  <div class="form-field"><label class="form-label" for="customer_kana">フリガナ<span class="required">必須</span></label><input class="form-input" type="text" id="customer_kana" name="customer_kana" required></div>
                  <div class="form-field"><label class="form-label" for="phone">電話番号<span class="required">必須</span></label><input class="form-input" type="tel" id="phone" name="phone" autocomplete="tel" required></div>
                  <div class="form-field"><label class="form-label" for="email">メールアドレス<span class="required">必須</span></label><input class="form-input" type="email" id="email" name="email" autocomplete="email" required></div>
                  <div class="form-field"><label class="form-label" for="age">年齢<span class="optional">任意</span></label><input class="form-input" type="number" id="age" name="age" min="0" max="120" inputmode="numeric"></div>
                  <div class="form-field"><label class="form-label" for="contact_method">ご連絡方法<span class="required">必須</span></label><select class="form-select" id="contact_method" name="contact_method" required><option value="">選択してください</option><option value="either">電話・メールどちらでも可</option><option value="phone">電話希望</option><option value="email">メール希望</option></select></div>
                  <div class="form-field"><label class="form-label" for="experience">運動経験<span class="optional">任意</span></label><select class="form-select" id="experience" name="experience"><option value="">選択してください</option><option value="初めて">運動はほとんど初めて</option><option value="少しある">少し経験がある</option><option value="継続中">現在も継続している</option></select></div>
                  <div class="form-field"><label class="form-label" for="trial_history" id="trialHistoryLabel">体験歴<span class="required">必須</span></label><select class="form-select" id="trial_history" name="trial_history" required><option value="">選択してください</option><option value="初めて">体験は初めて</option><option value="過去に体験を利用したことがある">過去に体験を利用したことがある</option></select></div>
                  <div class="form-field is-full"><label class="form-label" for="concern">お悩み・相談したいこと<span class="optional">任意</span></label><textarea class="form-textarea" id="concern" name="concern" placeholder="気になる部位、運動経験、来店時に確認したいことなど"></textarea></div>
                </div>

                <div class="slider-actions">
                  <button type="button" class="slider-button slider-button-secondary" data-step-back="1">戻る</button>
                  <button type="button" class="slider-button slider-button-primary" id="stepTwoNext" disabled>確認へ進む</button>
                </div>
              </section>

              <section class="slider-panel" data-step-panel="3" aria-labelledby="notice-title">
                <div class="section-head">
                  <p class="section-label">STEP 03</p>
                  <h2 id="notice-title" class="section-title">確認して申し込む</h2>
                  <p class="section-text">安全確認と注意事項をご確認のうえ、お申し込みください。</p>
                </div>

                <div class="selected-summary-card">
                  <p class="selected-summary-label">選択中の予約内容</p>
                  <p class="selected-summary-main" id="confirmSummaryDate">未選択</p>
                  <p class="selected-summary-sub" id="confirmSummaryMeta">日時を選択してください</p>
                </div>

                <div class="notice-box">
                  <p class="notice-title">既往歴・入会資格の確認</p>
                  <p class="section-text">安全確認のため、該当しない項目にチェックしてください。該当する内容がある場合は、受付で確認のうえ判断します。</p>
                  <div class="form-grid">
                    <label class="form-field is-full medical-check-item"><span class="form-label"><input class="medical-check-input" type="checkbox" name="medical_history_checks[]" value="exercise_restriction" required> 医師による運動制限、または運動に支障のある既往症はありません。</span></label>
                    <label class="form-field is-full medical-check-item"><span class="form-label"><input class="medical-check-input" type="checkbox" name="medical_history_checks[]" value="infectious_disease" required> 皮膚病・伝染性疾患、その他人へ感染する恐れのある疾病はありません。</span></label>
                    <label class="form-field is-full medical-check-item"><span class="form-label"><input class="medical-check-input" type="checkbox" name="medical_history_checks[]" value="fainting_risk" required> 現在、てんかん、心疾患、その他、運動中に発作、失神、意識消失または急激な体調悪化を生じるおそれのある疾患・症状はありません。</span></label>
                    <label class="form-field is-full medical-check-item"><span class="form-label"><input class="medical-check-input" type="checkbox" name="medical_history_checks[]" value="not_pregnant" required> 妊娠中ではありません。</span></label>
                    <label class="form-field is-full medical-check-item"><span class="form-label"><input class="medical-check-input" type="checkbox" name="medical_history_checks[]" value="membership_eligibility" required> 刺青・タトゥー、反社会的勢力との関係、その他入会資格に抵触する事項はありません。</span></label>
                    <label class="form-field is-full medical-check-item"><span class="form-label"><input class="medical-check-input" type="checkbox" name="medical_history_checks[]" value="follow_facility_rules" required> 施設利用中はマナー・モラル・スタッフの案内を遵守し、自己の体調管理に責任を持って利用します。</span></label>
                    <div class="form-field is-full"><label class="form-label" for="medical_history_note">既往歴・服薬・不安な点がある場合の補足<span class="optional">任意</span></label><textarea class="form-textarea" id="medical_history_note" name="medical_history_note" placeholder="例：腰痛で通院歴あり / 現在は医師から軽運動可と言われている、など"></textarea></div>
                  </div>
                </div>

                <div class="notice-box">
                  <p class="notice-title">お申し込み前にご確認ください</p>
                  <ul class="notice-list">
                    <li>体験・見学の内容は、選択した日付で受付中のものだけが表示されます。</li>
                    <li>体験の各メニューは、お一人様1回までです。</li>
                    <li>確認のご連絡後に日程が確定します。満席などでご希望に添えない場合があります。</li>
                    <li>キャンセルや変更をご希望の場合は、できるだけ早めにご連絡ください。</li>
                    <li>ご入力いただいた個人情報は、体験申込対応のためにのみ利用します。</li>
                  </ul>
                  <label class="agree-field"><input type="checkbox" name="agree" value="1" required><span>上記内容を確認し、同意します。</span></label>
                </div>

                <div class="slider-actions">
                  <button type="button" class="slider-button slider-button-secondary" data-step-back="2">戻る</button>
                  <button type="submit" class="slider-button slider-button-primary">この内容で申し込む</button>
                </div>
              </section>
            </div>
          </div>
        </section>
      </form>
    </div>
  </main>
  <script src="<?= h(asset_url('/assets/js/trial.js') . '?v=' . $trialJsVersion) ?>"></script>
</body>
</html>
