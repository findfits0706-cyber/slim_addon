<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/trial.php';
require_once __DIR__ . '/../app/trial-schedule.php';
require_once __DIR__ . '/../app/schema.php';
require_once __DIR__ . '/../app/audit.php';
require_once __DIR__ . '/../app/flash.php';

require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$defaults = [
    'slot_type' => 'repeat',
    'genre' => 'pilates',
    'lesson_name' => '',
    'instructor_name' => '',
    'location_name' => '',
    'booth_name' => '',
    'equipment_name' => '',
    'weekday' => '1',
    'single_date' => '',
    'start_time' => '10:00',
    'end_time' => '11:00',
    'capacity' => '10',
    'cleanup_minutes' => '0',
    'repeat_start_date' => date('Y-m-d'),
    'repeat_end_date' => date('Y-m-d', strtotime('+90 days')),
    'description' => '',
    'status' => 'open',
    'version' => '0',
];
$form = $defaults;
$errors = [];

if ($editing) {
    $template = fetch_slot_templates($id)[0] ?? null;
    if (!$template) {
        http_response_code(404);
        exit('指定された体験枠が見つかりません。');
    }
    $form = array_merge($form, array_map('strval', $template));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正な送信です。';
    } else {
        $form = preserve_form_data($_POST, $defaults);
        $form = array_map(static fn($value) => is_string($value) ? trim($value) : $value, $form);

        if (!array_key_exists($form['slot_type'], slot_type_options())) {
            $errors[] = '枠種別を選択してください。';
        }
        if (!array_key_exists($form['genre'], genre_options())) {
            $errors[] = 'メニューを選択してください。';
        }
        if ($form['lesson_name'] === '') {
            $errors[] = '枠名を入力してください。';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $form['start_time']) || !preg_match('/^\d{2}:\d{2}$/', $form['end_time'])) {
            $errors[] = '開始時間と終了時間を入力してください。';
        } elseif ($form['start_time'] >= $form['end_time']) {
            $errors[] = '終了時間は開始時間より後にしてください。';
        }
        if (!ctype_digit((string)$form['capacity']) || (int)$form['capacity'] <= 0) {
            $errors[] = '定員は1以上の整数で入力してください。';
        }
        if (!array_key_exists($form['status'], slot_status_options())) {
            $errors[] = '公開状態を選択してください。';
        }
        if (!ctype_digit((string)$form['cleanup_minutes']) || (int)$form['cleanup_minutes'] < 0 || (int)$form['cleanup_minutes'] > 120) {
            $errors[] = '清掃時間は0〜120分で入力してください。';
        }

        if ($form['slot_type'] === 'single') {
            if ($form['single_date'] === '') {
                $errors[] = '単発枠の日付を入力してください。';
            }
        } else {
            if ($form['repeat_start_date'] === '' || $form['repeat_end_date'] === '') {
                $errors[] = '繰り返し枠の開始日と終了日を入力してください。';
            }
            if (!ctype_digit((string)$form['weekday']) || !array_key_exists((int)$form['weekday'], weekday_options())) {
                $errors[] = '曜日を選択してください。';
            }
            if ($form['repeat_start_date'] !== '' && $form['repeat_end_date'] !== '' && $form['repeat_start_date'] > $form['repeat_end_date']) {
                $errors[] = '終了日は開始日以降にしてください。';
            }
        }

        if ($errors === []) {
            $baseCandidate = [
                'start_time' => $form['start_time'],
                'end_time' => $form['end_time'],
                'genre' => $form['genre'],
                'lesson_name' => $form['lesson_name'],
                'instructor_name' => $form['instructor_name'],
                'location_name' => $form['location_name'],
                'booth_name' => $form['booth_name'],
                'equipment_name' => $form['equipment_name'],
                'description' => $form['description'],
                'status' => $form['status'],
                'capacity' => (int)$form['capacity'],
                'cleanup_minutes' => (int)$form['cleanup_minutes'],
                'kind' => $form['genre'],
            ];
            $conflictCandidates = [];
            if ($form['slot_type'] === 'single') {
                $conflictCandidates[] = ['date' => $form['single_date']] + $baseCandidate;
            } else {
                foreach (each_date($form['repeat_start_date'], $form['repeat_end_date']) as $date) {
                    if ((int)(new DateTimeImmutable($date))->format('w') !== (int)$form['weekday']) {
                        continue;
                    }
                    $conflictCandidates[] = ['date' => $date] + $baseCandidate;
                    if (count($conflictCandidates) > 250) {
                        $errors[] = '繰り返し期間が長すぎます。期間を短くして保存してください。';
                        break;
                    }
                }
            }

            $conflicts = $errors === [] ? schedule_conflicts($conflictCandidates, $editing ? $id : 0) : [];
            if (conflicts_have_system_error($conflicts)) {
                $errors[] = (string)($conflicts[0]['message'] ?? '競合確認に失敗しました。');
            } elseif ($conflicts !== []) {
                foreach ($conflicts as $conflict) {
                    $errors[] = $conflict['date'] . ' ' . $conflict['time'] . ' ' . $conflict['message'];
                }
            }
        }

        if ($errors === []) {
            $params = [
                'slot_type' => $form['slot_type'],
                'genre' => $form['genre'],
                'lesson_name' => $form['lesson_name'],
                'instructor_name' => $form['instructor_name'] !== '' ? $form['instructor_name'] : null,
                'weekday' => $form['slot_type'] === 'repeat' ? (int)$form['weekday'] : null,
                'single_date' => $form['slot_type'] === 'single' ? $form['single_date'] : null,
                'start_time' => $form['start_time'] . ':00',
                'end_time' => $form['end_time'] . ':00',
                'capacity' => (int)$form['capacity'],
                'repeat_start_date' => $form['slot_type'] === 'repeat' ? $form['repeat_start_date'] : null,
                'repeat_end_date' => $form['slot_type'] === 'repeat' ? $form['repeat_end_date'] : null,
                'description' => $form['description'] !== '' ? $form['description'] : null,
                'status' => $form['status'],
            ];
            add_optional_slot_template_data($params, [
                'location_name' => $form['location_name'],
                'booth_name' => $form['booth_name'],
                'equipment_name' => $form['equipment_name'],
                'cleanup_minutes' => $form['cleanup_minutes'],
            ]);
            if ($editing) {
                $params['id'] = $id;
                $sets = [];
                foreach (array_keys($params) as $column) {
                    if ($column !== 'id') {
                        $sets[] = '`' . $column . '` = :' . $column;
                    }
                }
                $where = 'id = :id';
                if (db_column_exists_cached('trial_slot_templates', 'version')) {
                    $sets[] = 'version = version + 1';
                    $params['version'] = (int)$form['version'];
                    $where .= ' AND version = :version';
                }
                $stmt = db()->prepare('UPDATE trial_slot_templates SET ' . implode(', ', $sets) . ' WHERE ' . $where);
                $stmt->execute($params);
                if ($stmt->rowCount() !== 1) {
                    $errors[] = 'この枠は別の管理者が更新しました。最新内容を確認してください。';
                } else {
                    audit_log('trial_slot_template', (string)$id, 'slot_update', $template ?? null, $params);
                    flash_set('ok', '体験枠を更新しました。');
                    redirect(base_path('/admin/slots.php'));
                }
            } else {
                insert_slot_template_row(db(), $params);
                $newId = (int)db()->lastInsertId();
                audit_log('trial_slot_template', (string)$newId, 'slot_create', null, $params);
                flash_set('ok', '体験枠を登録しました。');
                redirect(base_path('/admin/slots.php'));
            }
        }
    }
}

render_admin_header($editing ? '体験枠編集' : '体験枠追加');
?>
<section class="admin-card">
  <h1><?= $editing ? '体験枠を編集' : '体験枠を追加' ?></h1>
  <?php if ($errors !== []): ?><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" class="admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label>枠種別
      <select name="slot_type" required>
        <?php foreach (slot_type_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= $form['slot_type'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>メニュー
      <select name="genre" required>
        <?php foreach (genre_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= $form['genre'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="is-full">枠名<input name="lesson_name" value="<?= h($form['lesson_name']) ?>" required></label>
    <label>担当<input name="instructor_name" value="<?= h($form['instructor_name']) ?>"></label>
    <label>開催場所<input name="location_name" value="<?= h($form['location_name']) ?>"></label>
    <label>ブース<input name="booth_name" value="<?= h($form['booth_name']) ?>"></label>
    <label>機器<input name="equipment_name" value="<?= h($form['equipment_name']) ?>"></label>
    <label>曜日
      <select name="weekday">
        <?php foreach (weekday_options() as $value => $label): ?>
          <option value="<?= h((string)$value) ?>" <?= (string)$form['weekday'] === (string)$value ? 'selected' : '' ?>><?= h($label . '曜') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>単発日付<input type="date" name="single_date" value="<?= h((string)$form['single_date']) ?>"></label>
    <label>開始時間<input type="time" name="start_time" value="<?= h(substr((string)$form['start_time'], 0, 5)) ?>" required></label>
    <label>終了時間<input type="time" name="end_time" value="<?= h(substr((string)$form['end_time'], 0, 5)) ?>" required></label>
    <label>定員<input type="number" min="1" name="capacity" value="<?= h((string)$form['capacity']) ?>" required></label>
    <label>清掃時間<input type="number" min="0" max="120" name="cleanup_minutes" value="<?= h((string)$form['cleanup_minutes']) ?>"></label>
    <label>繰り返し開始日<input type="date" name="repeat_start_date" value="<?= h((string)$form['repeat_start_date']) ?>"></label>
    <label>繰り返し終了日<input type="date" name="repeat_end_date" value="<?= h((string)$form['repeat_end_date']) ?>"></label>
    <label>公開状態
      <select name="status" required>
        <?php foreach (slot_status_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= $form['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="is-full">詳細説明<textarea name="description"><?= h((string)$form['description']) ?></textarea></label>
    <?php if (db_column_exists_cached('trial_slot_templates', 'version')): ?>
      <input type="hidden" name="version" value="<?= h((string)($form['version'] ?? 0)) ?>">
    <?php endif; ?>
    <div class="form-actions is-full">
      <button type="submit"><?= $editing ? '更新する' : '登録する' ?></button>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slots.php')) ?>">一覧へ戻る</a>
    </div>
  </form>
</section>
<?php render_admin_footer(); ?>
