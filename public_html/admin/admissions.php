<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/admin-ui.php';

require_admin();

$config = require __DIR__ . '/../admission/inc/config.php';
require_once __DIR__ . '/../admission/inc/functions.php';

$adminSelfUrl = base_path('/admin/admissions.php');
$adminCampaignsUrl = base_path('/admin/campaigns.php');
$admissionFormUrl = base_path('/admission/');

$statusOptions = $config['admin']['status_options'] ?? [];
$token = csrf_token();
$errors = [];

$records = load_admission_records($config);
$selectedId = (string)($_GET['id'] ?? '');
$isNewMode = ($_GET['action'] ?? '') === 'new';
$saveSuccess = isset($_GET['saved']) && $_GET['saved'] === '1';

$selectedRecord = $selectedId !== '' ? find_admission_record($records, $selectedId) : null;
if (!$isNewMode && $selectedRecord === null && !empty($records)) {
    $selectedRecord = $records[0];
    $selectedId = (string)($selectedRecord['id'] ?? '');
}

$formData = $selectedRecord['data'] ?? admission_blank_data();
$formStatus = (string)($selectedRecord['status'] ?? 'new');
$formAdminNote = (string)($selectedRecord['admin_note'] ?? '');
$formPhoto = $selectedRecord['photo'] ?? [];
$formCreatedAt = (string)($selectedRecord['created_at'] ?? '');
$formFees = $selectedRecord['fees'] ?? calculate_fees($config, $formData);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_string('admin_action') === 'save') {
    if (!verify_csrf_token(post_string('csrf_token'))) {
        http_response_code(400);
        exit('不正な送信です。ページを再読み込みして再度お試しください。');
    }

    $records = load_admission_records($config);
    $recordId = post_string('record_id');
    $existingRecord = $recordId !== '' ? find_admission_record($records, $recordId) : null;

    $formData = array_merge(admission_blank_data(), collect_form_data($_POST));
    $formStatus = post_string('status', 'new');
    $formAdminNote = post_string('admin_note');
    $formPhoto = $existingRecord['photo'] ?? [];
    $formCreatedAt = (string)($existingRecord['created_at'] ?? '');

    if (!isset($statusOptions[$formStatus])) {
        $formStatus = 'new';
    }

    $errors = validate_admin_record($config, $formData);
    $formFees = calculate_fees($config, $formData);

    if (empty($errors)) {
        $overrides = [
            'status' => $formStatus,
            'admin_note' => $formAdminNote,
            'mail_status' => $existingRecord['mail_status'] ?? [],
        ];

        if ($recordId !== '') {
            $overrides['id'] = $recordId;
        }
        if ($formCreatedAt !== '') {
            $overrides['created_at'] = $formCreatedAt;
        }
        if (isset($existingRecord['created_at_ts'])) {
            $overrides['created_at_ts'] = (int)$existingRecord['created_at_ts'];
        }

        $savedRecord = build_admission_record(
            $config,
            $formData,
            $formFees,
            is_array($formPhoto) ? $formPhoto : [],
            $overrides
        );

        upsert_admission_record($config, $savedRecord);

        header('Location: ' . $adminSelfUrl . '?id=' . rawurlencode((string)$savedRecord['id']) . '&saved=1');
        exit;
    }
}

$previewRecord = build_admission_record(
    $config,
    $formData,
    $formFees,
    is_array($formPhoto) ? $formPhoto : [],
    [
        'id' => $selectedRecord['id'] ?? '',
        'status' => $formStatus,
        'created_at' => $formCreatedAt !== '' ? $formCreatedAt : date('Y-m-d H:i:s'),
        'created_at_ts' => $selectedRecord['created_at_ts'] ?? time(),
        'admin_note' => $formAdminNote,
        'mail_status' => $selectedRecord['mail_status'] ?? [],
    ]
);

$recordCount = count($records);
$isErrorOpen = !empty($errors);
$selectedBirthWestern = date_label($formData['birth'] ?? '');
$selectedBirthWareki = wareki_date_label($formData['birth'] ?? '');
$selectedAge = calculate_age($formData['birth'] ?? '');

$addressFallback = (string)($formData['address'] ?? '');
$prefectureValue = (string)($formData['prefecture'] ?? '');
$cityAreaValue = (string)($formData['city_area'] ?? '');
$streetAddressValue = (string)($formData['street_address'] ?? '');
$buildingValue = (string)($formData['building'] ?? '');
if ($prefectureValue === '' && $cityAreaValue === '' && $streetAddressValue === '' && $addressFallback !== '') {
    $streetAddressValue = $addressFallback;
}

$monthlyVisitsForInitial = 8;
if (($formData['use_type'] ?? 'new') === 'add') {
    $selectedAddon = $config['pilates_addons'][$formData['addon'] ?? ''] ?? null;
    $monthlyVisitsForInitial = $selectedAddon ? (int)$selectedAddon['monthly_visits'] : 8;
} else {
    $selectedCourse = $config['pilates_courses'][$formData['course'] ?? ''] ?? null;
    $monthlyVisitsForInitial = $selectedCourse ? (int)$selectedCourse['monthly_visits'] : 8;
}
$initialVisitOptions = initial_visit_options($config, $monthlyVisitsForInitial);
$selectedInitialVisits = (string)($formData['initial_visits'] ?: max($initialVisitOptions));

render_admin_header('入会受付', 'admissions-admin-page');
?>
<section class="admin-card admissions-admin-page-head">
  <div>
    <h2>入会受付</h2>
    <p class="admin-note">Web入会申込の確認、ステータス更新、SLIM転記用コピー、手動登録を行います。</p>
    <p class="admissions-admin-count"><span data-admission-visible-count><?= h((string)$recordCount) ?></span> / <?= h((string)$recordCount) ?>件</p>
  </div>
  <div class="admissions-admin-actions">
    <a class="button-link" href="<?= h($adminSelfUrl) ?>?action=new">新規登録</a>
    <a class="button-link button-link--muted" href="<?= h($adminCampaignsUrl) ?>">キャンペーン設定</a>
    <a class="button-link button-link--muted" href="<?= h($admissionFormUrl) ?>" target="_blank" rel="noopener">申込フォームを開く</a>
  </div>
</section>

<?php if ($saveSuccess): ?>
  <div class="flash flash--success">保存しました。</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="error" role="alert">
    <strong>入力内容を確認してください。</strong>
    <ul class="error-list">
      <?php foreach ($errors as $error): ?>
        <li><?= h((string)$error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="admissions-admin-layout">
  <aside class="admin-card admissions-admin-list-panel" aria-label="申込一覧">
    <div class="admissions-admin-list-head">
      <h2>申込一覧</h2>
      <span class="admissions-admin-list-count" data-admission-visible-count><?= h((string)$recordCount) ?></span>
    </div>

    <div class="admissions-admin-filters">
      <label for="admissionSearch">検索</label>
      <input id="admissionSearch" type="search" data-admission-search placeholder="氏名・カナ・電話番号">
      <label for="admissionStatusFilter">ステータス</label>
      <select id="admissionStatusFilter" data-admission-status-filter>
        <option value="">すべて</option>
        <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
          <option value="<?= h((string)$statusKey) ?>"><?= h((string)$statusLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="admissions-admin-list" data-admission-list>
      <?php if (empty($records)): ?>
        <p class="admin-note">まだ保存された申込はありません。</p>
      <?php else: ?>
        <?php foreach ($records as $record): ?>
          <?php
          $recordData = is_array($record['data'] ?? null) ? $record['data'] : [];
          $recordFees = is_array($record['fees'] ?? null) ? $record['fees'] : [];
          $recordId = (string)($record['id'] ?? '');
          $status = (string)($record['status'] ?? 'new');
          $isActive = $recordId === (string)($previewRecord['id'] ?? '') && !$isNewMode;
          $searchText = trim(implode(' ', [
              $recordData['name'] ?? '',
              $recordData['kana'] ?? '',
              $recordData['phone'] ?? '',
          ]));
          ?>
          <a
            class="admissions-admin-list-item<?= $isActive ? ' is-active' : '' ?>"
            href="<?= h($adminSelfUrl) ?>?id=<?= h(rawurlencode($recordId)) ?>"
            data-admission-item
            data-status="<?= h($status) ?>"
            data-search="<?= h($searchText) ?>"
            <?= $isActive ? 'aria-current="page"' : '' ?>
          >
            <span class="admissions-admin-item-main">
              <strong><?= h((string)($recordData['name'] ?? '氏名未設定')) ?></strong>
              <span><?= h($statusOptions[$status] ?? $status) ?></span>
            </span>
            <span>受付日時：<?= h(datetime_label($record['created_at'] ?? '')) ?></span>
            <span>希望内容：<?= h((string)($recordFees['course_label'] ?? '未設定')) ?></span>
            <span>利用開始：<?= h(date_label($recordData['start_date'] ?? '')) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
      <p class="admissions-admin-empty" data-admission-empty hidden>条件に一致する申込はありません。</p>
    </div>
  </aside>

  <section class="admissions-admin-detail" id="admissionDetail" tabindex="-1">
    <form method="post" data-admission-form data-dirty-form>
      <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
      <input type="hidden" name="admin_action" value="save">
      <input type="hidden" name="record_id" value="<?= h((string)($selectedRecord['id'] ?? '')) ?>">

      <details class="admin-card admissions-admin-section" open>
        <summary>対応状況</summary>
        <div class="admissions-admin-grid">
          <div>
            <label for="status">対応ステータス</label>
            <select id="status" name="status">
              <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                <option value="<?= h((string)$statusKey) ?>" <?= $formStatus === $statusKey ? 'selected' : '' ?>><?= h((string)$statusLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="createdAtDisplay">受付日時</label>
            <input id="createdAtDisplay" value="<?= h(datetime_label($formCreatedAt)) ?>" readonly>
          </div>
        </div>
      </details>

      <details class="admin-card admissions-admin-section" open>
        <summary>SLIM転記用</summary>
        <div class="admissions-admin-copy-head">
          <button class="button-link button-link--muted" type="button" data-copy-target="slimCopyText">コピー</button>
          <button class="button-link button-link--muted" type="button" data-toggle-slim aria-controls="slimCopyText" aria-expanded="false">内容を表示</button>
          <span class="admissions-admin-copy-status" data-copy-status role="status"></span>
        </div>
        <textarea id="slimCopyText" class="admissions-admin-copy-box" readonly data-slim-copy-box><?= h(build_slim_copy_text($config, $previewRecord)) ?></textarea>
      </details>

      <details class="admin-card admissions-admin-section" open>
        <summary>本人情報</summary>
        <div class="admissions-admin-grid">
          <div>
            <label for="name">氏名</label>
            <input id="name" name="name" value="<?= h($formData['name'] ?? '') ?>" required>
          </div>
          <div>
            <label for="kana">カナ</label>
            <input id="kana" name="kana" value="<?= h($formData['kana'] ?? '') ?>" required>
          </div>
          <div>
            <label for="birth">生年月日</label>
            <input id="birth" name="birth" type="date" value="<?= h($formData['birth'] ?? '') ?>" required>
          </div>
          <div class="admissions-admin-birth-card" data-birth-card data-seireki="<?= h($selectedBirthWestern ?: '未入力') ?>" data-wareki="<?= h($selectedBirthWareki ?: '未入力') ?>" data-mode="seireki">
            <span>年齢：<?= $selectedAge === null ? '未入力' : h((string)$selectedAge) . '歳' ?></span>
            <strong class="admin-birth-value"><?= h($selectedBirthWestern ?: '未入力') ?></strong>
            <button class="button-link button-link--muted" type="button" data-birth-toggle>和暦表示へ切替</button>
          </div>
          <div>
            <label for="gender">性別</label>
            <select id="gender" name="gender">
              <option value="" <?= ($formData['gender'] ?? '') === '' ? 'selected' : '' ?>>選択しない</option>
              <option value="男性" <?= ($formData['gender'] ?? '') === '男性' ? 'selected' : '' ?>>男性</option>
              <option value="女性" <?= ($formData['gender'] ?? '') === '女性' ? 'selected' : '' ?>>女性</option>
              <option value="その他" <?= ($formData['gender'] ?? '') === 'その他' ? 'selected' : '' ?>>その他</option>
            </select>
          </div>
          <div>
            <label for="guardianName">保護者名</label>
            <input id="guardianName" name="guardian_name" value="<?= h($formData['guardian_name'] ?? '') ?>">
          </div>
        </div>
      </details>

      <details class="admin-card admissions-admin-section" <?= $isErrorOpen ? 'open' : '' ?>>
        <summary>連絡先</summary>
        <div class="admissions-admin-grid">
          <div>
            <label for="phone">電話番号</label>
            <input id="phone" name="phone" value="<?= h($formData['phone'] ?? '') ?>" required>
          </div>
          <div>
            <label for="email">メール</label>
            <input id="email" name="email" type="email" value="<?= h($formData['email'] ?? '') ?>">
          </div>
          <div>
            <label for="postalCode">郵便番号</label>
            <input id="postalCode" name="postal_code" value="<?= h($formData['postal_code'] ?? '') ?>">
          </div>
          <div>
            <label for="prefecture">都道府県</label>
            <input id="prefecture" name="prefecture" value="<?= h($prefectureValue) ?>">
          </div>
          <div>
            <label for="cityArea">市区町村</label>
            <input id="cityArea" name="city_area" value="<?= h($cityAreaValue) ?>">
          </div>
          <div>
            <label for="streetAddress">番地</label>
            <input id="streetAddress" name="street_address" value="<?= h($streetAddressValue) ?>">
          </div>
          <div>
            <label for="building">建物名</label>
            <input id="building" name="building" value="<?= h($buildingValue) ?>">
          </div>
          <input type="hidden" name="address" value="<?= h($addressFallback) ?>">
          <div>
            <label for="emergencyName">緊急連絡先 氏名</label>
            <input id="emergencyName" name="emergency_name" value="<?= h($formData['emergency_name'] ?? '') ?>">
          </div>
          <div>
            <label for="emergencyRelationship">続柄</label>
            <input id="emergencyRelationship" name="emergency_relationship" value="<?= h($formData['emergency_relationship'] ?? '') ?>">
          </div>
          <div>
            <label for="emergencyPhone">緊急連絡先 電話番号</label>
            <input id="emergencyPhone" name="emergency_phone" value="<?= h($formData['emergency_phone'] ?? '') ?>">
          </div>
        </div>
      </details>

      <details class="admin-card admissions-admin-section" <?= $isErrorOpen ? 'open' : '' ?>>
        <summary>利用形態・コース</summary>
        <div class="admissions-admin-grid">
          <div>
            <label for="useType">利用形態</label>
            <select id="useType" name="use_type">
              <option value="new" <?= ($formData['use_type'] ?? 'new') === 'new' ? 'selected' : '' ?>>ピラティス単体</option>
              <option value="add" <?= ($formData['use_type'] ?? '') === 'add' ? 'selected' : '' ?>>本館併用</option>
            </select>
          </div>
          <div>
            <label for="mainMemberStatus">現在、本館をご利用中ですか？</label>
            <select id="mainMemberStatus" name="main_member_status">
              <option value="existing" <?= ($formData['main_member_status'] ?? 'existing') === 'existing' ? 'selected' : '' ?>>はい</option>
              <option value="simultaneous" <?= ($formData['main_member_status'] ?? '') === 'simultaneous' ? 'selected' : '' ?>>いいえ</option>
            </select>
          </div>
          <div>
            <label for="course">ピラティス単体種別</label>
            <select id="course" name="course">
              <?php foreach ($config['pilates_courses'] as $courseKey => $course): ?>
                <option value="<?= h((string)$courseKey) ?>" <?= ($formData['course'] ?? '') === $courseKey ? 'selected' : '' ?>><?= h((string)$course['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="mainMembership">本館会員種別</label>
            <select id="mainMembership" name="main_membership">
              <option value="">選択してください</option>
              <?php foreach ($config['main_club_memberships'] as $membershipKey => $membership): ?>
                <option value="<?= h((string)$membershipKey) ?>" <?= ($formData['main_membership'] ?? '') === $membershipKey ? 'selected' : '' ?>><?= h((string)$membership['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="addon">Find Pilates種別</label>
            <select id="addon" name="addon">
              <?php foreach ($config['pilates_addons'] as $addonKey => $addon): ?>
                <option value="<?= h((string)$addonKey) ?>" <?= ($formData['addon'] ?? '') === $addonKey ? 'selected' : '' ?>><?= h((string)$addon['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="initialVisits">初月利用回数</label>
            <select id="initialVisits" name="initial_visits">
              <?php foreach ($initialVisitOptions as $visits): ?>
                <option value="<?= h((string)$visits) ?>" <?= $selectedInitialVisits === (string)$visits ? 'selected' : '' ?>><?= h((string)$visits) ?>回</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="mainMemberNumber">本館会員番号</label>
            <input id="mainMemberNumber" name="main_member_number" value="<?= h($formData['main_member_number'] ?? '') ?>">
          </div>
          <div>
            <label for="startDate">利用開始希望日</label>
            <input id="startDate" name="start_date" type="date" value="<?= h($formData['start_date'] ?? '') ?>">
          </div>
        </div>
      </details>

      <details class="admin-card admissions-admin-section" <?= $isErrorOpen ? 'open' : '' ?>>
        <summary>キャンペーン・紹介</summary>
        <div class="admissions-admin-grid">
          <div>
            <label for="campaignCode">紹介・キャンペーンコード</label>
            <input id="campaignCode" name="campaign_code" value="<?= h($formData['campaign_code'] ?? '') ?>">
          </div>
        </div>
      </details>

      <details class="admin-card admissions-admin-section" <?= $isErrorOpen ? 'open' : '' ?>>
        <summary>手続希望</summary>
        <div class="admissions-admin-grid admissions-admin-grid--three">
          <?php for ($i = 1; $i <= 3; $i++): ?>
            <div>
              <label for="procedureDate<?= h((string)$i) ?>">第<?= h((string)$i) ?>希望</label>
              <input id="procedureDate<?= h((string)$i) ?>" name="procedure_date_<?= h((string)$i) ?>" type="date" value="<?= h($formData['procedure_date_' . $i] ?? '') ?>">
              <select name="procedure_time_<?= h((string)$i) ?>" aria-label="第<?= h((string)$i) ?>希望時間">
                <?php foreach ($config['procedure_time_slots'] as $slotValue => $slotLabel): ?>
                  <option value="<?= h((string)$slotValue) ?>" <?= ($formData['procedure_time_' . $i] ?? '') === $slotValue ? 'selected' : '' ?>><?= h((string)$slotLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endfor; ?>
        </div>
      </details>

      <details class="admin-card admissions-admin-section" <?= $isErrorOpen ? 'open' : '' ?>>
        <summary>確認事項</summary>
        <div class="admissions-admin-check-list">
          <?php foreach ($config['health_checks'] as $healthKey => $item): ?>
            <label class="admissions-admin-check">
              <input type="checkbox" name="health_checks[]" value="<?= h((string)$healthKey) ?>" <?= in_array($healthKey, $formData['health_checks'] ?? [], true) ? 'checked' : '' ?>>
              <span><?= h((string)$item['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <label for="medicalMemo">健康備考</label>
        <textarea id="medicalMemo" name="medical_memo"><?= h($formData['medical_memo'] ?? '') ?></textarea>
        <label class="admissions-admin-check">
          <input type="checkbox" name="terms_agree" value="1" <?= ($formData['terms_agree'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span>規約同意済みとして扱う</span>
        </label>
      </details>

      <details class="admin-card admissions-admin-section" open>
        <summary>初回費用</summary>
        <dl class="admissions-admin-fee-list">
          <div><dt>希望内容</dt><dd><?= h($formFees['course_label'] ?? '') ?></dd></div>
          <div><dt>月会費</dt><dd><?= h(yen((int)($formFees['monthly_fee'] ?? 0))) ?></dd></div>
          <div><dt>初月会費合計</dt><dd><?= h(yen((int)($formFees['current_month_fee'] ?? 0))) ?></dd></div>
          <?php if (($formData['use_type'] ?? '') === 'add'): ?>
            <div><dt>本館初月会費（日割）</dt><dd><?= h(yen((int)($formFees['main_club_initial_fee'] ?? 0))) ?></dd></div>
            <div><dt>Find Pilates初月会費</dt><dd><?= h(yen((int)($formFees['pilates_current_month_fee'] ?? 0))) ?></dd></div>
          <?php endif; ?>
          <?php if ((int)($formFees['join_fee'] ?? 0) > 0): ?>
            <div><dt>入会費</dt><dd><?= h(yen((int)$formFees['join_fee'])) ?></dd></div>
          <?php endif; ?>
          <?php if ((int)($formFees['processing_fee'] ?? 0) > 0): ?>
            <div><dt>手数料</dt><dd><?= h(yen((int)$formFees['processing_fee'])) ?></dd></div>
          <?php endif; ?>
          <?php if ((int)($formFees['next_month_fee'] ?? 0) > 0): ?>
            <div><dt>翌月会費</dt><dd><?= h(yen((int)$formFees['next_month_fee'])) ?></dd></div>
          <?php endif; ?>
          <?php if ((int)($formFees['main_club_next_month_fee'] ?? 0) > 0): ?>
            <div><dt>翌月本館会費</dt><dd><?= h(yen((int)$formFees['main_club_next_month_fee'])) ?></dd></div>
          <?php endif; ?>
          <?php if ((int)($formFees['pilates_next_month_fee'] ?? 0) > 0): ?>
            <div><dt>翌月Find Pilates種別</dt><dd><?= h(yen((int)$formFees['pilates_next_month_fee'])) ?></dd></div>
          <?php endif; ?>
          <div><dt>キャンペーン値引</dt><dd><?= !empty($formFees['campaign_discount']) ? h('-' . yen((int)$formFees['campaign_discount'])) : 'なし' ?></dd></div>
          <div><dt>初回概算</dt><dd><?= h(yen((int)($formFees['initial_total'] ?? 0))) ?></dd></div>
        </dl>
      </details>

      <details class="admin-card admissions-admin-section" <?= $isErrorOpen ? 'open' : '' ?>>
        <summary>管理メモ</summary>
        <label for="remarks">受付への連絡事項</label>
        <textarea id="remarks" name="remarks"><?= h($formData['remarks'] ?? '') ?></textarea>
        <label for="adminNote">管理メモ</label>
        <textarea id="adminNote" name="admin_note"><?= h($formAdminNote) ?></textarea>
      </details>

      <div class="admissions-admin-save-spacer" aria-hidden="true"></div>
      <div class="admissions-admin-savebar" data-savebar>
        <span class="admissions-admin-dirty" data-dirty-indicator hidden>未保存の変更があります</span>
        <button class="button-link" type="submit"><?= $selectedRecord ? '更新する' : '登録する' ?></button>
        <a class="button-link button-link--muted" href="<?= h($adminSelfUrl) ?><?= $selectedRecord ? '?id=' . h(rawurlencode((string)$selectedRecord['id'])) : '' ?>">入力を戻す</a>
      </div>
    </form>
  </section>
</div>
<?php render_admin_footer(); ?>
