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

$targetWeek = valid_date_string((string)($_GET['target_week'] ?? $_POST['target_week'] ?? ''))
    ? (string)($_GET['target_week'] ?? $_POST['target_week'])
    : (new DateTimeImmutable('today'))->format('Y-m-d');
$targetStart = week_start_monday($targetWeek);
$sourceStart = valid_date_string((string)($_POST['source_week'] ?? ''))
    ? week_start_monday((string)$_POST['source_week'])
    : $targetStart->modify('-7 days');

$genre = trim((string)($_POST['genre'] ?? ''));
$status = trim((string)($_POST['status'] ?? ''));
$instructor = trim((string)($_POST['instructor'] ?? ''));
$includeClosed = isset($_POST['include_closed']);
$action = (string)($_POST['copy_action'] ?? 'form');
$errors = [];
$candidates = [];
$conflicts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('不正な送信です。');
    }

    try {
        $sourceSlots = admin_slot_instances_between(
            $sourceStart->format('Y-m-d'),
            $sourceStart->modify('+6 days')->format('Y-m-d'),
            [
                'genre' => $genre,
                'status' => $status,
                'instructor' => $instructor,
            ]
        );

        foreach ($sourceSlots as $slot) {
            if (!$includeClosed && (string)$slot['status'] !== 'open') {
                continue;
            }
            $sourceDate = new DateTimeImmutable((string)$slot['booking_date']);
            $offsetDays = (int)$sourceStart->diff($sourceDate)->format('%a');
            $targetDate = $targetStart->modify('+' . $offsetDays . ' days')->format('Y-m-d');
            $candidates[] = [
                'date' => $targetDate,
                'start_time' => substr((string)$slot['start_time'], 0, 5),
                'end_time' => substr((string)$slot['end_time'], 0, 5),
                'genre' => (string)$slot['genre'],
                'lesson_name' => (string)$slot['lesson_name'],
                'instructor_name' => (string)$slot['instructor_name'],
                'location_name' => '',
                'booth_name' => '',
                'equipment_name' => '',
                'description' => (string)$slot['description'],
                'status' => (string)$slot['status'],
                'capacity' => (int)$slot['capacity'],
                'kind' => (string)$slot['genre'],
            ];
        }

        $conflicts = schedule_conflicts($candidates);

        if ($action === 'save') {
            $blocking = array_values(array_filter($conflicts, static fn(array $conflict): bool => !empty($conflict['blocking'])));
            if (conflicts_have_system_error($conflicts)) {
                $errors[] = (string)($conflicts[0]['message'] ?? '競合確認に失敗しました。');
            } elseif ($blocking !== [] && empty($_POST['skip_conflicts'])) {
                $errors[] = '重複があります。除外して登録する場合はチェックを入れてください。';
            }

            if ($errors === []) {
                $conflictKeys = [];
                foreach ($blocking as $conflict) {
                    $conflictKeys[$conflict['date'] . '|' . $conflict['time']] = true;
                }

                $created = 0;
                $skipped = 0;
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    foreach ($candidates as $candidate) {
                        $key = $candidate['date'] . '|' . format_time_range((string)$candidate['start_time'], (string)$candidate['end_time']);
                        if (isset($conflictKeys[$key]) && !empty($_POST['skip_conflicts'])) {
                            $skipped++;
                            continue;
                        }
                        insert_single_slot_template($pdo, $candidate);
                        $created++;
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = db_error_message($e);
                }

                if ($errors === []) {
                    audit_log('trial_slot_template', '', 'schedule_week_copy', null, [
                        'source_week' => $sourceStart->format('Y-m-d'),
                        'target_week' => $targetStart->format('Y-m-d'),
                        'created' => $created,
                        'skipped' => $skipped,
                    ]);
                    flash_set('ok', $created . '件をコピーしました。' . ($skipped > 0 ? ' ' . $skipped . '件を除外しました。' : ''));
                    redirect(base_path('/admin/slots.php?week=' . rawurlencode($targetStart->format('Y-m-d'))));
                }
            }
        }
    } catch (Throwable $e) {
        $errors[] = db_error_message($e);
    }
}

render_admin_header('前週コピー');
?>
<section class="admin-card">
  <h2>前週をコピー</h2>
  <p class="admin-note">コピー先の既存予約や枠は上書きしません。登録前に重複を確認します。</p>
  <?php if ($errors !== []): ?><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" class="admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="copy_action" value="preview">
    <label>コピー元の週<input type="date" name="source_week" value="<?= h($sourceStart->format('Y-m-d')) ?>"></label>
    <label>コピー先の週<input type="date" name="target_week" value="<?= h($targetStart->format('Y-m-d')) ?>"></label>
    <label>対象種別
      <select name="genre">
        <option value="">すべて</option>
        <?php foreach (genre_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= $genre === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>公開状態
      <select name="status">
        <option value="">すべて</option>
        <?php foreach (slot_status_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>担当者<input name="instructor" value="<?= h($instructor) ?>"></label>
    <label class="check-inline"><input type="checkbox" name="include_closed" value="1" <?= $includeClosed ? 'checked' : '' ?>> 受付停止を含む</label>
    <div class="form-actions is-full">
      <button type="submit">コピー結果をプレビュー</button>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slots.php?week=' . rawurlencode($targetStart->format('Y-m-d')))) ?>">戻る</a>
    </div>
  </form>
</section>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
<section class="admin-card">
  <div class="section-row">
    <h2>プレビュー</h2>
    <div class="admin-note">作成予定 <?= h((string)count($candidates)) ?> 件 / 競合 <?= h((string)count($conflicts)) ?> 件</div>
  </div>
  <?php if ($conflicts !== []): ?>
    <div class="error"><ul><?php foreach ($conflicts as $conflict): ?><li><?= h($conflict['date'] . ' ' . $conflict['time'] . ' / ' . $conflict['message']) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <table class="admin-table">
    <thead><tr><th>日付</th><th>時間</th><th>種別</th><th>枠名</th><th>担当</th><th>状態</th></tr></thead>
    <tbody>
      <?php if ($candidates === []): ?><tr><td colspan="6">コピー対象はありません。</td></tr><?php endif; ?>
      <?php foreach ($candidates as $candidate): ?>
        <tr>
          <td><?= h(format_date_jp((string)$candidate['date'])) ?></td>
          <td><?= h(format_time_range((string)$candidate['start_time'], (string)$candidate['end_time'])) ?></td>
          <td><?= h(genre_label((string)$candidate['genre'])) ?></td>
          <td><?= h((string)$candidate['lesson_name']) ?></td>
          <td><?= h((string)$candidate['instructor_name']) ?></td>
          <td><?= h(status_label((string)$candidate['status'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($candidates !== []): ?>
    <form method="post" class="form-actions" style="margin-top:18px">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="copy_action" value="save">
      <input type="hidden" name="source_week" value="<?= h($sourceStart->format('Y-m-d')) ?>">
      <input type="hidden" name="target_week" value="<?= h($targetStart->format('Y-m-d')) ?>">
      <input type="hidden" name="genre" value="<?= h($genre) ?>">
      <input type="hidden" name="status" value="<?= h($status) ?>">
      <input type="hidden" name="instructor" value="<?= h($instructor) ?>">
      <?php if ($includeClosed): ?><input type="hidden" name="include_closed" value="1"><?php endif; ?>
      <?php $hasSystemConflict = conflicts_have_system_error($conflicts); ?>
      <?php if ($conflicts !== [] && !$hasSystemConflict): ?><label class="check-inline"><input type="checkbox" name="skip_conflicts" value="1" required> 重複している枠を除外して登録する</label><?php endif; ?>
      <button type="submit" <?= $hasSystemConflict ? 'disabled' : '' ?>>この内容でコピーする</button>
    </form>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php render_admin_footer(); ?>
