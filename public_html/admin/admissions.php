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
$slimStatusOptions = $config['admin']['slim_status_options'] ?? [];
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

$originalData = is_array($selectedRecord['data'] ?? null) ? $selectedRecord['data'] : admission_blank_data();
$formData = is_array($selectedRecord['normalized'] ?? null) ? array_merge(admission_blank_data(), $originalData, $selectedRecord['normalized']) : $originalData;
$formStatus = (string)($selectedRecord['status'] ?? 'new');
$formSlimStatus = (string)($selectedRecord['slim_status'] ?? 'not_started');
$formAdminNote = (string)($selectedRecord['admin_note'] ?? '');
$formPhoto = $selectedRecord['photo'] ?? [];
$formCreatedAt = (string)($selectedRecord['created_at'] ?? '');
$formFees = $selectedRecord['fees'] ?? calculate_fees($config, $formData);
$formOperations = is_array($selectedRecord['operations'] ?? null) ? $selectedRecord['operations'] : slim_operations_with_readiness($formData, is_array($formPhoto) ? $formPhoto : []);
$formProgress = slim_operation_progress($formOperations);
$formReadiness = validate_slim_readiness($formData, $formOperations, is_array($formPhoto) ? $formPhoto : []);

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
    $originalData = is_array($existingRecord['data'] ?? null) ? $existingRecord['data'] : $formData;

    if (!isset($statusOptions[$formStatus])) {
        $formStatus = 'new';
    }

    $errors = validate_admin_record($config, $formData);
    $formFees = calculate_fees($config, $formData);
    $formOperations = slim_operations_with_readiness($formData, is_array($formPhoto) ? $formPhoto : []);
    $formProgress = slim_operation_progress($formOperations);
    $formReadiness = validate_slim_readiness($formData, $formOperations, is_array($formPhoto) ? $formPhoto : []);

    if (empty($errors)) {
        $adminUser = function_exists('admin_user') ? admin_user() : null;
        $overrides = [
            'status' => $formStatus,
            'slim_status' => slim_status_from_operations($formData, $formOperations),
            'admin_note' => $formAdminNote,
            'mail_status' => $existingRecord['mail_status'] ?? [],
            'original_data' => $originalData,
            'normalized' => admission_normalized_payload($formData),
            'actor_admin_id' => is_array($adminUser) ? (int)($adminUser['id'] ?? 0) : null,
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
        'slim_status' => $formSlimStatus,
        'created_at' => $formCreatedAt !== '' ? $formCreatedAt : date('Y-m-d H:i:s'),
        'created_at_ts' => $selectedRecord['created_at_ts'] ?? time(),
        'admin_note' => $formAdminNote,
        'mail_status' => $selectedRecord['mail_status'] ?? [],
        'original_data' => $originalData,
        'normalized' => admission_normalized_payload($formData),
    ]
);
$previewRecord['operations'] = $formOperations;
$previewRecord['operation_progress'] = $formProgress;
$previewRecord['readiness'] = $formReadiness;
$formSlimStatus = slim_status_from_operations($formData, $formOperations);
$previewRecord['slim_status'] = $formSlimStatus;

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

function admission_readiness_label(string $code): string
{
    $labels = [
        'surname_missing' => '姓が未入力です',
        'given_name_missing' => '名が未入力です',
        'surname_kana_missing' => 'セイが未入力です',
        'given_name_kana_missing' => 'メイが未入力です',
        'birth_missing' => '生年月日が未入力です',
        'gender_missing' => '性別が未入力です',
        'phone_missing' => '電話番号が未入力です',
        'phone_type_missing' => '電話番号種別が未入力です',
        'start_date_missing' => '利用開始日が未入力です',
        'actual_procedure_date_missing' => '実手続日が未入力です',
        'legacy_weekend_plan' => '旧ウィークエンドプランは自動転記できません',
        'operations_missing' => '有効なSLIM操作キューがありません',
        'main_member_number_missing' => '既存本館会員番号が未入力です',
        'slim_member_number_missing' => '後続の追加届に使うSLIM会員番号が未入力です',
        'photo_missing' => '顔写真が未登録です',
    ];

    return $labels[$code] ?? $code;
}

function admission_operation_status_label(string $status): string
{
    $labels = [
        'pending' => '待機',
        'ready' => '準備OK',
        'blocked' => '準備不足',
        'needs_review' => '要確認',
        'in_progress' => '登録中',
        'filled' => '転記済み',
        'completed' => '登録済み',
    ];

    return $labels[$status] ?? $status;
}

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
      <label for="admissionSlimStatusFilter">SLIMステータス</label>
      <select id="admissionSlimStatusFilter" data-admission-slim-status-filter>
        <option value="">すべて</option>
        <?php foreach ($slimStatusOptions as $statusKey => $statusLabel): ?>
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
          $slimStatus = (string)($record['slim_status'] ?? 'not_started');
          $progress = is_array($record['operation_progress'] ?? null) ? $record['operation_progress'] : slim_operation_progress(is_array($record['operations'] ?? null) ? $record['operations'] : []);
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
            data-slim-status="<?= h($slimStatus) ?>"
            data-search="<?= h($searchText) ?>"
            <?= $isActive ? 'aria-current="page"' : '' ?>
          >
            <span class="admissions-admin-item-main">
              <strong><?= h((string)($recordData['name'] ?? '氏名未設定')) ?></strong>
              <span><?= h($statusOptions[$status] ?? $status) ?></span>
            </span>
            <span>受付日時：<?= h(datetime_label($record['created_at'] ?? '')) ?></span>
            <span>希望内容：<?= h((string)($recordFees['course_label'] ?? '未設定')) ?></span>
            <span>SLIM：<?= h((string)($slimStatusOptions[$slimStatus] ?? $slimStatus)) ?> / <?= h((string)($progress['completed'] ?? 0)) ?><?= '/' ?><?= h((string)($progress['total'] ?? 0)) ?></span>
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
        <summary>SLIM登録準備</summary>
        <div class="admissions-admin-grid">
          <div>
            <label for="actualProcedureDate">実手続日</label>
            <input id="actualProcedureDate" name="actual_procedure_date" type="date" value="<?= h($formData['actual_procedure_date'] ?? '') ?>" required>
          </div>
          <div>
            <label for="slimMemberNumber">SLIM会員番号</label>
            <input id="slimMemberNumber" name="slim_member_number" value="<?= h($formData['slim_member_number'] ?? '') ?>">
          </div>
          <div>
            <label>SLIMステータス</label>
            <div class="admissions-admin-status-card">
              <strong><?= h((string)($slimStatusOptions[$formSlimStatus] ?? $formSlimStatus)) ?></strong>
              <span><?= h((string)($formProgress['completed'] ?? 0)) ?> / <?= h((string)($formProgress['total'] ?? 0)) ?> operations completed</span>
            </div>
          </div>
          <div>
            <label>未完了件数</label>
            <div class="admissions-admin-status-card">
              <strong><?= h((string)($formProgress['incomplete'] ?? 0)) ?></strong>
              <span>ready: <?= h((string)($formProgress['ready'] ?? 0)) ?> / blocked: <?= h((string)($formProgress['blocked'] ?? 0)) ?></span>
            </div>
          </div>
        </div>

        <?php if (!empty($formReadiness['errors']) || !empty($formReadiness['warnings'])): ?>
          <div class="admissions-admin-readiness">
            <?php if (!empty($formReadiness['errors'])): ?>
              <div class="admissions-admin-readiness-box is-error">
                <strong>開始前に確認が必要</strong>
                <ul>
                  <?php foreach ($formReadiness['errors'] as $code): ?>
                    <li><?= h(admission_readiness_label((string)$code)) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
            <?php if (!empty($formReadiness['warnings'])): ?>
              <div class="admissions-admin-readiness-box is-warning">
                <strong>警告</strong>
                <ul>
                  <?php foreach ($formReadiness['warnings'] as $code): ?>
                    <li><?= h(admission_readiness_label((string)$code)) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="admissions-admin-operation-list">
          <?php if (empty($formOperations)): ?>
            <p class="admin-note">この申込から自動生成できるSLIM操作はありません。旧プランまたは未対応の組み合わせを確認してください。</p>
          <?php else: ?>
            <?php foreach ($formOperations as $operation): ?>
              <?php $opErrors = is_array($operation['readiness_errors'] ?? null) ? $operation['readiness_errors'] : []; ?>
              <article class="admissions-admin-operation-card is-<?= h((string)($operation['status'] ?? 'pending')) ?>">
                <div class="admissions-admin-operation-head">
                  <strong><?= h((string)($operation['sequence_no'] ?? '')) ?>. <?= h((string)($operation['business_label'] ?? '')) ?></strong>
                  <span><?= h(admission_operation_status_label((string)($operation['status'] ?? 'pending'))) ?></span>
                </div>
                <dl>
                  <div><dt>page</dt><dd><?= h((string)($operation['page_type'] ?? '')) ?></dd></div>
                  <div><dt>course</dt><dd><?= h((string)($operation['course_code'] ?? '')) ?> / <?= h((string)($operation['course_id'] ?? '')) ?></dd></div>
                  <div><dt>application_date</dt><dd><?= h(date_label($operation['application_date'] ?? '')) ?></dd></div>
                  <div><dt>start_date</dt><dd><?= h(date_label($operation['start_date'] ?? '')) ?></dd></div>
                  <?php if (!empty($operation['reason_id'])): ?>
                    <div><dt>reason</dt><dd><?= h((string)$operation['reason_id']) ?> / <?= h((string)($operation['reason_label'] ?? '')) ?></dd></div>
                  <?php endif; ?>
                </dl>
                <?php if (!empty($opErrors)): ?>
                  <ul class="admissions-admin-operation-errors">
                    <?php foreach ($opErrors as $code): ?>
                      <li><?= h(admission_readiness_label((string)$code)) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
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

      <details class="admin-card admissions-admin-section">
        <summary>申込原本と転記値</summary>
        <div class="admissions-admin-compare-table">
          <div class="admissions-admin-compare-head">
            <span>項目</span>
            <span>申込原本</span>
            <span>SLIM転記値</span>
          </div>
          <?php foreach ([
              'name' => '氏名',
              'kana' => 'フリガナ',
              'birth' => '生年月日',
              'gender' => '性別',
              'phone_type' => '電話種別',
              'phone' => '電話番号',
              'postal_code' => '郵便番号',
              'city_area' => '住所1',
              'street_address' => '住所2',
              'building' => '住所3',
              'start_date' => '利用開始日',
          ] as $field => $label): ?>
            <?php
            $originalValue = (string)($originalData[$field] ?? '');
            $transferValue = (string)($formData[$field] ?? '');
            $different = $originalValue !== $transferValue;
            ?>
            <div class="<?= $different ? 'is-different' : '' ?>">
              <span><?= h($label) ?></span>
              <span><?= h($originalValue) ?></span>
              <span><?= h($transferValue) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
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
            <label for="phoneType">電話番号種別</label>
            <select id="phoneType" name="phone_type">
              <option value="" <?= ($formData['phone_type'] ?? '') === '' ? 'selected' : '' ?>>選択してください</option>
              <option value="mobile" <?= ($formData['phone_type'] ?? '') === 'mobile' ? 'selected' : '' ?>>携帯TEL</option>
              <option value="home" <?= ($formData['phone_type'] ?? '') === 'home' ? 'selected' : '' ?>>自宅TEL</option>
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
