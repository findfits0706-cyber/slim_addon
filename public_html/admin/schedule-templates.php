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

$tablesReady = db_table_exists_for_schedule('trial_schedule_templates') && db_table_exists_for_schedule('trial_schedule_template_items');
$errors = [];
$week = valid_date_string((string)($_GET['week'] ?? $_POST['week'] ?? '')) ? (string)($_GET['week'] ?? $_POST['week']) : (new DateTimeImmutable('today'))->format('Y-m-d');
$weekStart = week_start_monday($week);
$action = (string)($_POST['template_action'] ?? '');
$previewCandidates = [];
$conflicts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('不正な送信です。');
    }
    if (!$tablesReady) {
        $errors[] = 'テンプレート用テーブルがありません。マイグレーションを反映してください。';
    } elseif ($action === 'save_week') {
        $templateName = trim((string)($_POST['template_name'] ?? ''));
        if ($templateName === '') {
            $errors[] = 'テンプレート名を入力してください。';
        } else {
            $slots = admin_slot_instances_between($weekStart->format('Y-m-d'), $weekStart->modify('+6 days')->format('Y-m-d'));
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO trial_schedule_templates (template_name, description) VALUES (:template_name, :description)');
                $stmt->execute([
                    'template_name' => $templateName,
                    'description' => '管理画面から保存: ' . $weekStart->format('Y-m-d'),
                ]);
                $templateId = (int)$pdo->lastInsertId();
                $itemStmt = $pdo->prepare(
                    'INSERT INTO trial_schedule_template_items (
                        schedule_template_id, weekday, genre, lesson_name, instructor_name,
                        start_time, end_time, capacity, status, description, sort_order
                    ) VALUES (
                        :schedule_template_id, :weekday, :genre, :lesson_name, :instructor_name,
                        :start_time, :end_time, :capacity, :status, :description, :sort_order
                    )'
                );
                $sort = 10;
                foreach ($slots as $slot) {
                    $itemStmt->execute([
                        'schedule_template_id' => $templateId,
                        'weekday' => (int)(new DateTimeImmutable((string)$slot['booking_date']))->format('w'),
                        'genre' => (string)$slot['genre'],
                        'lesson_name' => (string)$slot['lesson_name'],
                        'instructor_name' => (string)$slot['instructor_name'],
                        'start_time' => (string)$slot['start_time'],
                        'end_time' => (string)$slot['end_time'],
                        'capacity' => (int)$slot['capacity'],
                        'status' => (string)$slot['status'],
                        'description' => (string)$slot['description'],
                        'sort_order' => $sort,
                    ]);
                    $sort += 10;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            audit_log('trial_schedule_template', (string)$templateId, 'schedule_template_save', null, ['week' => $weekStart->format('Y-m-d'), 'items' => count($slots)]);
            flash_set('ok', 'テンプレートを保存しました。');
            redirect(base_path('/admin/schedule-templates.php?week=' . rawurlencode($weekStart->format('Y-m-d'))));
        }
    } elseif (in_array($action, ['preview_apply', 'save_apply'], true)) {
        $templateId = (int)($_POST['schedule_template_id'] ?? 0);
        $targetWeek = week_start_monday(valid_date_string((string)($_POST['target_week'] ?? '')) ? (string)$_POST['target_week'] : $weekStart->format('Y-m-d'));
        $stmt = db()->prepare('SELECT * FROM trial_schedule_template_items WHERE schedule_template_id = :id ORDER BY weekday ASC, start_time ASC, sort_order ASC');
        $stmt->execute(['id' => $templateId]);
        $items = $stmt->fetchAll();
        foreach ($items as $item) {
            $weekday = (int)$item['weekday'];
            $offset = ($weekday + 6) % 7;
            $previewCandidates[] = [
                'date' => $targetWeek->modify('+' . $offset . ' days')->format('Y-m-d'),
                'start_time' => substr((string)$item['start_time'], 0, 5),
                'end_time' => substr((string)$item['end_time'], 0, 5),
                'genre' => (string)$item['genre'],
                'lesson_name' => (string)$item['lesson_name'],
                'instructor_name' => (string)$item['instructor_name'],
                'location_name' => '',
                'booth_name' => '',
                'equipment_name' => '',
                'description' => (string)$item['description'],
                'status' => (string)$item['status'],
                'capacity' => (int)$item['capacity'],
                'kind' => (string)$item['genre'],
            ];
        }
        $conflicts = schedule_conflicts($previewCandidates);
        if ($action === 'save_apply') {
            $blocking = array_values(array_filter($conflicts, static fn(array $conflict): bool => !empty($conflict['blocking'])));
            if (conflicts_have_system_error($conflicts)) {
                $errors[] = (string)($conflicts[0]['message'] ?? '競合確認に失敗しました。');
            } elseif ($blocking !== [] && empty($_POST['skip_conflicts'])) {
                $errors[] = '重複があります。除外して登録する場合はチェックを入れてください。';
            } else {
                $conflictKeys = [];
                foreach ($blocking as $conflict) {
                    $conflictKeys[$conflict['date'] . '|' . $conflict['time']] = true;
                }
                $created = 0;
                $skipped = 0;
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    foreach ($previewCandidates as $candidate) {
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
                    throw $e;
                }
                audit_log('trial_schedule_template', (string)$templateId, 'schedule_template_apply', null, ['created' => $created, 'skipped' => $skipped]);
                flash_set('ok', 'テンプレートから' . $created . '件作成しました。' . ($skipped > 0 ? ' ' . $skipped . '件を除外しました。' : ''));
                redirect(base_path('/admin/slots.php?week=' . rawurlencode($targetWeek->format('Y-m-d'))));
            }
        }
    }
}

$templates = [];
if ($tablesReady) {
    $templates = db()->query('SELECT * FROM trial_schedule_templates WHERE is_active = 1 ORDER BY updated_at DESC, id DESC')->fetchAll();
}

render_admin_header('スケジュールテンプレート');
?>
<section class="admin-card">
  <h2>スケジュールテンプレート</h2>
  <p class="admin-note">予約者情報を含めず、週間の枠構成だけを保存・適用します。</p>
  <?php if (!$tablesReady): ?><p class="error">DBマイグレーション未反映です。`database/migrations/20260623_trial_schedule_upgrade.sql` をMySQLに適用してください。</p><?php endif; ?>
  <?php if ($errors !== []): ?><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" class="admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="template_action" value="save_week">
    <input type="hidden" name="week" value="<?= h($weekStart->format('Y-m-d')) ?>">
    <label>保存する週<input type="date" value="<?= h($weekStart->format('Y-m-d')) ?>" disabled></label>
    <label>テンプレート名<input name="template_name" maxlength="120" placeholder="通常週" <?= !$tablesReady ? 'disabled' : '' ?>></label>
    <div class="form-actions is-full">
      <button type="submit" <?= !$tablesReady ? 'disabled' : '' ?>>この週をテンプレート保存</button>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slots.php?week=' . rawurlencode($weekStart->format('Y-m-d')))) ?>">戻る</a>
    </div>
  </form>
</section>

<section class="admin-card">
  <h2>テンプレート適用</h2>
  <form method="post" class="admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="template_action" value="preview_apply">
    <label>テンプレート
      <select name="schedule_template_id" <?= !$tablesReady ? 'disabled' : '' ?>>
        <?php foreach ($templates as $template): ?>
          <option value="<?= h((string)$template['id']) ?>"><?= h((string)$template['template_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>適用先の週<input type="date" name="target_week" value="<?= h($weekStart->format('Y-m-d')) ?>" <?= !$tablesReady ? 'disabled' : '' ?>></label>
    <div class="form-actions is-full">
      <button type="submit" <?= !$tablesReady || $templates === [] ? 'disabled' : '' ?>>適用プレビュー</button>
    </div>
  </form>
</section>

<?php if ($previewCandidates !== []): ?>
<section class="admin-card">
  <div class="section-row"><h2>適用プレビュー</h2><span class="admin-note"><?= h((string)count($previewCandidates)) ?>件 / 競合 <?= h((string)count($conflicts)) ?>件</span></div>
  <?php if ($conflicts !== []): ?><div class="error"><ul><?php foreach ($conflicts as $conflict): ?><li><?= h($conflict['date'] . ' ' . $conflict['time'] . ' / ' . $conflict['message']) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <table class="admin-table">
    <thead><tr><th>日付</th><th>時間</th><th>種別</th><th>枠名</th><th>担当</th><th>状態</th></tr></thead>
    <tbody>
      <?php foreach ($previewCandidates as $candidate): ?>
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
  <form method="post" class="form-actions" style="margin-top:18px">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="template_action" value="save_apply">
    <input type="hidden" name="schedule_template_id" value="<?= h((string)($_POST['schedule_template_id'] ?? '')) ?>">
    <input type="hidden" name="target_week" value="<?= h((string)($_POST['target_week'] ?? $weekStart->format('Y-m-d'))) ?>">
    <?php $hasSystemConflict = conflicts_have_system_error($conflicts); ?>
    <?php if ($conflicts !== [] && !$hasSystemConflict): ?><label class="check-inline"><input type="checkbox" name="skip_conflicts" value="1" required> 重複している枠を除外して登録する</label><?php endif; ?>
    <button type="submit" <?= $hasSystemConflict ? 'disabled' : '' ?>>この内容で適用する</button>
  </form>
</section>
<?php endif; ?>
<?php render_admin_footer(); ?>
