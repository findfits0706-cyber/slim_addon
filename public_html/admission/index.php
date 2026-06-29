<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

$token = csrf_token();
$today = date('Y-m-d');
$selectableStartDate = (string)($config['date_selection']['min_date'] ?? $today);
$mainCategories = $config['main_club_categories'];
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= h($config['site']['name']) ?> 入会受付フォーム</title>
  <link rel="stylesheet" href="./css/style.css?v=20260624-mobile-summary-scroll">
</head>
<body>

<header class="hero">
  <div class="wrap">
    <div class="brandline"><span class="mark">F</span><?= h($config['site']['name']) ?></div>
    <h1>入会受付フォーム</h1>
    <p class="lead">
      現在の料金と利用方式に合わせて、プラン選択から店頭手続き希望日、健康確認、顔写真までを段階的に入力できます。
      送信後、店頭で本人確認と口座登録を行います。
    </p>
  </div>
</header>

<main class="wrap layout">
  <section class="panel">
    <div class="progress" aria-live="polite">
      <div class="progress-head">
        <span id="stepText">STEP 1 / 6　利用形態・プラン選択</span>
        <span id="progressPct">1 / 6</span>
      </div>
      <div class="bar"><span id="bar"></span></div>
    </div>

    <form id="joinForm" action="./confirm.php" method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
      <input type="hidden" id="photoData" name="photo_data" value="">

      <div class="form-shell">
        <div class="form-main">
          <div class="alert danger" id="formError" role="alert" aria-live="assertive"></div>

          <fieldset class="form-step is-active" data-step-title="利用形態・プラン選択">
            <legend>利用形態・プラン選択</legend>
            <p class="hint">Find Pilatesだけで始めるか、本館会員と併用するかを選択してください。</p>

            <div class="toggle" role="radiogroup" aria-label="利用形態">
              <label>
                <input type="radio" name="use_type" value="new" checked>
                <span>Find Pilates単体</span>
              </label>
              <label>
                <input type="radio" name="use_type" value="add">
                <span>本館併用</span>
              </label>
            </div>

            <div id="coursePanel" class="course-panel">
              <div class="form-section-title">Find Pilates単体プラン</div>
              <div class="cards" role="radiogroup" aria-label="Find Pilates単体プラン">
                <?php foreach (['basic', 'double'] as $key): ?>
                  <?php $course = $config['pilates_courses'][$key]; ?>
                  <label class="choice">
                    <input
                      type="radio"
                      name="course"
                      value="<?= h($key) ?>"
                      data-fee="<?= h((string)$course['monthly_fee']) ?>"
                      data-visits="<?= h((string)$course['monthly_visits']) ?>"
                      data-label="<?= h($course['label']) ?>"
                      data-description="<?= h($course['description']) ?>"
                      <?= $key === 'basic' ? 'checked' : '' ?>
                    >
                    <strong><?= h($course['label']) ?></strong>
                    <span><?= h($course['description']) ?></span>
                    <b><?= h(yen((int)$course['monthly_fee'])) ?><small>（税込）</small></b>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div id="mainClubPanel" class="conditional">
              <div class="form-section-title">現在、本館をご利用中ですか？</div>
              <div class="cards compact status-cards" role="radiogroup" aria-label="現在、本館をご利用中ですか？">
                <label class="choice">
                  <input type="radio" name="main_member_status" value="existing" checked>
                  <strong>はい</strong>
                </label>
                <label class="choice">
                  <input type="radio" name="main_member_status" value="simultaneous">
                  <strong>いいえ</strong>
                </label>
              </div>

              <div id="memberNumberPanel" class="mini-field">
                <label for="mainMemberNumber">本館会員番号（任意）</label>
                <input id="mainMemberNumber" name="main_member_number" autocomplete="off">
              </div>

              <div class="form-section-title">本館会員種別</div>
              <?php foreach ($mainCategories as $categoryKey => $categoryLabel): ?>
                <fieldset class="card-group">
                  <legend><?= h($categoryLabel) ?></legend>
                  <div class="cards compact" role="radiogroup" aria-label="<?= h($categoryLabel) ?>">
                    <?php foreach ($config['main_club_memberships'] as $key => $membership): ?>
                      <?php if (($membership['category'] ?? '') !== $categoryKey) continue; ?>
                      <label class="choice">
                        <input
                          type="radio"
                          name="main_membership"
                          value="<?= h($key) ?>"
                          data-base="<?= h((string)$membership['monthly_fee']) ?>"
                          data-label="<?= h($membership['label']) ?>"
                          data-description="<?= h($membership['description']) ?>"
                        >
                        <strong><?= h($membership['label']) ?></strong>
                        <span><?= h($membership['description']) ?></span>
                        <b><?= h(yen((int)$membership['monthly_fee'])) ?><small>（税込）</small></b>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>
              <?php endforeach; ?>

              <div class="form-section-title">Find Pilates種別</div>
              <div class="cards compact" role="radiogroup" aria-label="Find Pilates種別">
                <?php foreach (['basic', 'double'] as $key): ?>
                  <?php $addon = $config['pilates_addons'][$key]; ?>
                  <label class="choice">
                    <input
                      type="radio"
                      name="addon"
                      value="<?= h($key) ?>"
                      data-fee="<?= h((string)$addon['add_fee']) ?>"
                      data-visits="<?= h((string)$addon['monthly_visits']) ?>"
                      data-label="<?= h($addon['label']) ?>"
                      data-description="<?= h($addon['description']) ?>"
                      <?= $key === 'basic' ? 'checked' : '' ?>
                    >
                    <strong><?= h($addon['label']) ?></strong>
                    <span><?= h($addon['description']) ?></span>
                    <b><?= h(yen((int)$addon['add_fee'])) ?><small>（税込）</small></b>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="menu-note">
              <strong>利用できる4つのメニュー</strong>
              <ul>
                <?php foreach ($config['menu_options'] as $menu): ?>
                  <li><?= h($menu) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </fieldset>

          <fieldset class="form-step" data-step-title="利用開始日・来店希望日">
            <legend>利用開始日・来店希望日</legend>
            <p class="hint">利用開始希望日と店頭手続き希望日を入力してください。</p>

            <div class="grid">
              <div>
                <label class="req" for="startDate">利用開始希望日</label>
                <input id="startDate" name="start_date" type="date" min="<?= h($selectableStartDate) ?>" value="<?= h($selectableStartDate) ?>" required>
              </div>
              <div>
                <label class="req" for="initialVisits">初月利用回数</label>
                <select id="initialVisits" name="initial_visits" required>
                  <?php foreach ($config['initial_visit_options'][8] as $visits): ?>
                    <option value="<?= h((string)$visits) ?>"><?= h((string)$visits) ?>回</option>
                  <?php endforeach; ?>
                </select>
                <p class="field-note">利用開始日に応じた推奨回数を初期表示します。必要に応じて変更できます。</p>
              </div>
              <div>
                <label for="campaignCode">紹介・キャンペーンコード（任意）</label>
                <input id="campaignCode" name="campaign_code" autocomplete="off">
              </div>
            </div>

            <div class="procedure-card">
              <h3>店頭手続きについて</h3>
              <p>Web申込後、店頭で本人確認・月会費の口座登録・予約方法や利用案内を行います。</p>
              <div class="procedure-grid">
                <div class="procedure-mini">
                  <strong>店頭で行うこと</strong>
                  <ul>
                    <?php foreach ($config['procedure_info']['things_to_do'] as $item): ?>
                      <li><?= h($item) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <div class="procedure-mini">
                  <strong>持ち物</strong>
                  <ul>
                    <?php foreach ($config['procedure_info']['things_to_bring'] as $item): ?>
                      <li><?= h($item) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            </div>

            <div class="preference-list">
              <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="preference-row">
                  <div class="preference-title">第<?= h((string)$i) ?>希望</div>
                  <div>
                    <label class="<?= $i === 1 ? 'req' : '' ?>" for="procedureDate<?= h((string)$i) ?>">日付</label>
                    <input
                      id="procedureDate<?= h((string)$i) ?>"
                      name="procedure_date_<?= h((string)$i) ?>"
                      type="date"
                      min="<?= h($selectableStartDate) ?>"
                      <?= $i === 1 ? 'required' : '' ?>
                    >
                    <p class="field-note" id="procedureWeekday<?= h((string)$i) ?>">曜日を表示します</p>
                  </div>
                  <div>
                    <label for="procedureTime<?= h((string)$i) ?>">希望時間帯</label>
                    <select id="procedureTime<?= h((string)$i) ?>" name="procedure_time_<?= h((string)$i) ?>">
                      <?php foreach ($config['procedure_time_slots_weekday'] as $slotValue => $slotLabel): ?>
                        <option value="<?= h($slotValue) ?>"><?= h($slotLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              <?php endfor; ?>
            </div>
          </fieldset>

          <fieldset class="form-step" data-step-title="お客様情報">
            <legend>お客様情報</legend>
            <input id="name" name="name" type="hidden">
            <input id="kana" name="kana" type="hidden">
            <div class="grid">
              <div>
                <label class="req" for="surname">姓</label>
                <input id="surname" name="surname" autocomplete="family-name" placeholder="例：山田" maxlength="28" required>
              </div>
              <div>
                <label class="req" for="givenName">名</label>
                <input id="givenName" name="given_name" autocomplete="given-name" placeholder="例：花子" maxlength="28" required>
              </div>
              <div>
                <label class="req" for="surnameKana">セイ</label>
                <input id="surnameKana" name="surname_kana" inputmode="kana" placeholder="例：ヤマダ" maxlength="28" required>
                <p class="field-note">全角カタカナで入力してください。</p>
              </div>
              <div>
                <label class="req" for="givenNameKana">メイ</label>
                <input id="givenNameKana" name="given_name_kana" inputmode="kana" placeholder="例：ハナコ" maxlength="28" required>
                <p class="field-note">全角カタカナで入力してください。</p>
              </div>
            </div>

            <div class="grid-3">
              <div>
                <label class="req" for="birth">生年月日</label>
                <input id="birth" name="birth" type="date" max="<?= h($today) ?>" required>
                <p class="field-note" id="ageText">年齢を自動計算します。</p>
              </div>
              <div>
                <label class="req" for="gender">性別</label>
                <select id="gender" name="gender" required>
                  <option value="">選択してください</option>
                  <option value="女性">女性</option>
                  <option value="男性">男性</option>
                  <option value="その他">その他</option>
                </select>
              </div>
              <div>
                <label class="req" for="phoneType">電話番号種別</label>
                <select id="phoneType" name="phone_type" required>
                  <option value="">選択してください</option>
                  <option value="mobile">携帯TEL</option>
                  <option value="home">自宅TEL</option>
                </select>
              </div>
              <div>
                <label class="req" for="phone">電話番号</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="例：090-1234-5678" required>
              </div>
            </div>

            <div id="minorPanel" class="conditional">
              <div class="form-section-title">未成年の方の確認</div>
              <label class="req" for="guardianName">保護者氏名</label>
              <input id="guardianName" name="guardian_name" placeholder="例：山田 太郎">
            </div>

            <label class="check compact-check" id="schoolPanel">
              <input type="checkbox" name="school_confirmation" value="1">
              <span>15歳前後のため、中学生以下ではないことを確認しました。</span>
            </label>

            <div class="grid">
              <div>
                <label class="req" for="email">メールアドレス</label>
                <input id="email" name="email" type="email" autocomplete="email" placeholder="example@mail.com" required>
              </div>
              <div>
                <label for="postalCode">郵便番号</label>
                <div class="address-search">
                  <input id="postalCode" name="postal_code" inputmode="numeric" autocomplete="postal-code" placeholder="例：329-2754">
                  <button class="btn secondary" id="zipSearch" type="button">住所を検索</button>
                </div>
                <p class="field-note" id="zipStatus" aria-live="polite"></p>
              </div>
            </div>

            <div class="grid">
              <div>
                <label class="req" for="prefecture">都道府県</label>
                <select id="prefecture" name="prefecture" autocomplete="address-level1" required>
                  <option value="">選択してください</option>
                  <?php foreach ([
                      '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
                      '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
                      '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
                      '岐阜県', '静岡県', '愛知県', '三重県',
                      '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
                      '鳥取県', '島根県', '岡山県', '広島県', '山口県',
                      '徳島県', '香川県', '愛媛県', '高知県',
                      '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
                  ] as $prefecture): ?>
                    <option value="<?= h($prefecture) ?>"><?= h($prefecture) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="req" for="cityArea">市区町村・町域</label>
                <input id="cityArea" name="city_area" autocomplete="address-level2" maxlength="30" required>
              </div>
            </div>

            <div class="grid">
              <div>
                <label class="req" for="streetAddress">番地</label>
                <input id="streetAddress" name="street_address" autocomplete="street-address" maxlength="30" required>
              </div>
              <div>
                <label for="building">建物名・部屋番号（任意）</label>
                <input id="building" name="building" maxlength="30">
              </div>
            </div>

            <div class="grid-3">
              <div>
                <label class="req" for="emergencyName">緊急連絡先 氏名</label>
                <input id="emergencyName" name="emergency_name" required>
              </div>
              <div>
                <label class="req" for="emergencyRelationship">続柄</label>
                <input id="emergencyRelationship" name="emergency_relationship" placeholder="例：母" required>
              </div>
              <div>
                <label class="req" for="emergencyPhone">緊急連絡先 電話番号</label>
                <input id="emergencyPhone" name="emergency_phone" type="tel" inputmode="tel" required>
              </div>
            </div>
          </fieldset>

          <fieldset class="form-step" data-step-title="健康状態・入会資格">
            <legend>健康状態・入会資格</legend>
            <p class="hint">安全にご利用いただくため、各項目をご確認ください。</p>
            <div class="field-error group-error" id="healthGroupError" hidden></div>
            <div class="check-list">
              <?php foreach ($config['health_checks'] as $key => $item): ?>
                <label class="check">
                  <input type="checkbox" name="health_checks[]" value="<?= h($key) ?>" required>
                  <span>
                    <?= h($item['label']) ?>
                    <?php if (!empty($item['note'])): ?>
                      <em><?= h($item['note']) ?></em>
                    <?php endif; ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <div>
              <label for="medicalMemo">既往歴・服薬・不安な点がある場合の補足（任意）</label>
              <textarea id="medicalMemo" name="medical_memo"></textarea>
            </div>
          </fieldset>

          <fieldset class="form-step" data-step-title="規約・顔写真">
            <legend>規約・顔写真</legend>
            <p class="hint">規約を最後まで確認すると同意チェックが有効になります。</p>
            <div id="termsBox" class="terms-box" tabindex="0" aria-label="クラブ規約">
ファインドスポーツクラブおよびFind Pilates利用規約

第1条（適用範囲）
本規約は、Find Pilatesおよびファインドスポーツクラブが提供する施設・サービスの利用に適用されます。

第2条（会員制度）
入会希望者は、本規約および各施設の案内事項を確認し、所定の手続きを完了することで利用できます。

第3条（入会資格）
15歳未満および中学生以下の方、医師等から運動を禁じられている方、その他クラブが会員として適切でないと判断した方は入会できません。

第4条（利用上の注意）
会員はスタッフの案内を守り、体調管理に責任を持って利用します。体調不良や不安がある場合は利用を控え、必要に応じて医師へ相談してください。

第5条（会費）
会費、入会費、追加料金その他費用はクラブが定めるものとします。正式な請求金額は店頭手続き時に確認します。

第6条（個人情報）
申込情報は入会受付、本人確認、連絡、会員管理のために利用します。

附則　本規約は2026年6月1日より適用します。
            </div>
            <div class="terms-meter" id="termsMeter">規約スクロール：0%</div>
            <label class="check locked" id="termsAgreeLabel">
              <input id="termsAgree" type="checkbox" name="terms_agree" value="1" required disabled>
              <span>クラブ規約を最後まで確認し、内容に同意します。</span>
            </label>

            <div class="photo-box">
              <div>
                <label class="req">顔写真撮影</label>
                <video id="video" autoplay playsinline muted></video>
                <canvas id="canvas" hidden></canvas>
                <div class="btns">
                  <button class="btn secondary" type="button" id="cameraStart">カメラを起動</button>
                  <button class="btn" type="button" id="capture">撮影する</button>
                </div>
              </div>
              <div>
                <label class="req" for="photoUpload">画像アップロード</label>
                <div class="photo-preview" id="photoPreview">写真プレビュー</div>
                <input id="photoUpload" name="photo_file" type="file" accept="image/jpeg,image/png,image/webp">
                <p class="field-note">JPEG、PNG、WebP形式。最大5MB。</p>
              </div>
            </div>
          </fieldset>

          <fieldset class="form-step" data-step-title="入力内容確認">
            <legend>入力内容確認</legend>
            <p class="hint">内容を確認し、必要な場合は各ステップへ戻って修正してください。</p>
            <div id="clientReview" class="review-list" aria-live="polite"></div>
            <div>
              <label for="remarks">受付への連絡事項（任意）</label>
              <textarea id="remarks" name="remarks"></textarea>
            </div>
            <p class="fine">次の画面でサーバー側の再計算結果を確認してから送信します。</p>
          </fieldset>

          <div class="step-actions">
            <button class="btn ghost" type="button" id="prevStep" hidden>戻る</button>
            <button class="btn" type="button" id="nextStep">次へ</button>
            <button class="btn submit-btn" type="submit" id="submitButton" hidden>確認のうえで送信します</button>
          </div>
        </div>

        <aside class="side price-summary" aria-live="polite">
          <h2>選択内容と概算料金</h2>
          <dl id="priceSummary"></dl>
          <small><?= h($config['texts']['price_notice']) ?></small>
        </aside>
      </div>

      <div class="mobile-summary" id="mobileSummary">
        <button type="button" id="mobileSummaryToggle">
          <span id="mobileSummaryTitle">ベーシック会員</span>
          <strong id="mobileSummaryTotal"><?= h(yen((int)$config['fees']['join_fee'] + 8800)) ?></strong>
        </button>
        <div id="mobileSummaryBody"></div>
      </div>
    </form>
  </section>
</main>

<script>
  window.FIND_PILATES_CONFIG = <?= json_encode([
      'joinFee' => (int)$config['fees']['join_fee'],
      'processingFee' => (int)($config['fees']['processing_fee'] ?? 0),
      'today' => $today,
      'selectableStartDate' => $selectableStartDate,
      'pilatesCourses' => array_intersect_key($config['pilates_courses'], ['basic' => true, 'double' => true]),
      'mainClubMemberships' => $config['main_club_memberships'],
      'pilatesAddons' => array_intersect_key($config['pilates_addons'], ['basic' => true, 'double' => true]),
      'initialVisitOptions' => $config['initial_visit_options'],
      'campaigns' => load_campaign_settings($config),
      'weekdaySlots' => $config['procedure_time_slots_weekday'],
      'sundaySlots' => $config['procedure_time_slots_sunday'],
      'closedDates' => $config['closed_dates'],
      'closedDayRules' => $config['closed_day_rules'],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="./js/app.js?v=20260625-existing-no-join-fee"></script>
</body>
</html>
