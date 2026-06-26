<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/admin-ui.php';

require_admin();

$config = require __DIR__ . '/../admission/inc/config.php';
require_once __DIR__ . '/../admission/inc/functions.php';

$adminAdmissionsUrl = base_path('/admin/admissions.php');
$token = csrf_token();

$errors = [];
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
$showNew = ($_GET['action'] ?? '') === 'new';
$campaigns = load_campaign_settings($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(post_string('csrf_token'))) {
        http_response_code(400);
        exit('不正な送信です。ページを再読み込みして再度お試しください。');
    }

    $postedCampaigns = $_POST['campaigns'] ?? [];
    $nextCampaigns = [];
    if (is_array($postedCampaigns)) {
        foreach ($postedCampaigns as $campaign) {
            if (!is_array($campaign) || !empty($campaign['delete'])) {
                continue;
            }

            $name = trim((string)($campaign['name'] ?? ''));
            $code = normalize_campaign_code((string)($campaign['code'] ?? ''));
            $autoApply = !empty($campaign['auto_apply']);
            if ($name === '' && $code === '' && !$autoApply) {
                continue;
            }

            if (!$autoApply && $code === '') {
                $errors[] = 'コード不要ではないキャンペーンにはキャンペーンコードを入力してください。';
            }
            if ($autoApply && $code !== '') {
                $errors[] = 'コード不要キャンペーンのキャンペーンコードは空欄にしてください。';
            }

            foreach (['start_date' => '開始日', 'end_date' => '終了日'] as $key => $label) {
                $value = trim((string)($campaign[$key] ?? ''));
                if ($value !== '' && !is_valid_date_string($value)) {
                    $errors[] = $label . 'の日付形式を確認してください。';
                }
            }

            if (($campaign['start_date'] ?? '') !== '' && ($campaign['end_date'] ?? '') !== ''
                && (string)$campaign['start_date'] > (string)$campaign['end_date']
            ) {
                $errors[] = 'キャンペーンの開始日は終了日以前にしてください。';
            }

            $nextCampaigns[] = [
                'id' => (string)($campaign['id'] ?? ''),
                'enabled' => !empty($campaign['enabled']),
                'name' => $name,
                'code' => $code,
                'auto_apply' => $autoApply,
                'start_date' => trim((string)($campaign['start_date'] ?? '')),
                'end_date' => trim((string)($campaign['end_date'] ?? '')),
                'combinable' => !empty($campaign['combinable']),
                'discount_mode' => (string)($campaign['discount_mode'] ?? 'amount'),
                'discount_amount' => (int)($campaign['discount_amount'] ?? 0),
                'discount_rate' => (float)($campaign['discount_rate'] ?? 0),
                'target_single_total' => (int)($campaign['target_single_total'] ?? 0),
                'target_addon_basic_total' => (int)($campaign['target_addon_basic_total'] ?? 0),
                'target_addon_double_total' => (int)($campaign['target_addon_double_total'] ?? 0),
                'discount_rules' => is_array($campaign['discount_rules'] ?? null) ? $campaign['discount_rules'] : [],
                'note' => trim((string)($campaign['note'] ?? '')),
            ];
        }
    }

    if (empty($errors)) {
        if (save_campaign_settings($config, $nextCampaigns)) {
            header('Location: ' . base_path('/admin/campaigns.php?saved=1'));
            exit;
        }
        $errors[] = 'キャンペーン設定の保存に失敗しました。';
    }

    $campaigns = $nextCampaigns;
    $showNew = true;
}

$activeCount = count(array_filter($campaigns, static fn(array $campaign): bool => !empty($campaign['enabled'])));
if ($showNew || !empty($errors)) {
    $campaigns[] = [
        'id' => '',
        'enabled' => false,
        'name' => '',
        'code' => '',
        'auto_apply' => false,
        'start_date' => '',
        'end_date' => '',
        'combinable' => false,
        'discount_mode' => 'amount',
        'discount_amount' => 0,
        'discount_rate' => 0,
        'target_single_total' => 0,
        'target_addon_basic_total' => 0,
        'target_addon_double_total' => 0,
        'discount_rules' => [],
        'note' => '',
    ];
}

render_admin_header('キャンペーン設定', 'campaigns-admin-page');
?>
<section class="admin-card campaigns-admin-page-head">
  <div>
    <h2>キャンペーン設定</h2>
    <p class="admin-note">キャンペーンコード値引と、コード不要で自動適用する期間限定キャンペーンを管理します。</p>
    <p class="campaigns-admin-count">有効なキャンペーン：<?= h((string)$activeCount) ?>件</p>
  </div>
  <div class="campaigns-admin-actions">
    <a class="button-link" href="<?= h(base_path('/admin/campaigns.php?action=new')) ?>">新しいキャンペーンを追加</a>
    <a class="button-link button-link--muted" href="<?= h($adminAdmissionsUrl) ?>">入会受付へ戻る</a>
  </div>
</section>

<?php if ($saved): ?>
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

<form method="post" class="campaigns-admin-form" data-campaign-form data-dirty-form>
  <input type="hidden" name="csrf_token" value="<?= h($token) ?>">

  <div class="campaigns-admin-list">
    <?php if (empty($campaigns)): ?>
      <section class="admin-card">
        <p class="admin-note">キャンペーンはまだ登録されていません。「新しいキャンペーンを追加」から登録してください。</p>
      </section>
    <?php endif; ?>

    <?php foreach ($campaigns as $index => $campaign): ?>
      <?php
      $isNew = (string)($campaign['id'] ?? '') === '';
      $isOpen = $isNew || !empty($errors);
      $mode = (string)($campaign['discount_mode'] ?? 'amount');
      $period = trim(((string)($campaign['start_date'] ?? '') ?: '開始日なし') . ' 〜 ' . ((string)($campaign['end_date'] ?? '') ?: '終了日なし'));
      $applyType = !empty($campaign['auto_apply']) ? '自動適用' : 'コード適用';
      $discountSummary = $mode === 'target_total'
          ? '初回合計指定'
          : ($mode === 'rules'
              ? '項目別設定'
              : ($mode === 'percent' ? '割引率 ' . (string)($campaign['discount_rate'] ?? 0) . '%' : '値引額 ' . yen((int)($campaign['discount_amount'] ?? 0))));
      $rulesForUi = is_array($campaign['discount_rules'] ?? null) ? array_values($campaign['discount_rules']) : [];
      if ($rulesForUi === []) {
          $rulesForUi = array_map(
              static fn(string $component): array => [
                  'enabled' => false,
                  'component' => $component,
                'scope' => 'all',
                'discount_type' => $component === 'initial_total' ? 'amount' : 'free',
                'amount' => 0,
              ],
              array_keys(campaign_discount_components())
          );
      }
      while (count($rulesForUi) < 8) {
          $rulesForUi[] = [
              'enabled' => false,
              'component' => 'current_month_fee',
                'scope' => 'all',
                'discount_type' => 'amount',
                'amount' => 0,
          ];
      }
      ?>
      <details class="admin-card campaigns-admin-card" data-campaign-card <?= $isOpen ? 'open' : '' ?>>
        <summary class="campaigns-admin-summary">
          <span>
            <strong><?= $isNew ? '新規キャンペーン' : h((string)($campaign['name'] ?: '名称未設定')) ?></strong>
            <small><?= !empty($campaign['enabled']) ? '有効' : '無効' ?> / <?= h($applyType) ?> / <?= h($period) ?></small>
          </span>
          <span class="campaigns-admin-summary-meta"><?= h($discountSummary) ?></span>
        </summary>

        <input type="hidden" name="campaigns[<?= h((string)$index) ?>][id]" value="<?= h((string)($campaign['id'] ?? '')) ?>">

        <fieldset class="campaigns-admin-fieldset">
          <legend>基本設定</legend>
          <div class="campaigns-admin-grid">
            <label class="campaigns-admin-check">
              <input type="checkbox" name="campaigns[<?= h((string)$index) ?>][enabled]" value="1" <?= !empty($campaign['enabled']) ? 'checked' : '' ?>>
              <span>有効</span>
            </label>
            <label class="campaigns-admin-check">
              <input type="checkbox" name="campaigns[<?= h((string)$index) ?>][auto_apply]" value="1" data-campaign-auto <?= !empty($campaign['auto_apply']) ? 'checked' : '' ?>>
              <span>キャンペーンコード不要で期間中に自動適用</span>
            </label>
            <label class="campaigns-admin-check">
              <input type="checkbox" name="campaigns[<?= h((string)$index) ?>][combinable]" value="1" <?= !empty($campaign['combinable']) ? 'checked' : '' ?>>
              <span>他キャンペーンと併用可能</span>
            </label>
          </div>

          <div class="campaigns-admin-grid">
            <div>
              <label for="campaignName<?= h((string)$index) ?>">名称</label>
              <input id="campaignName<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][name]" value="<?= h((string)($campaign['name'] ?? '')) ?>">
            </div>
            <div data-campaign-code-field>
              <label for="campaignCode<?= h((string)$index) ?>">キャンペーンコード</label>
              <input id="campaignCode<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][code]" value="<?= h((string)($campaign['code'] ?? '')) ?>" autocomplete="off">
              <p class="admin-note">自動適用を有効にする場合は空欄にしてください。</p>
            </div>
          </div>

          <div class="campaigns-admin-grid campaigns-admin-grid--three">
            <div>
              <label for="campaignStart<?= h((string)$index) ?>">開始日</label>
              <input id="campaignStart<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][start_date]" type="date" value="<?= h((string)($campaign['start_date'] ?? '')) ?>">
            </div>
            <div>
              <label for="campaignEnd<?= h((string)$index) ?>">終了日</label>
              <input id="campaignEnd<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][end_date]" type="date" value="<?= h((string)($campaign['end_date'] ?? '')) ?>">
            </div>
            <div>
              <label for="campaignMode<?= h((string)$index) ?>">値引方式</label>
              <select id="campaignMode<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][discount_mode]" data-campaign-mode>
                <option value="amount" <?= $mode === 'amount' ? 'selected' : '' ?>>値引額</option>
                <option value="percent" <?= $mode === 'percent' ? 'selected' : '' ?>>割引率（%）</option>
                <option value="target_total" <?= $mode === 'target_total' ? 'selected' : '' ?>>初回合計を指定</option>
                <option value="rules" <?= $mode === 'rules' ? 'selected' : '' ?>>項目別設定</option>
              </select>
            </div>
          </div>
        </fieldset>

        <fieldset class="campaigns-admin-fieldset">
          <legend>値引内容</legend>
          <div class="campaigns-admin-grid" data-campaign-amount-fields>
            <div>
              <label for="campaignAmount<?= h((string)$index) ?>">値引額</label>
              <input id="campaignAmount<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][discount_amount]" type="number" min="0" step="1" value="<?= h((string)($campaign['discount_amount'] ?? 0)) ?>">
            </div>
          </div>

          <div class="campaigns-admin-grid" data-campaign-rate-fields>
            <div>
              <label for="campaignRate<?= h((string)$index) ?>">割引率（%）</label>
              <input id="campaignRate<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][discount_rate]" type="number" min="0" max="100" step="1" value="<?= h((string)($campaign['discount_rate'] ?? 0)) ?>">
              <p class="admin-note">100分率で入力してください。例：50 = 50%割引。</p>
            </div>
          </div>

          <div class="campaigns-admin-grid campaigns-admin-grid--three" data-campaign-target-fields>
            <div>
              <label for="campaignSingle<?= h((string)$index) ?>">単体適用後の初回合計</label>
              <input id="campaignSingle<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][target_single_total]" type="number" min="0" step="1" value="<?= h((string)($campaign['target_single_total'] ?? 0)) ?>">
            </div>
            <div>
              <label for="campaignAddonBasic<?= h((string)$index) ?>">本館併用 ベーシック適用後</label>
              <input id="campaignAddonBasic<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][target_addon_basic_total]" type="number" min="0" step="1" value="<?= h((string)($campaign['target_addon_basic_total'] ?? 0)) ?>">
            </div>
            <div>
              <label for="campaignAddonDouble<?= h((string)$index) ?>">本館併用 ダブル適用後</label>
              <input id="campaignAddonDouble<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][target_addon_double_total]" type="number" min="0" step="1" value="<?= h((string)($campaign['target_addon_double_total'] ?? 0)) ?>">
            </div>
          </div>

          <div class="campaigns-admin-rules" data-campaign-rule-fields>
            <p class="admin-note">必要な行だけ有効にしてください。例：入会金を無料、当月会費をベーシックだけ無料、翌月会費をダブルだけ半額相当にする、などを組み合わせられます。</p>
            <div class="campaigns-admin-rule-head" aria-hidden="true">
              <span>有効</span>
              <span>対象項目</span>
              <span>会員種別</span>
              <span>割引</span>
              <span>金額・率</span>
            </div>
            <?php foreach ($rulesForUi as $ruleIndex => $rule): ?>
              <?php $rule = normalize_campaign_rule(is_array($rule) ? $rule : []); ?>
              <div class="campaigns-admin-rule-row">
                <label class="campaigns-admin-check campaigns-admin-rule-enabled">
                  <input type="checkbox" name="campaigns[<?= h((string)$index) ?>][discount_rules][<?= h((string)$ruleIndex) ?>][enabled]" value="1" <?= !empty($rule['enabled']) ? 'checked' : '' ?>>
                  <span>有効</span>
                </label>
                <div>
                  <label for="campaignRuleComponent<?= h((string)$index) ?>_<?= h((string)$ruleIndex) ?>">対象項目</label>
                  <select id="campaignRuleComponent<?= h((string)$index) ?>_<?= h((string)$ruleIndex) ?>" name="campaigns[<?= h((string)$index) ?>][discount_rules][<?= h((string)$ruleIndex) ?>][component]">
                    <?php foreach (campaign_discount_components() as $componentKey => $componentLabel): ?>
                      <option value="<?= h((string)$componentKey) ?>" <?= $rule['component'] === $componentKey ? 'selected' : '' ?>><?= h((string)$componentLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="campaignRuleScope<?= h((string)$index) ?>_<?= h((string)$ruleIndex) ?>">会員種別</label>
                  <select id="campaignRuleScope<?= h((string)$index) ?>_<?= h((string)$ruleIndex) ?>" name="campaigns[<?= h((string)$index) ?>][discount_rules][<?= h((string)$ruleIndex) ?>][scope]">
                    <?php foreach (campaign_plan_scopes() as $scopeKey => $scopeLabel): ?>
                      <option value="<?= h((string)$scopeKey) ?>" <?= $rule['scope'] === $scopeKey ? 'selected' : '' ?>><?= h((string)$scopeLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="campaignRuleType<?= h((string)$index) ?>_<?= h((string)$ruleIndex) ?>">割引</label>
                  <select id="campaignRuleType<?= h((string)$index) ?>_<?= h((string)$ruleIndex) ?>" name="campaigns[<?= h((string)$index) ?>][discount_rules][<?= h((string)$ruleIndex) ?>][discount_type]">
                    <option value="free" <?= $rule['discount_type'] === 'free' ? 'selected' : '' ?>>無料</option>
                    <option value="amount" <?= $rule['discount_type'] === 'amount' ? 'selected' : '' ?>>値引額</option>
                    <option value="percent" <?= $rule['discount_type'] === 'percent' ? 'selected' : '' ?>>割引率（%）</option>
                    <option value="target_amount" <?= $rule['discount_type'] === 'target_amount' ? 'selected' : '' ?>>適用後金額</option>
                  </select>
                </div>
                <div>
                  <label for="campaignRuleAmount<?= h((string)$index) ?>_<?= h((string)$ruleIndex) ?>">金額・率</label>
                  <input id="campaignRuleAmount<?= h((string)$index) ?>_<?= h((string)$ruleIndex) ?>" name="campaigns[<?= h((string)$index) ?>][discount_rules][<?= h((string)$ruleIndex) ?>][amount]" type="number" min="0" step="1" value="<?= h((string)($rule['amount'] ?? 0)) ?>">
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="campaigns-admin-fieldset">
          <legend>メモ</legend>
          <label for="campaignNote<?= h((string)$index) ?>">管理メモ</label>
          <textarea id="campaignNote<?= h((string)$index) ?>" name="campaigns[<?= h((string)$index) ?>][note]"><?= h((string)($campaign['note'] ?? '')) ?></textarea>
        </fieldset>

        <?php if (!$isNew): ?>
          <fieldset class="campaigns-admin-danger">
            <legend>危険な操作</legend>
            <p><?= h((string)($campaign['name'] ?: 'このキャンペーン')) ?> は、保存時に削除チェックが入っていると削除されます。</p>
            <label class="campaigns-admin-check">
              <input type="checkbox" name="campaigns[<?= h((string)$index) ?>][delete]" value="1" data-campaign-delete>
              <span>このキャンペーンを削除する</span>
            </label>
            <p class="campaigns-admin-delete-note" data-campaign-delete-note hidden>保存すると削除されます。</p>
          </fieldset>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>
  </div>

  <div class="campaigns-admin-save-spacer" aria-hidden="true"></div>
  <div class="campaigns-admin-savebar" data-savebar>
    <span class="campaigns-admin-dirty" data-dirty-indicator hidden>未保存の変更があります</span>
    <button class="button-link" type="submit">キャンペーン設定を保存</button>
    <a class="button-link button-link--muted" href="<?= h($adminAdmissionsUrl) ?>">入会受付へ戻る</a>
  </div>
</form>
<?php render_admin_footer(); ?>
