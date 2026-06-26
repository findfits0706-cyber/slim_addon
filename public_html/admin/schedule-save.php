<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/audit.php';
require_once __DIR__ . '/../app/trial-schedule.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_path('/admin/slots.php'));
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('不正な送信です。ページを再読み込みして再度お試しください。');
}

$form = $_POST;
$returnWeek = valid_date_string((string)($form['return_week'] ?? ''))
    ? (string)$form['return_week']
    : (new DateTimeImmutable('today'))->format('Y-m-d');
$built = schedule_candidates_from_input($form);
$conflicts = $built['errors'] === [] ? schedule_conflicts($built['candidates']) : [];
$blockingConflicts = array_values(array_filter($conflicts, static fn(array $conflict): bool => !empty($conflict['blocking'])));
$hasSystemConflict = conflicts_have_system_error($conflicts);

if ((string)($form['schedule_action'] ?? 'preview') === 'save') {
    try {
        $result = schedule_save_from_input($form);
        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $error) {
                flash_set('error', $error);
            }
            redirect(base_path('/admin/slots.php?week=' . rawurlencode($returnWeek)));
        }

        audit_log('trial_slot_template', '', 'schedule_create', null, [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'repeat_mode' => (string)($form['repeat_mode'] ?? ''),
        ]);
        $message = $result['created'] . '件の枠を作成しました。';
        if ((int)$result['skipped'] > 0) {
            $message .= ' ' . $result['skipped'] . '件を除外しました。';
        }
        flash_set('ok', $message);
        redirect(base_path('/admin/slots.php?week=' . rawurlencode($returnWeek)));
    } catch (Throwable $e) {
        flash_set('error', db_error_message($e));
        redirect(base_path('/admin/slots.php?week=' . rawurlencode($returnWeek)));
    }
}

function hidden_inputs(array $data, string $prefix = ''): void
{
    foreach ($data as $key => $value) {
        if ($key === 'csrf_token' || $key === 'schedule_action') {
            continue;
        }
        $name = $prefix === '' ? (string)$key : $prefix . '[' . (string)$key . ']';
        if (is_array($value)) {
            hidden_inputs($value, $name);
            continue;
        }
        echo '<input type="hidden" name="' . h($name) . '" value="' . h((string)$value) . '">';
    }
}

render_admin_header('作成前プレビュー');
?>
<section class="admin-card">
  <div class="section-row">
    <div>
      <h2>作成前プレビュー</h2>
      <p class="admin-note">登録前に作成件数、日付、時刻、競合を確認してください。</p>
    </div>
    <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slots.php?week=' . rawurlencode($returnWeek))) ?>">戻る</a>
  </div>

  <?php if ($built['errors'] !== []): ?>
    <ul class="error-list">
      <?php foreach ($built['errors'] as $error): ?>
        <li><?= h($error) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div class="stats-grid stats-grid--compact">
      <div class="stat-box"><span class="stat-label">作成予定</span><strong><?= h((string)count($built['candidates'])) ?></strong></div>
      <div class="stat-box"><span class="stat-label">競合</span><strong><?= h((string)count($blockingConflicts)) ?></strong></div>
    </div>

    <?php if ($conflicts !== []): ?>
      <div class="error" style="margin-top:18px">
        <strong>競合があります。</strong>
        <ul>
          <?php foreach ($conflicts as $conflict): ?>
            <li><?= h($conflict['date'] . ' ' . $conflict['time'] . ' / ' . $conflict['message']) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <table class="admin-table">
      <thead>
        <tr><th>開催日</th><th>曜日</th><th>時間</th><th>メニュー</th><th>枠名</th><th>担当</th><th>定員</th><th>状態</th></tr>
      </thead>
      <tbody>
        <?php foreach ($built['candidates'] as $candidate): ?>
          <?php $dt = new DateTimeImmutable((string)$candidate['date']); ?>
          <tr>
            <td><?= h(format_date_jp((string)$candidate['date'])) ?></td>
            <td><?= h(weekday_label((int)$dt->format('w'))) ?></td>
            <td><?= h(format_time_range((string)$candidate['start_time'], (string)$candidate['end_time'])) ?></td>
            <td><?= h(genre_label((string)$candidate['genre'])) ?></td>
            <td><?= h((string)$candidate['lesson_name']) ?></td>
            <td><?= h((string)$candidate['instructor_name']) ?></td>
            <td><?= h((string)$candidate['capacity']) ?></td>
            <td><?= h(status_label((string)$candidate['status'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post" class="form-actions" style="margin-top:18px">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="schedule_action" value="save">
      <?php hidden_inputs($form); ?>
      <?php if ($blockingConflicts !== [] && !$hasSystemConflict): ?>
        <label class="check-inline"><input type="checkbox" name="skip_conflicts" value="1" required> 競合している日を除外して登録する</label>
      <?php endif; ?>
      <button type="submit" <?= $hasSystemConflict ? 'disabled' : '' ?>>この内容で登録する</button>
    </form>
  <?php endif; ?>
</section>
<?php render_admin_footer(); ?>
