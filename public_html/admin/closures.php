<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/audit.php';
require_once __DIR__ . '/../app/trial-schedule.php';

require_admin();

$closureTypeOptions = [
    'all' => '全館休館',
    'pilates' => 'ピラティス体験のみ停止',
    'self_esthe' => 'セルフエステ体験のみ停止',
    'visit' => '見学のみ停止',
    'maintenance' => 'メンテナンス',
    'cleaning' => '清掃',
    'shooting' => '撮影',
    'internal' => '社内利用',
];

$tableReady = db_table_exists_for_schedule('trial_closures');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('不正な送信です。');
    }
    if (!$tableReady) {
        $errors[] = 'trial_closures テーブルがありません。マイグレーションを反映してください。';
    } elseif (isset($_POST['deactivate_id'])) {
        $stmt = db()->prepare('UPDATE trial_closures SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => (int)$_POST['deactivate_id']]);
        audit_log('trial_closure', (string)(int)$_POST['deactivate_id'], 'closure_deactivate');
        flash_set('ok', '休館日・利用停止を無効化しました。');
        redirect(base_path('/admin/closures.php'));
    } else {
        $closureType = (string)($_POST['closure_type'] ?? '');
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $startTime = trim((string)($_POST['start_time'] ?? ''));
        $endTime = trim((string)($_POST['end_time'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        if (!array_key_exists($closureType, $closureTypeOptions)) {
            $errors[] = '停止種別を選択してください。';
        }
        if (!valid_date_string($startDate) || !valid_date_string($endDate) || $startDate > $endDate) {
            $errors[] = '期間を正しく入力してください。';
        }
        if (($startTime !== '' || $endTime !== '') && (!valid_time_string($startTime) || !valid_time_string($endTime) || time_to_minutes($startTime) >= time_to_minutes($endTime))) {
            $errors[] = '時間帯を正しく入力してください。';
        }
        if ($title === '') {
            $errors[] = '表示名を入力してください。';
        }

        if ($errors === []) {
            $stmt = db()->prepare(
                'INSERT INTO trial_closures (
                    closure_type, start_date, end_date, start_time, end_time, title, note
                ) VALUES (
                    :closure_type, :start_date, :end_date, :start_time, :end_time, :title, :note
                )'
            );
            $stmt->execute([
                'closure_type' => $closureType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => $startTime !== '' ? sql_time($startTime) : null,
                'end_time' => $endTime !== '' ? sql_time($endTime) : null,
                'title' => $title,
                'note' => $note !== '' ? $note : null,
            ]);
            $id = (int)db()->lastInsertId();
            audit_log('trial_closure', (string)$id, 'closure_create', null, $_POST);
            flash_set('ok', '休館日・利用停止を登録しました。');
            redirect(base_path('/admin/closures.php'));
        }
    }
}

$closures = [];
if ($tableReady) {
    $stmt = db()->query('SELECT * FROM trial_closures WHERE is_active = 1 ORDER BY start_date ASC, start_time ASC, id DESC');
    $closures = $stmt->fetchAll();
}

render_admin_header('休館日・利用停止');
?>
<section class="admin-card">
  <h2>休館日・利用停止</h2>
  <p class="admin-note">全館休館、体験種別ごとの停止、清掃・メンテナンスなどを登録します。繰り返し枠は削除せず、保存前プレビューで停止として検知します。</p>
  <?php if (!$tableReady): ?>
    <p class="error">DBマイグレーション未反映です。`database/migrations/20260623_trial_schedule_upgrade.sql` をMySQLに適用してください。</p>
  <?php endif; ?>
  <?php if ($errors !== []): ?><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" class="admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label>停止種別
      <select name="closure_type" <?= !$tableReady ? 'disabled' : '' ?>>
        <?php foreach ($closureTypeOptions as $value => $label): ?>
          <option value="<?= h($value) ?>"><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>表示名<input name="title" maxlength="120" <?= !$tableReady ? 'disabled' : '' ?>></label>
    <label>開始日<input type="date" name="start_date" value="<?= h((new DateTimeImmutable('today'))->format('Y-m-d')) ?>" <?= !$tableReady ? 'disabled' : '' ?>></label>
    <label>終了日<input type="date" name="end_date" value="<?= h((new DateTimeImmutable('today'))->format('Y-m-d')) ?>" <?= !$tableReady ? 'disabled' : '' ?>></label>
    <label>開始時刻<input type="time" name="start_time" <?= !$tableReady ? 'disabled' : '' ?>></label>
    <label>終了時刻<input type="time" name="end_time" <?= !$tableReady ? 'disabled' : '' ?>></label>
    <label class="is-full">備考<textarea name="note" <?= !$tableReady ? 'disabled' : '' ?>></textarea></label>
    <div class="form-actions is-full">
      <button type="submit" <?= !$tableReady ? 'disabled' : '' ?>>登録する</button>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slots.php')) ?>">スケジュールへ戻る</a>
    </div>
  </form>
</section>

<section class="admin-card">
  <h2>登録済み</h2>
  <table class="admin-table">
    <thead><tr><th>期間</th><th>時間</th><th>種別</th><th>表示名</th><th>操作</th></tr></thead>
    <tbody>
      <?php if ($closures === []): ?><tr><td colspan="5">登録はありません。</td></tr><?php endif; ?>
      <?php foreach ($closures as $closure): ?>
        <tr>
          <td><?= h((string)$closure['start_date'] . ' - ' . (string)$closure['end_date']) ?></td>
          <td><?= h(!empty($closure['start_time']) ? format_time_range((string)$closure['start_time'], (string)$closure['end_time']) : '終日') ?></td>
          <td><?= h($closureTypeOptions[(string)$closure['closure_type']] ?? (string)$closure['closure_type']) ?></td>
          <td><?= h((string)$closure['title']) ?></td>
          <td>
            <form method="post" class="inline-form">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="deactivate_id" value="<?= h((string)$closure['id']) ?>">
              <button type="submit" class="button-link button-link--danger">無効化</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_admin_footer(); ?>
