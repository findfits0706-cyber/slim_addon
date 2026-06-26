<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/trial.php';

require_admin();

$templateId = (int)($_GET['template_id'] ?? $_POST['template_id'] ?? 0);
$template = $templateId > 0 ? (fetch_slot_templates($templateId)[0] ?? null) : null;
if (!$template) {
    http_response_code(404);
    exit('指定された体験枠が見つかりません。');
}

$defaults = [
    'id' => '',
    'target_date' => '',
    'exception_type' => 'change',
    'new_start_time' => '',
    'new_end_time' => '',
    'substitute_instructor_name' => '',
    'new_capacity' => '',
    'status' => 'open',
    'note' => '',
];
$form = $defaults;
$errors = [];

if (isset($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM trial_slot_exceptions WHERE id = :id AND template_id = :template_id');
    $stmt->execute(['id' => (int)$_GET['id'], 'template_id' => $templateId]);
    $existing = $stmt->fetch();
    if ($existing) {
        $form = array_merge($form, array_map('strval', $existing));
    }
} elseif (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['date'])) {
    $form['target_date'] = (string)$_GET['date'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正な送信です。';
    } elseif (isset($_POST['delete_exception_id'])) {
        $stmt = db()->prepare('DELETE FROM trial_slot_exceptions WHERE id = :id AND template_id = :template_id');
        $stmt->execute([
            'id' => (int)$_POST['delete_exception_id'],
            'template_id' => $templateId,
        ]);
        redirect(base_path('/admin/slot-exception.php?template_id=' . $templateId));
    } else {
        $form = preserve_form_data($_POST, $defaults);
        $form = array_map(static fn($value) => is_string($value) ? trim($value) : $value, $form);

        if ($form['target_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$form['target_date'])) {
            $errors[] = '対象日を入力してください。';
        } elseif (!slot_date_matches_template($template, (string)$form['target_date'])) {
            $errors[] = '対象日がこの枠の開催日と一致しません。';
        }
        if (!array_key_exists($form['exception_type'], exception_type_options())) {
            $errors[] = '変更種別を選択してください。';
        }
        if ($form['exception_type'] === 'substitute' && $form['substitute_instructor_name'] === '') {
            $errors[] = '代行の場合は代行担当を入力してください。';
        }
        if ($form['new_capacity'] !== '' && (!ctype_digit((string)$form['new_capacity']) || (int)$form['new_capacity'] <= 0)) {
            $errors[] = '変更後定員は1以上の整数で入力してください。';
        }
        if ($form['new_start_time'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $form['new_start_time'])) {
            $errors[] = '変更後開始時間が不正です。';
        }
        if ($form['new_end_time'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $form['new_end_time'])) {
            $errors[] = '変更後終了時間が不正です。';
        }
        if (($form['new_start_time'] === '') !== ($form['new_end_time'] === '')) {
            $errors[] = '時間変更は開始時間と終了時間を両方入力してください。';
        }
        if ($form['new_start_time'] !== '' && $form['new_end_time'] !== '' && $form['new_start_time'] >= $form['new_end_time']) {
            $errors[] = '変更後終了時間は開始時間より後にしてください。';
        }
        if (!array_key_exists($form['status'], slot_status_options())) {
            $errors[] = '状態を選択してください。';
        }
        if ($form['target_date'] !== '') {
            $stmt = db()->prepare("SELECT COUNT(*) FROM trial_bookings WHERE template_id = :template_id AND booking_date = :booking_date AND status <> 'cancelled'");
            $stmt->execute([
                'template_id' => $templateId,
                'booking_date' => $form['target_date'],
            ]);
            $bookingCount = (int)$stmt->fetchColumn();
            if ($form['new_capacity'] !== '' && (int)$form['new_capacity'] < $bookingCount) {
                $errors[] = '変更後定員は現在の予約人数（' . $bookingCount . '名）以上にしてください。';
            }
        }
        if ($form['id'] === '' && $form['target_date'] !== '') {
            $stmt = db()->prepare('SELECT COUNT(*) FROM trial_slot_exceptions WHERE template_id = :template_id AND target_date = :target_date');
            $stmt->execute([
                'template_id' => $templateId,
                'target_date' => $form['target_date'],
            ]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = '同じ開催日の例外設定が既にあります。既存の設定を編集してください。';
            }
        }

        if ($errors === []) {
            $params = [
                'template_id' => $templateId,
                'target_date' => $form['target_date'],
                'exception_type' => $form['exception_type'],
                'new_start_time' => $form['new_start_time'] !== '' ? $form['new_start_time'] . ':00' : null,
                'new_end_time' => $form['new_end_time'] !== '' ? $form['new_end_time'] . ':00' : null,
                'substitute_instructor_name' => $form['substitute_instructor_name'] !== '' ? $form['substitute_instructor_name'] : null,
                'new_capacity' => $form['new_capacity'] !== '' ? (int)$form['new_capacity'] : null,
                'status' => $form['status'],
                'note' => $form['note'] !== '' ? $form['note'] : null,
            ];

            if ($form['id'] !== '') {
                $params['id'] = (int)$form['id'];
                $sql = "UPDATE trial_slot_exceptions SET
                    target_date = :target_date, exception_type = :exception_type,
                    new_start_time = :new_start_time, new_end_time = :new_end_time,
                    substitute_instructor_name = :substitute_instructor_name,
                    new_capacity = :new_capacity, status = :status, note = :note
                    WHERE id = :id AND template_id = :template_id";
            } else {
                $sql = "INSERT INTO trial_slot_exceptions (
                    template_id, target_date, exception_type, new_start_time, new_end_time,
                    substitute_instructor_name, new_capacity, status, note
                ) VALUES (
                    :template_id, :target_date, :exception_type, :new_start_time, :new_end_time,
                    :substitute_instructor_name, :new_capacity, :status, :note
                )";
            }

            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            redirect(base_path('/admin/slot-exception.php?template_id=' . $templateId));
        }
    }
}

$stmt = db()->prepare('SELECT * FROM trial_slot_exceptions WHERE template_id = :template_id ORDER BY target_date ASC, id DESC');
$stmt->execute(['template_id' => $templateId]);
$exceptions = $stmt->fetchAll();

render_admin_header('この日だけ変更・代行設定');
?>
<section class="admin-card">
  <h1>この日だけ変更・代行設定</h1>
  <p class="admin-note"><?= h(genre_label((string)$template['genre'])) ?> / <?= h((string)$template['lesson_name']) ?></p>
  <?php if ($errors !== []): ?><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" class="admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="template_id" value="<?= h((string)$templateId) ?>">
    <input type="hidden" name="id" value="<?= h((string)$form['id']) ?>">
    <label>対象日<input type="date" name="target_date" value="<?= h((string)$form['target_date']) ?>" required></label>
    <label>変更種別
      <select name="exception_type" required>
        <?php foreach (exception_type_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= (string)$form['exception_type'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>変更後開始時間<input type="time" name="new_start_time" value="<?= h(substr((string)$form['new_start_time'], 0, 5)) ?>"></label>
    <label>変更後終了時間<input type="time" name="new_end_time" value="<?= h(substr((string)$form['new_end_time'], 0, 5)) ?>"></label>
    <label>代行担当<input name="substitute_instructor_name" value="<?= h((string)$form['substitute_instructor_name']) ?>"></label>
    <label>変更後定員<input type="number" min="1" name="new_capacity" value="<?= h((string)$form['new_capacity']) ?>"></label>
    <label>状態
      <select name="status">
        <?php foreach (slot_status_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= (string)$form['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="is-full">備考<textarea name="note"><?= h((string)$form['note']) ?></textarea></label>
    <div class="form-actions is-full">
      <button type="submit"><?= $form['id'] !== '' ? '変更内容を更新' : '変更を登録' ?></button>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slots.php')) ?>">一覧へ戻る</a>
    </div>
  </form>
</section>

<section class="admin-card">
  <h2>登録済みの例外設定</h2>
  <table class="admin-table">
    <thead><tr><th>対象日</th><th>種別</th><th>変更内容</th><th>状態</th><th>操作</th></tr></thead>
    <tbody>
      <?php if ($exceptions === []): ?>
        <tr><td colspan="5">例外設定はまだありません。</td></tr>
      <?php endif; ?>
      <?php foreach ($exceptions as $exception): ?>
        <tr>
          <td><?= h(format_date_jp((string)$exception['target_date'])) ?></td>
          <td><?= h(exception_type_label((string)$exception['exception_type'])) ?></td>
          <td>
            <?= h(((string)$exception['new_start_time'] !== '' ? substr((string)$exception['new_start_time'], 0, 5) . ' - ' : '') . ((string)$exception['new_end_time'] !== '' ? substr((string)$exception['new_end_time'], 0, 5) : '')) ?>
            <?php if (!empty($exception['substitute_instructor_name'])): ?><br>代行: <?= h((string)$exception['substitute_instructor_name']) ?><?php endif; ?>
            <?php if (!empty($exception['new_capacity'])): ?><br>定員: <?= h((string)$exception['new_capacity']) ?><?php endif; ?>
          </td>
          <td><?= h(status_label((string)$exception['status'])) ?></td>
          <td class="actions">
            <a href="<?= h(base_path('/admin/slot-exception.php?template_id=' . $templateId . '&id=' . (int)$exception['id'])) ?>">編集</a>
            <form method="post" class="inline-form">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="template_id" value="<?= h((string)$templateId) ?>">
              <input type="hidden" name="delete_exception_id" value="<?= h((string)$exception['id']) ?>">
              <button type="submit" class="button-link button-link--danger">削除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_admin_footer(); ?>
