<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/mail.php';
require_once __DIR__ . '/../app/trial.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_path('/trial/'));
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    echo '不正な送信です。ページを再読み込みして、もう一度お試しください。';
    exit;
}

[$data, $errors] = validate_booking_input($_POST);
$slot = $data['slot_id'] !== '' ? find_slot_instance($data['slot_id']) : null;

if ($slot === null) {
    $errors[] = '選択された体験枠が見つからないか、現在は受付停止中です。';
} elseif ($slot['genre'] !== $data['genre']) {
    $errors[] = '選択した体験内容と時間枠が一致していません。';
}

if ($errors === []) {
    try {
        $pdo = db();
        $decoded = decode_slot_instance_id($data['slot_id']);
        if ($decoded === null) {
            throw new RuntimeException('Invalid slot_id.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM trial_slot_templates WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $decoded['template_id']]);
        $template = $stmt->fetch();
        if (!$template) {
            throw new RuntimeException('Selected template not found.');
        }

        $stmt = $pdo->prepare('SELECT * FROM trial_slot_exceptions WHERE template_id = :template_id AND target_date = :target_date ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([
            'template_id' => $decoded['template_id'],
            'target_date' => $decoded['booking_date'],
        ]);
        $exception = $stmt->fetch() ?: null;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM trial_bookings WHERE template_id = :template_id AND booking_date = :booking_date AND status <> 'cancelled'");
        $stmt->execute([
            'template_id' => $decoded['template_id'],
            'booking_date' => $decoded['booking_date'],
        ]);
        $bookingCount = (int)$stmt->fetchColumn();

        $lockedSlot = build_slot_instance($template, $decoded['booking_date'], $exception, $bookingCount);
        if ($lockedSlot === null || $lockedSlot['status'] !== 'open') {
            throw new RuntimeException('選択された体験枠は現在受付停止中です。別の日時をお選びください。');
        }
        if ($lockedSlot['genre'] !== $data['genre']) {
            throw new RuntimeException('選択した体験内容と時間枠が一致していません。日時を選び直してください。');
        }
        if ($lockedSlot['full']) {
            throw new RuntimeException('選択された体験枠は満席です。別の日時をお選びください。');
        }

        $stmt = $pdo->prepare(
            "SELECT id FROM trial_bookings
             WHERE genre = :genre
               AND status <> 'cancelled'
               AND (email = :email OR phone = :phone)
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([
            'genre' => $data['genre'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);

        if ($stmt->fetchColumn() !== false) {
            throw new RuntimeException('同じ体験内容で受付中のお申し込みがあります。変更をご希望の場合はお問い合わせください。');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO trial_bookings (
                template_id, booking_date, start_time, end_time, genre, lesson_name, instructor_name,
                customer_name, customer_kana, phone, email, age, contact_method, experience,
                trial_history, concern, customer_note, status
            ) VALUES (
                :template_id, :booking_date, :start_time, :end_time, :genre, :lesson_name, :instructor_name,
                :customer_name, :customer_kana, :phone, :email, :age, :contact_method, :experience,
                :trial_history, :concern, :customer_note, 'new'
            )"
        );
        $stmt->execute([
            'template_id' => $lockedSlot['template_id'],
            'booking_date' => $lockedSlot['booking_date'],
            'start_time' => $lockedSlot['start_time'],
            'end_time' => $lockedSlot['end_time'],
            'genre' => $lockedSlot['genre'],
            'lesson_name' => $lockedSlot['lesson_name'],
            'instructor_name' => $lockedSlot['instructor_name'],
            'customer_name' => $data['customer_name'],
            'customer_kana' => $data['customer_kana'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'age' => $data['age'] !== '' ? (int)$data['age'] : null,
            'contact_method' => $data['contact_method'],
            'experience' => $data['experience'] !== '' ? $data['experience'] : null,
            'trial_history' => $data['trial_history'],
            'concern' => $data['concern'] !== '' ? $data['concern'] : null,
            'customer_note' => null,
        ]);

        $bookingId = (int)$pdo->lastInsertId();
        $pdo->commit();

        $mailPayload = [
            'id' => $bookingId,
            'customer_name' => $data['customer_name'],
            'customer_kana' => $data['customer_kana'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'age' => $data['age'] !== '' ? $data['age'] : '未入力',
            'contact_method_label' => contact_method_options()[$data['contact_method']],
            'genre_label' => $lockedSlot['genre_label'],
            'booking_date_label' => $lockedSlot['booking_date_label'],
            'booking_time_label' => $lockedSlot['booking_time_label'],
            'instructor_name' => $lockedSlot['instructor_name'],
            'experience' => $data['experience'] !== '' ? $data['experience'] : '未入力',
            'trial_history' => $data['trial_history'],
            'medical_history_summary' => medical_history_summary($data['medical_history_checks']),
            'medical_history_note' => $data['medical_history_note'] !== '' ? $data['medical_history_note'] : '未入力',
            'concern' => $data['concern'] !== '' ? $data['concern'] : '未入力',
        ];

        send_customer_mail($mailPayload);
        send_admin_mail($mailPayload);

        $_SESSION['trial_booking_completed'] = true;
        $_SESSION['trial_booking_summary'] = [
            'genre_label' => $lockedSlot['genre_label'],
            'booking_date_label' => $lockedSlot['booking_date_label'],
            'booking_time_label' => $lockedSlot['booking_time_label'],
            'instructor_name' => $lockedSlot['instructor_name'],
        ];
        redirect(base_path('/trial/thanks.php'));
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Trial booking failed: ' . $e->getMessage());

        $publicMessages = [
            '選択された体験枠は現在受付停止中です。別の日時をお選びください。',
            '選択した体験内容と時間枠が一致していません。日時を選び直してください。',
            '選択された体験枠は満席です。別の日時をお選びください。',
            '同じ体験内容で受付中のお申し込みがあります。変更をご希望の場合はお問い合わせください。',
        ];

        $errors[] = in_array($e->getMessage(), $publicMessages, true) || APP_DEBUG
            ? $e->getMessage()
            : 'お申し込み処理中にエラーが発生しました。時間をおいて再度お試しください。';
    }
}

http_response_code(422);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>入力内容をご確認ください | Find Pilates</title>
  <link rel="stylesheet" href="<?= h(asset_url('/assets/css/trial.css')) ?>">
</head>
<body>
  <main class="trial-page">
    <div class="trial-container">
      <section class="trial-card">
        <p class="section-label">ERROR</p>
        <h1 class="trial-title">入力内容をご確認ください</h1>
        <ul class="error-list">
          <?php foreach ($errors as $error): ?>
            <li><?= h($error) ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="trial-lead">お手数ですが、前の画面に戻って入力内容をご確認のうえ、もう一度お申し込みください。</p>
        <p><a class="trial-simple-button" href="<?= h(base_path('/trial/')) ?>">体験申込ページに戻る</a></p>
      </section>
    </div>
  </main>
</body>
</html>
