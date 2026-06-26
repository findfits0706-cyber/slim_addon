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

if (isset($_GET['download']) && $_GET['download'] === 'template') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="trial_schedule_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['genre', 'lesson_name', 'date', 'start_time', 'end_time', 'instructor_name', 'location_name', 'booth_name', 'equipment_name', 'capacity', 'status', 'description']);
    fputcsv($out, ['pilates', 'マシンピラティス体験', '2026-07-01', '10:00', '10:50', '', '', '', '', '1', 'open', '']);
    fclose($out);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'slots') {
    $week = valid_date_string((string)($_GET['week'] ?? '')) ? (string)$_GET['week'] : (new DateTimeImmutable('today'))->format('Y-m-d');
    $weekStart = week_start_monday($week);
    try {
        $slots = admin_slot_instances_between($weekStart->format('Y-m-d'), $weekStart->modify('+6 days')->format('Y-m-d'));
    } catch (Throwable $e) {
        $slots = [];
        $exportError = db_error_message($e);
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="trial_schedule_' . $weekStart->format('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (isset($exportError)) {
        fputcsv($out, ['error']);
        fputcsv($out, [csv_safe_value($exportError)]);
        fclose($out);
        exit;
    }
    fputcsv($out, ['genre', 'lesson_name', 'date', 'start_time', 'end_time', 'instructor_name', 'capacity', 'status', 'booked_count', 'description']);
    foreach ($slots as $slot) {
        fputcsv($out, [
            csv_safe_value((string)$slot['genre']),
            csv_safe_value((string)$slot['lesson_name']),
            csv_safe_value((string)$slot['booking_date']),
            csv_safe_value(substr((string)$slot['start_time'], 0, 5)),
            csv_safe_value(substr((string)$slot['end_time'], 0, 5)),
            csv_safe_value((string)$slot['instructor_name']),
            csv_safe_value((string)$slot['capacity']),
            csv_safe_value((string)$slot['status']),
            csv_safe_value((string)$slot['booked_count']),
            csv_safe_value((string)$slot['description']),
        ]);
    }
    fclose($out);
    exit;
}

$previewRows = [];
$validCandidates = [];
$conflicts = [];
$errors = [];
$action = (string)($_POST['import_action'] ?? '');
$previewToken = (string)($_POST['preview_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('不正な送信です。');
    }

    if ($action === 'preview') {
        if (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file((string)$_FILES['csv_file']['tmp_name'])) {
            $errors[] = 'CSVファイルを選択してください。';
        } elseif ((int)($_FILES['csv_file']['size'] ?? 0) > 1024 * 1024) {
            $errors[] = 'CSVファイルは1MB以内にしてください。';
        } else {
            $handle = fopen((string)$_FILES['csv_file']['tmp_name'], 'r');
            if ($handle === false) {
                $errors[] = 'CSVファイルを読み込めませんでした。';
            } else {
                $headers = fgetcsv($handle);
                $headers = is_array($headers) ? array_map('trim', $headers) : [];
                $required = ['genre', 'lesson_name', 'date', 'start_time', 'end_time', 'capacity', 'status'];
                foreach ($required as $requiredHeader) {
                    if (!in_array($requiredHeader, $headers, true)) {
                        $errors[] = '必須列がありません: ' . $requiredHeader;
                    }
                }

                $rowNumber = 1;
                while ($errors === [] && ($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;
                    if ($rowNumber > 251) {
                        $errors[] = 'CSVはヘッダーを除き250行以内にしてください。';
                        break;
                    }
                    if (count(array_filter($row, static fn($value): bool => trim((string)$value) !== '')) === 0) {
                        continue;
                    }
                    $data = [];
                    foreach ($headers as $index => $header) {
                        $value = trim((string)($row[$index] ?? ''));
                        $checkValue = ltrim($value, " \t\r\n");
                        if ($checkValue !== '' && preg_match('/^[=+\-@]/u', $checkValue) === 1) {
                            $data['_error'] = '数式として解釈される恐れのある値があります。';
                        }
                        $data[$header] = $value;
                    }

                    $rowErrors = [];
                    if (!array_key_exists((string)($data['genre'] ?? ''), genre_options())) {
                        $rowErrors[] = '種別が不正です。';
                    }
                    if ((string)($data['lesson_name'] ?? '') === '') {
                        $rowErrors[] = '枠名が未入力です。';
                    }
                    if (!valid_date_string((string)($data['date'] ?? ''))) {
                        $rowErrors[] = '日付が不正です。';
                    }
                    if (!valid_time_string((string)($data['start_time'] ?? '')) || !valid_time_string((string)($data['end_time'] ?? ''))) {
                        $rowErrors[] = '時刻が不正です。';
                    } elseif (time_to_minutes((string)$data['start_time']) >= time_to_minutes((string)$data['end_time'])) {
                        $rowErrors[] = '終了時刻は開始時刻より後にしてください。';
                    }
                    if (!ctype_digit((string)($data['capacity'] ?? '')) || (int)$data['capacity'] < 1 || (int)$data['capacity'] > 99) {
                        $rowErrors[] = '定員が不正です。';
                    }
                    if (!array_key_exists((string)($data['status'] ?? ''), slot_status_options())) {
                        $rowErrors[] = '公開状態が不正です。';
                    }
                    if (isset($data['_error'])) {
                        $rowErrors[] = (string)$data['_error'];
                    }

                    $candidate = [
                        'date' => (string)($data['date'] ?? ''),
                        'start_time' => (string)($data['start_time'] ?? ''),
                        'end_time' => (string)($data['end_time'] ?? ''),
                        'genre' => (string)($data['genre'] ?? ''),
                        'lesson_name' => (string)($data['lesson_name'] ?? ''),
                        'instructor_name' => (string)($data['instructor_name'] ?? ''),
                        'location_name' => (string)($data['location_name'] ?? ''),
                        'booth_name' => (string)($data['booth_name'] ?? ''),
                        'equipment_name' => (string)($data['equipment_name'] ?? ''),
                        'description' => (string)($data['description'] ?? ''),
                        'status' => (string)($data['status'] ?? 'open'),
                        'capacity' => (int)($data['capacity'] ?? 1),
                        'kind' => (string)($data['genre'] ?? ''),
                    ];
                    if ($rowErrors === []) {
                        $validCandidates[] = $candidate;
                    }
                    $previewRows[] = [
                        'row' => $rowNumber,
                        'candidate' => $candidate,
                        'errors' => $rowErrors,
                    ];
                }
                fclose($handle);

                if ($validCandidates !== []) {
                    $conflicts = schedule_conflicts($validCandidates);
                }
                $previewToken = bin2hex(random_bytes(16));
                $_SESSION['schedule_import_candidates'][$previewToken] = $validCandidates;
            }
        }
    }

    if ($action === 'save') {
        $validCandidates = $previewToken !== '' && is_array($_SESSION['schedule_import_candidates'][$previewToken] ?? null)
            ? $_SESSION['schedule_import_candidates'][$previewToken]
            : [];
        $conflicts = schedule_conflicts($validCandidates);
        $hasSystemConflict = conflicts_have_system_error($conflicts);
        $blocking = array_values(array_filter($conflicts, static fn(array $conflict): bool => !empty($conflict['blocking'])));
        if ($validCandidates === []) {
            $errors[] = '登録できる行がありません。';
        } elseif ($hasSystemConflict) {
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
            try {
                $pdo = db();
                $pdo->beginTransaction();
                foreach ($validCandidates as $candidate) {
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
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = db_error_message($e);
            }
            if ($errors === []) {
                unset($_SESSION['schedule_import_candidates'][$previewToken]);
                audit_log('trial_slot_template', '', 'schedule_csv_import', null, ['created' => $created, 'skipped' => $skipped]);
                flash_set('ok', 'CSVから' . $created . '件登録しました。' . ($skipped > 0 ? ' ' . $skipped . '件を除外しました。' : ''));
                redirect(base_path('/admin/slots.php'));
            }
        }
    }
}

render_admin_header('CSV取込');
?>
<section class="admin-card">
  <div class="section-row">
    <div>
      <h2>CSV取込</h2>
      <p class="admin-note">文字コードはUTF-8を推奨します。登録前にプレビューと重複確認を行います。</p>
    </div>
    <a class="button-link button-link--muted" href="<?= h(base_path('/admin/schedule-import.php?download=template')) ?>">CSVテンプレートをダウンロード</a>
  </div>
  <?php if ($errors !== []): ?><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="import_action" value="preview">
    <label class="is-full">CSVファイル<input type="file" name="csv_file" accept=".csv,text/csv" required></label>
    <div class="form-actions is-full">
      <button type="submit">取込前プレビュー</button>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slots.php')) ?>">戻る</a>
    </div>
  </form>
</section>

<?php if ($previewRows !== [] || $conflicts !== []): ?>
<section class="admin-card">
  <div class="section-row">
    <h2>プレビュー</h2>
    <div class="admin-note">正常 <?= h((string)count($validCandidates)) ?> 件 / 全 <?= h((string)count($previewRows)) ?> 行 / 競合 <?= h((string)count($conflicts)) ?> 件</div>
  </div>
  <?php if ($conflicts !== []): ?>
    <div class="error"><ul><?php foreach ($conflicts as $conflict): ?><li><?= h($conflict['date'] . ' ' . $conflict['time'] . ' / ' . $conflict['message']) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <table class="admin-table">
    <thead><tr><th>行</th><th>日付</th><th>時間</th><th>種別</th><th>枠名</th><th>状態</th><th>結果</th></tr></thead>
    <tbody>
      <?php foreach ($previewRows as $row): ?>
        <?php $candidate = $row['candidate']; ?>
        <tr>
          <td><?= h((string)$row['row']) ?></td>
          <td><?= h((string)$candidate['date']) ?></td>
          <td><?= h((string)$candidate['start_time'] . ' - ' . (string)$candidate['end_time']) ?></td>
          <td><?= h((string)$candidate['genre']) ?></td>
          <td><?= h((string)$candidate['lesson_name']) ?></td>
          <td><?= h((string)$candidate['status']) ?></td>
          <td><?= $row['errors'] === [] ? '登録可能' : h(implode(' / ', $row['errors'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($validCandidates !== []): ?>
    <form method="post" class="form-actions" style="margin-top:18px">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="import_action" value="save">
      <input type="hidden" name="preview_token" value="<?= h($previewToken) ?>">
      <?php $hasSystemConflict = conflicts_have_system_error($conflicts); ?>
      <?php if ($conflicts !== [] && !$hasSystemConflict): ?><label class="check-inline"><input type="checkbox" name="skip_conflicts" value="1" required> 重複している行を除外して登録する</label><?php endif; ?>
      <button type="submit" <?= $hasSystemConflict ? 'disabled' : '' ?>>正常行を登録する</button>
    </form>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php render_admin_footer(); ?>
