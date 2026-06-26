<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/admin-ui.php';
require_once __DIR__ . '/../app/flash.php';
require_once __DIR__ . '/../app/audit.php';
require_once __DIR__ . '/../app/trial-schedule.php';

require_admin();

function booking_column_exists(string $column): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND COLUMN_NAME = :column_name'
        );
        $stmt->execute(['table_name' => 'trial_bookings', 'column_name' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function booking_current_slot_id(array $booking): string
{
    return encode_slot_instance_id((int)$booking['template_id'], (string)$booking['booking_date']);
}

function booking_slot_summary(array $slot): string
{
    $parts = [
        format_date_jp((string)$slot['booking_date']),
        format_time_range((string)$slot['start_time'], (string)$slot['end_time']),
        genre_label((string)$slot['genre']),
        (string)$slot['lesson_name'],
    ];

    $instructor = trim((string)($slot['instructor_name'] ?? ''));
    if ($instructor !== '') {
        $parts[] = '担当: ' . $instructor;
    }

    $capacity = (int)($slot['capacity'] ?? 0);
    if ($capacity > 0) {
        $remaining = (int)($slot['remaining'] ?? 0);
        $parts[] = '残り' . $remaining . '/' . $capacity;
    }

    return implode(' / ', $parts);
}

function booking_candidate_slots(string $date): array
{
    try {
        return admin_slot_instances_between($date, $date);
    } catch (Throwable) {
        return [];
    }
}

function locked_slot_for_booking_change(PDO $pdo, string $slotId, int $currentBookingId): array
{
    $decoded = decode_slot_instance_id($slotId);
    if ($decoded === null) {
        throw new RuntimeException('変更先の体験枠を選択してください。');
    }

    $stmt = $pdo->prepare('SELECT * FROM trial_slot_templates WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $decoded['template_id']]);
    $template = $stmt->fetch();
    if (!$template) {
        throw new RuntimeException('変更先の体験枠が見つかりません。');
    }
    if (db_column_exists_cached('trial_slot_templates', 'archived_at') && !empty($template['archived_at'])) {
        throw new RuntimeException('変更先の体験枠はアーカイブ済みです。別の枠を選択してください。');
    }

    $stmt = $pdo->prepare(
        'SELECT *
           FROM trial_slot_exceptions
          WHERE template_id = :template_id
            AND target_date = :target_date
          ORDER BY id DESC
          LIMIT 1
          FOR UPDATE'
    );
    $stmt->execute([
        'template_id' => $decoded['template_id'],
        'target_date' => $decoded['booking_date'],
    ]);
    $exception = $stmt->fetch() ?: null;

    $stmt = $pdo->prepare(
        "SELECT id
           FROM trial_bookings
          WHERE template_id = :template_id
            AND booking_date = :booking_date
            AND status <> 'cancelled'
            AND id <> :current_booking_id
          FOR UPDATE"
    );
    $stmt->execute([
        'template_id' => $decoded['template_id'],
        'booking_date' => $decoded['booking_date'],
        'current_booking_id' => $currentBookingId,
    ]);
    $bookingCount = count($stmt->fetchAll());

    $slot = build_slot_instance($template, $decoded['booking_date'], $exception, $bookingCount);
    if ($slot === null) {
        throw new RuntimeException('変更先の体験枠が見つかりません。');
    }
    if (slot_blocked_by_closure($slot, trial_closures_for_range($decoded['booking_date'], $decoded['booking_date']))) {
        throw new RuntimeException('変更先の体験枠は休館・停止対象です。別の枠を選択してください。');
    }
    if ((string)$slot['status'] !== 'open') {
        throw new RuntimeException('変更先の体験枠は受付中ではありません。別の枠を選択してください。');
    }
    if (!empty($slot['full'])) {
        throw new RuntimeException('変更先の体験枠は満席です。別の枠を選択してください。');
    }

    return $slot;
}

function validate_admin_booking_input(array $input, bool $hasAssignedStaff): array
{
    $data = [
        'slot_id' => trim((string)($input['slot_id'] ?? '')),
        'customer_name' => trim((string)($input['customer_name'] ?? '')),
        'customer_kana' => trim((string)($input['customer_kana'] ?? '')),
        'phone' => preg_replace('/\D+/', '', (string)($input['phone'] ?? '')) ?? '',
        'email' => trim((string)($input['email'] ?? '')),
        'age' => trim((string)($input['age'] ?? '')),
        'contact_method' => trim((string)($input['contact_method'] ?? '')),
        'experience' => trim((string)($input['experience'] ?? '')),
        'trial_history' => trim((string)($input['trial_history'] ?? '')),
        'concern' => trim((string)($input['concern'] ?? '')),
        'customer_note' => trim((string)($input['customer_note'] ?? '')),
        'status' => trim((string)($input['status'] ?? '')),
        'admin_note' => trim((string)($input['admin_note'] ?? '')),
        'assigned_staff' => $hasAssignedStaff ? trim((string)($input['assigned_staff'] ?? '')) : '',
        'contact_required' => isset($input['contact_required']) ? 1 : 0,
        'mark_contacted' => isset($input['mark_contacted']) ? 1 : 0,
    ];
    $errors = [];

    if ($data['slot_id'] === '' || decode_slot_instance_id($data['slot_id']) === null) {
        $errors[] = '変更先の体験枠を選択してください。';
    }
    if ($data['customer_name'] === '') {
        $errors[] = 'お名前を入力してください。';
    }
    if ($data['customer_kana'] === '') {
        $errors[] = 'フリガナを入力してください。';
    }
    if ($data['phone'] === '') {
        $errors[] = '電話番号を入力してください。';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'メールアドレスを正しく入力してください。';
    }
    if ($data['age'] !== '') {
        if (!ctype_digit($data['age'])) {
            $errors[] = '年齢は数字で入力してください。';
        } elseif ((int)$data['age'] < 0 || (int)$data['age'] > 120) {
            $errors[] = '年齢は0歳から120歳の範囲で入力してください。';
        }
    }
    if (!array_key_exists($data['contact_method'], contact_method_options())) {
        $errors[] = '連絡方法を選択してください。';
    }
    if ($data['trial_history'] === '') {
        $errors[] = '体験歴を選択してください。';
    }
    if (!array_key_exists($data['status'], booking_status_options())) {
        $errors[] = 'ステータスを選択してください。';
    }

    return [$data, $errors];
}

$hasContactRequired = booking_column_exists('contact_required');
$hasContactedAt = booking_column_exists('contacted_at');
$hasAssignedStaff = booking_column_exists('assigned_staff');
$hasCustomerNote = booking_column_exists('customer_note');
$hasVersion = booking_column_exists('version');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM trial_bookings WHERE id = :id');
$stmt->execute(['id' => $id]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    exit('指定された申込が見つかりません。');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正な送信です。';
    } else {
        [$data, $errors] = validate_admin_booking_input($_POST, $hasAssignedStaff);
    }

    if ($errors === []) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM trial_bookings WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $lockedBooking = $stmt->fetch();
            if (!$lockedBooking) {
                throw new RuntimeException('指定された申込が見つかりません。');
            }

            if ($hasVersion) {
                $postedVersion = (int)($_POST['version'] ?? 0);
                if ($postedVersion !== (int)($lockedBooking['version'] ?? 0)) {
                    throw new RuntimeException('この内容は、画面を開いた後に別の管理者が更新しました。最新内容を確認してください。');
                }
            } elseif ((string)($_POST['updated_at'] ?? '') !== (string)$lockedBooking['updated_at']) {
                throw new RuntimeException('この内容は、画面を開いた後に別の管理者が更新しました。最新内容を確認してください。');
            }

            $currentSlotId = booking_current_slot_id($lockedBooking);
            $slotChanged = $data['slot_id'] !== $currentSlotId;
            $targetSlot = null;
            if ($slotChanged) {
                $targetSlot = locked_slot_for_booking_change($pdo, $data['slot_id'], $id);
            }

            $sets = [
                'customer_name = :customer_name',
                'customer_kana = :customer_kana',
                'phone = :phone',
                'email = :email',
                'age = :age',
                'contact_method = :contact_method',
                'experience = :experience',
                'trial_history = :trial_history',
                'concern = :concern',
                'status = :status',
                'admin_note = :admin_note',
            ];
            $params = [
                'customer_name' => $data['customer_name'],
                'customer_kana' => $data['customer_kana'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'age' => $data['age'] !== '' ? (int)$data['age'] : null,
                'contact_method' => $data['contact_method'],
                'experience' => $data['experience'] !== '' ? $data['experience'] : null,
                'trial_history' => $data['trial_history'],
                'concern' => $data['concern'] !== '' ? $data['concern'] : null,
                'status' => $data['status'],
                'admin_note' => $data['admin_note'] !== '' ? $data['admin_note'] : null,
                'id' => $id,
            ];

            if ($hasCustomerNote) {
                $sets[] = 'customer_note = :customer_note';
                $params['customer_note'] = $data['customer_note'] !== '' ? $data['customer_note'] : null;
            }
            if ($hasContactRequired) {
                $sets[] = 'contact_required = :contact_required';
                $params['contact_required'] = $data['contact_required'];
            }
            if ($hasContactedAt && $data['mark_contacted'] === 1) {
                $sets[] = 'contacted_at = NOW()';
                if ($hasContactRequired) {
                    $params['contact_required'] = 0;
                }
            }
            if ($hasAssignedStaff) {
                $sets[] = 'assigned_staff = :assigned_staff';
                $params['assigned_staff'] = $data['assigned_staff'] !== '' ? $data['assigned_staff'] : null;
            }
            if ($targetSlot !== null) {
                $sets[] = 'template_id = :template_id';
                $sets[] = 'booking_date = :booking_date';
                $sets[] = 'start_time = :start_time';
                $sets[] = 'end_time = :end_time';
                $sets[] = 'genre = :genre';
                $sets[] = 'lesson_name = :lesson_name';
                $sets[] = 'instructor_name = :instructor_name';
                $params['template_id'] = (int)$targetSlot['template_id'];
                $params['booking_date'] = (string)$targetSlot['booking_date'];
                $params['start_time'] = (string)$targetSlot['start_time'];
                $params['end_time'] = (string)$targetSlot['end_time'];
                $params['genre'] = (string)$targetSlot['genre'];
                $params['lesson_name'] = (string)$targetSlot['lesson_name'];
                $params['instructor_name'] = (string)$targetSlot['instructor_name'];
            }
            if ($hasVersion) {
                $sets[] = 'version = version + 1';
            }

            $stmt = $pdo->prepare('UPDATE trial_bookings SET ' . implode(', ', $sets) . ' WHERE id = :id');
            $stmt->execute($params);

            $stmt = $pdo->prepare('SELECT * FROM trial_bookings WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $afterBooking = $stmt->fetch() ?: null;

            audit_log('trial_booking', (string)$id, 'booking_update', $lockedBooking, $afterBooking, $slotChanged ? '管理画面で体験者情報と予約枠を更新' : '管理画面で体験者情報を更新');

            $pdo->commit();
            flash_set('ok', $slotChanged ? '申込情報と予約枠を更新しました。' : '申込情報を更新しました。');
            redirect(base_path('/admin/booking-edit.php?id=' . $id));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}

$stmt = db()->prepare('SELECT * FROM trial_bookings WHERE id = :id');
$stmt->execute(['id' => $id]);
$booking = $stmt->fetch();

$sameSlotBookings = [];
try {
    $stmt = db()->prepare(
        "SELECT id, customer_name, customer_kana, status, phone, email
           FROM trial_bookings
          WHERE template_id = :template_id
            AND booking_date = :booking_date
            AND start_time = :start_time
            AND end_time = :end_time
          ORDER BY id ASC"
    );
    $stmt->execute([
        'template_id' => (int)$booking['template_id'],
        'booking_date' => (string)$booking['booking_date'],
        'start_time' => (string)$booking['start_time'],
        'end_time' => (string)$booking['end_time'],
    ]);
    $sameSlotBookings = $stmt->fetchAll();
} catch (Throwable) {
    $sameSlotBookings = [];
}

$week = week_start_monday((string)$booking['booking_date'])->format('Y-m-d');
$currentSlotId = booking_current_slot_id($booking);
$slotSearchDate = trim((string)($_GET['slot_date'] ?? ''));
if (!valid_date_string($slotSearchDate)) {
    $slotSearchDate = (string)$booking['booking_date'];
}
$candidateSlots = booking_candidate_slots($slotSearchDate);
$currentSlotOption = [
    'slot_id' => $currentSlotId,
    'template_id' => (int)$booking['template_id'],
    'booking_date' => (string)$booking['booking_date'],
    'start_time' => (string)$booking['start_time'],
    'end_time' => (string)$booking['end_time'],
    'genre' => (string)$booking['genre'],
    'lesson_name' => (string)$booking['lesson_name'],
    'instructor_name' => (string)$booking['instructor_name'],
    'remaining' => 0,
    'capacity' => 0,
    'status' => 'open',
    'full' => false,
];

render_admin_header('体験申込詳細');
?>
<section class="admin-card">
  <div class="section-row">
    <div>
      <h2>体験申込詳細</h2>
      <p class="admin-note">申込ID: <?= h((string)$booking['id']) ?> / 更新日時: <?= h((string)$booking['updated_at']) ?></p>
    </div>
    <div class="actions">
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slots.php?week=' . rawurlencode($week))) ?>">予約枠へ</a>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/slot-edit.php?id=' . (int)$booking['template_id'])) ?>">枠テンプレート編集</a>
      <a class="button-link button-link--muted" href="<?= h(base_path('/admin/bookings.php')) ?>">一覧へ戻る</a>
    </div>
  </div>
  <?php if ($errors !== []): ?><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <div class="detail-grid">
    <div><strong>現在の予約枠</strong><br><?= h(format_date_jp((string)$booking['booking_date']) . ' ' . format_time_range((string)$booking['start_time'], (string)$booking['end_time'])) ?></div>
    <div><strong>メニュー</strong><br><?= h(genre_label((string)$booking['genre'])) ?></div>
    <div><strong>担当</strong><br><?= h((string)$booking['instructor_name']) ?></div>
    <div><strong>ステータス</strong><br><?= h(booking_status_label((string)$booking['status'])) ?></div>
    <?php if ($hasContactRequired): ?><div><strong>要連絡</strong><br><?= !empty($booking['contact_required']) ? '要連絡' : 'なし' ?></div><?php endif; ?>
    <?php if ($hasContactedAt): ?><div><strong>連絡済み日時</strong><br><?= h((string)($booking['contacted_at'] ?? '')) ?></div><?php endif; ?>
  </div>
</section>

<section class="admin-card">
  <div class="section-row">
    <div>
      <h2>体験者・予約内容の編集</h2>
      <p class="admin-note">体験者情報、管理情報、受ける予約枠をまとめて更新できます。</p>
    </div>
    <form method="get" class="inline-date-form">
      <input type="hidden" name="id" value="<?= h((string)$id) ?>">
      <label>変更先候補日
        <input type="date" name="slot_date" value="<?= h($slotSearchDate) ?>">
      </label>
      <button type="submit">候補を表示</button>
    </form>
  </div>

  <form method="post" class="admin-form-grid">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= h((string)$id) ?>">
    <input type="hidden" name="updated_at" value="<?= h((string)$booking['updated_at']) ?>">
    <?php if ($hasVersion): ?><input type="hidden" name="version" value="<?= h((string)($booking['version'] ?? 0)) ?>"><?php endif; ?>

    <label class="is-full">受ける予約枠
      <select name="slot_id" required>
        <option value="<?= h($currentSlotId) ?>">現在の枠から変更しない: <?= h(booking_slot_summary($currentSlotOption)) ?></option>
        <?php foreach ($candidateSlots as $slot): ?>
          <?php
          $slotId = (string)$slot['slot_id'];
          if ($slotId === $currentSlotId) {
              continue;
          }
          $canSelect = (string)$slot['status'] === 'open' && empty($slot['full']);
          ?>
          <option value="<?= h($slotId) ?>" <?= $canSelect ? '' : 'disabled' ?>><?= h(booking_slot_summary($slot) . ($canSelect ? '' : '（選択不可）')) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>氏名
      <input name="customer_name" value="<?= h((string)$booking['customer_name']) ?>" maxlength="100" required>
    </label>
    <label>フリガナ
      <input name="customer_kana" value="<?= h((string)$booking['customer_kana']) ?>" maxlength="100" required>
    </label>
    <label>電話番号
      <input name="phone" value="<?= h((string)$booking['phone']) ?>" maxlength="30" required>
    </label>
    <label>メール
      <input type="email" name="email" value="<?= h((string)$booking['email']) ?>" maxlength="255" required>
    </label>
    <label>年齢
      <input type="number" name="age" value="<?= h((string)($booking['age'] ?? '')) ?>" min="0" max="120">
    </label>
    <label>連絡方法
      <select name="contact_method" required>
        <?php foreach (contact_method_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= (string)$booking['contact_method'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>運動経験
      <select name="experience">
        <?php
        $experienceOptions = ['', '初めて', '少しある', '継続中'];
        $currentExperience = (string)($booking['experience'] ?? '');
        if ($currentExperience !== '' && !in_array($currentExperience, $experienceOptions, true)) {
            $experienceOptions[] = $currentExperience;
        }
        ?>
        <?php foreach ($experienceOptions as $value): ?>
          <option value="<?= h($value) ?>" <?= $currentExperience === $value ? 'selected' : '' ?>><?= h($value === '' ? '選択してください' : $value) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>体験歴
      <select name="trial_history" required>
        <?php
        $trialHistoryOptions = ['初めて', '過去に体験を利用したことがある'];
        $currentTrialHistory = (string)($booking['trial_history'] ?? '');
        if ($currentTrialHistory !== '' && !in_array($currentTrialHistory, $trialHistoryOptions, true)) {
            $trialHistoryOptions[] = $currentTrialHistory;
        }
        ?>
        <option value="">選択してください</option>
        <?php foreach ($trialHistoryOptions as $value): ?>
          <option value="<?= h($value) ?>" <?= $currentTrialHistory === $value ? 'selected' : '' ?>><?= h($value) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>ステータス
      <select name="status" required>
        <?php foreach (booking_status_options() as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= (string)$booking['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($hasAssignedStaff): ?>
      <label>担当スタッフ
        <input name="assigned_staff" value="<?= h((string)($booking['assigned_staff'] ?? '')) ?>" maxlength="100">
      </label>
    <?php endif; ?>
    <?php if ($hasContactRequired): ?>
      <label class="check-inline"><input type="checkbox" name="contact_required" value="1" <?= !empty($booking['contact_required']) ? 'checked' : '' ?>> 要連絡として記録</label>
    <?php endif; ?>
    <?php if ($hasContactedAt): ?>
      <label class="check-inline"><input type="checkbox" name="mark_contacted" value="1"> 連絡済みにする</label>
    <?php endif; ?>
    <label class="is-full">相談内容
      <textarea name="concern" maxlength="2000"><?= h((string)($booking['concern'] ?? '')) ?></textarea>
    </label>
    <?php if ($hasCustomerNote): ?>
      <label class="is-full">お客様備考
        <textarea name="customer_note" maxlength="2000"><?= h((string)($booking['customer_note'] ?? '')) ?></textarea>
      </label>
    <?php endif; ?>
    <label class="is-full">管理メモ
      <textarea name="admin_note"><?= h((string)($booking['admin_note'] ?? '')) ?></textarea>
    </label>
    <div class="form-actions is-full">
      <button type="submit">更新する</button>
    </div>
  </form>
</section>

<section class="admin-card">
  <h2>同じ枠の予約者</h2>
  <table class="admin-table">
    <thead><tr><th>ID</th><th>氏名</th><th>ステータス</th><th>電話</th><th>メール</th><th>操作</th></tr></thead>
    <tbody>
      <?php if ($sameSlotBookings === []): ?>
        <tr><td colspan="6">同じ枠の予約者はありません。</td></tr>
      <?php endif; ?>
      <?php foreach ($sameSlotBookings as $same): ?>
        <tr>
          <td><?= h((string)$same['id']) ?></td>
          <td><?= h((string)$same['customer_name']) ?><br><span class="muted"><?= h((string)$same['customer_kana']) ?></span></td>
          <td><?= h(booking_status_label((string)$same['status'])) ?></td>
          <td><a href="tel:<?= h((string)$same['phone']) ?>"><?= h((string)$same['phone']) ?></a></td>
          <td><a href="mailto:<?= h((string)$same['email']) ?>"><?= h((string)$same['email']) ?></a></td>
          <td><a href="<?= h(base_path('/admin/booking-edit.php?id=' . (int)$same['id'])) ?>">詳細</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_admin_footer(); ?>
