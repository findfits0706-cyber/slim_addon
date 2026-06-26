<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/schema.php';

function encode_slot_instance_id(int $templateId, string $date): string
{
    return $templateId . '|' . $date;
}

function decode_slot_instance_id(string $slotId): ?array
{
    $parts = explode('|', $slotId, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$templateId, $date] = $parts;
    if (!ctype_digit($templateId) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }

    return [
        'template_id' => (int)$templateId,
        'booking_date' => $date,
    ];
}

function trial_form_defaults(): array
{
    return [
        'genre' => 'pilates',
        'slot_id' => '',
        'customer_name' => '',
        'customer_kana' => '',
        'phone' => '',
        'email' => '',
        'age' => '',
        'contact_method' => 'either',
        'experience' => '',
        'trial_history' => '',
        'concern' => '',
        'medical_history_checks' => [],
        'medical_history_note' => '',
        'agree' => '',
    ];
}

function medical_history_item_labels(): array
{
    return [
        'exercise_restriction' => '医師による運動制限、または運動に支障のある既往症はありません。',
        'infectious_disease' => '皮膚病・伝染性疾患、その他人へ感染する恐れのある疾病はありません。',
        'fainting_risk' => '現在、てんかん、心疾患、その他、運動中に発作、失神、意識消失または急激な体調悪化を生じるおそれのある疾患・症状はありません。',
        'not_pregnant' => '妊娠中ではありません。',
        'membership_eligibility' => '刺青・タトゥー、反社会的勢力との関係、その他入会資格に抵触する事項はありません。',
        'follow_facility_rules' => '施設利用中はマナー・モラル・スタッフの案内を遵守し、自己の体調管理に責任を持って利用します。',
    ];
}

function medical_history_summary(array $checkedKeys): string
{
    $checkedMap = array_fill_keys($checkedKeys, true);
    $lines = [];

    foreach (medical_history_item_labels() as $key => $label) {
        $status = isset($checkedMap[$key]) ? 'チェックあり' : '未チェック（要確認）';
        $lines[] = '・' . $label . ' : ' . $status;
    }

    return implode("\n", $lines);
}

function validate_booking_input(array $input): array
{
    $data = preserve_form_data($input, trial_form_defaults());
    $errors = [];

    $data['genre'] = trim((string)$data['genre']);
    $data['slot_id'] = trim((string)$data['slot_id']);
    $data['customer_name'] = trim((string)$data['customer_name']);
    $data['customer_kana'] = trim((string)$data['customer_kana']);
    $data['phone'] = preg_replace('/\D+/', '', (string)$data['phone']) ?? '';
    $data['email'] = trim((string)$data['email']);
    $data['age'] = trim((string)$data['age']);
    $data['contact_method'] = trim((string)$data['contact_method']);
    $data['experience'] = trim((string)$data['experience']);
    $data['trial_history'] = trim((string)$data['trial_history']);
    $data['concern'] = trim((string)$data['concern']);

    $medicalHistoryChecks = $data['medical_history_checks'] ?? [];
    if (!is_array($medicalHistoryChecks)) {
        $medicalHistoryChecks = [];
    }
    $allowedMedicalHistoryKeys = array_keys(medical_history_item_labels());
    $data['medical_history_checks'] = array_values(array_intersect($allowedMedicalHistoryKeys, array_map('strval', $medicalHistoryChecks)));
    $data['medical_history_note'] = trim((string)$data['medical_history_note']);
    $data['agree'] = (string)$data['agree'];

    if (!array_key_exists($data['genre'], genre_options())) {
        $errors[] = '体験メニューを選択してください。';
    }
    if ($data['slot_id'] === '' || decode_slot_instance_id($data['slot_id']) === null) {
        $errors[] = '希望日時を選択してください。';
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
    if (array_diff($allowedMedicalHistoryKeys, $data['medical_history_checks']) !== []) {
        $errors[] = '既往歴・入会資格の確認項目をすべてチェックしてください。';
    }
    if ($data['agree'] !== '1') {
        $errors[] = '注意事項への同意が必要です。';
    }

    return [$data, $errors];
}

function fetch_slot_templates(?int $templateId = null): array
{
    $sql = 'SELECT * FROM trial_slot_templates';
    $params = [];
    $conditions = [];
    if ($templateId !== null) {
        $conditions[] = 'id = :id';
        $params['id'] = $templateId;
    }
    if (db_column_exists_cached('trial_slot_templates', 'archived_at')) {
        $conditions[] = 'archived_at IS NULL';
    }
    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY slot_type ASC, genre ASC, start_time ASC, id ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_slot_exceptions(array $templateIds): array
{
    if ($templateIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($templateIds), '?'));
    $stmt = db()->prepare("SELECT * FROM trial_slot_exceptions WHERE template_id IN ($placeholders) ORDER BY target_date ASC, id DESC");
    $stmt->execute(array_values($templateIds));
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int)$row['template_id']][(string)$row['target_date']] = $row;
    }

    return $grouped;
}

function fetch_booking_counts(array $templateIds, string $startDate, string $endDate): array
{
    if ($templateIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($templateIds), '?'));
    $sql = "SELECT template_id, booking_date, COUNT(*) AS booking_count
        FROM trial_bookings
        WHERE template_id IN ($placeholders)
          AND booking_date BETWEEN ? AND ?
          AND status <> 'cancelled'
        GROUP BY template_id, booking_date";

    $params = array_values($templateIds);
    $params[] = $startDate;
    $params[] = $endDate;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = encode_slot_instance_id((int)$row['template_id'], (string)$row['booking_date']);
        $counts[$key] = (int)$row['booking_count'];
    }

    return $counts;
}

function build_slot_instance(array $template, string $date, ?array $exception, int $bookingCount): ?array
{
    if (!slot_date_matches_template($template, $date)) {
        return null;
    }

    $effectiveStatus = $exception['status'] ?? $template['status'];
    if (($exception['exception_type'] ?? null) === 'cancel') {
        return null;
    }

    $startTime = (string)(($exception['new_start_time'] ?? null) ?: $template['start_time']);
    $endTime = (string)(($exception['new_end_time'] ?? null) ?: $template['end_time']);
    $capacity = (int)(($exception['new_capacity'] ?? null) ?: $template['capacity']);
    $instructorName = (string)(($exception['substitute_instructor_name'] ?? null) ?: $template['instructor_name']);

    return [
        'slot_id' => encode_slot_instance_id((int)$template['id'], $date),
        'template_id' => (int)$template['id'],
        'slot_type' => (string)$template['slot_type'],
        'genre' => (string)$template['genre'],
        'genre_label' => genre_label((string)$template['genre']),
        'lesson_name' => (string)$template['lesson_name'],
        'instructor_name' => $instructorName !== '' ? $instructorName : '未定',
        'booking_date' => $date,
        'booking_date_label' => format_date_jp($date),
        'start_time' => $startTime,
        'end_time' => $endTime,
        'booking_time_label' => format_time_range($startTime, $endTime),
        'capacity' => $capacity,
        'booked_count' => $bookingCount,
        'remaining' => max($capacity - $bookingCount, 0),
        'full' => $bookingCount >= $capacity,
        'status' => $effectiveStatus,
        'description' => (string)$template['description'],
        'exception_type' => (string)($exception['exception_type'] ?? ''),
        'location_name' => (string)($template['location_name'] ?? ''),
        'booth_name' => (string)($template['booth_name'] ?? ''),
        'equipment_name' => (string)($template['equipment_name'] ?? ''),
        'cleanup_minutes' => (int)($template['cleanup_minutes'] ?? 0),
    ];
}

function trial_closures_for_range(string $startDate, string $endDate): array
{
    if (!db_table_exists_cached('trial_closures')) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT *
           FROM trial_closures
          WHERE is_active = 1
            AND start_date <= :end_date
            AND end_date >= :start_date'
    );
    $stmt->execute([
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    return $stmt->fetchAll();
}

function slot_blocked_by_closure(array $slot, array $closures): bool
{
    foreach ($closures as $closure) {
        if ((string)$slot['booking_date'] < (string)$closure['start_date'] || (string)$slot['booking_date'] > (string)$closure['end_date']) {
            continue;
        }

        $closureType = (string)$closure['closure_type'];
        $applies = $closureType === 'all'
            || $closureType === (string)$slot['genre']
            || in_array($closureType, ['maintenance', 'cleaning', 'shooting', 'internal'], true);
        if (!$applies) {
            continue;
        }

        if (!empty($closure['start_time']) && !empty($closure['end_time'])) {
            $slotStart = substr((string)$slot['start_time'], 0, 5);
            $slotEnd = substr((string)$slot['end_time'], 0, 5);
            $closureStart = substr((string)$closure['start_time'], 0, 5);
            $closureEnd = substr((string)$closure['end_time'], 0, 5);
            $overlap = $slotStart < $closureEnd && $closureStart < $slotEnd;
            if (!$overlap) {
                continue;
            }
        }

        return true;
    }

    return false;
}

function slot_date_matches_template(array $template, string $date): bool
{
    if ((string)$template['slot_type'] === 'single') {
        return (string)$template['single_date'] === $date;
    }

    if (empty($template['repeat_start_date']) || $template['weekday'] === null || $template['weekday'] === '') {
        return false;
    }

    if ($date < (string)$template['repeat_start_date']) {
        return false;
    }
    if (!empty($template['repeat_end_date']) && $date > (string)$template['repeat_end_date']) {
        return false;
    }

    $weekday = (int)(new DateTimeImmutable($date))->format('w');
    return $weekday === (int)$template['weekday'];
}

function list_slot_instances(?string $genre = null, int $days = BOOKING_WINDOW_DAYS, bool $publicOnly = true): array
{
    $start = new DateTimeImmutable('today');
    $end = $start->modify('+' . max($days, 1) . ' days');

    $templates = fetch_slot_templates();
    $templateIds = array_map(static fn(array $row): int => (int)$row['id'], $templates);
    $exceptions = fetch_slot_exceptions($templateIds);
    $bookingCounts = fetch_booking_counts($templateIds, $start->format('Y-m-d'), $end->format('Y-m-d'));
    $closures = trial_closures_for_range($start->format('Y-m-d'), $end->format('Y-m-d'));

    $slots = [];

    foreach ($templates as $template) {
        $templateGenre = (string)$template['genre'];
        if ($genre !== null && $genre !== $templateGenre) {
            continue;
        }

        $templateStatus = (string)$template['status'];
        if ($publicOnly && $templateStatus === 'hidden') {
            continue;
        }

        $dates = [];
        if ((string)$template['slot_type'] === 'single') {
            $singleDate = (string)$template['single_date'];
            if ($singleDate !== '' && $singleDate >= $start->format('Y-m-d') && $singleDate <= $end->format('Y-m-d')) {
                $dates[] = $singleDate;
            }
        } else {
            if (empty($template['repeat_start_date']) || $template['weekday'] === null || $template['weekday'] === '') {
                continue;
            }

            $periodStart = new DateTimeImmutable((string)$template['repeat_start_date']);
            $periodEnd = !empty($template['repeat_end_date'])
                ? new DateTimeImmutable((string)$template['repeat_end_date'])
                : $end;

            if ($periodEnd < $start) {
                continue;
            }

            $cursor = $periodStart > $start ? $periodStart : $start;
            while ($cursor <= $periodEnd && $cursor <= $end) {
                if ((int)$cursor->format('w') === (int)$template['weekday']) {
                    $dates[] = $cursor->format('Y-m-d');
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        foreach ($dates as $date) {
            $exception = $exceptions[(int)$template['id']][$date] ?? null;
            $slot = build_slot_instance(
                $template,
                $date,
                $exception,
                $bookingCounts[encode_slot_instance_id((int)$template['id'], $date)] ?? 0
            );

            if ($slot === null) {
                continue;
            }
            if (slot_blocked_by_closure($slot, $closures)) {
                continue;
            }
            if ($publicOnly && $slot['status'] !== 'open') {
                continue;
            }

            $slots[] = $slot;
        }
    }

    usort($slots, static function (array $a, array $b): int {
        return [$a['booking_date'], $a['start_time'], $a['genre'], $a['template_id']]
            <=>
            [$b['booking_date'], $b['start_time'], $b['genre'], $b['template_id']];
    });

    return $slots;
}

function find_slot_instance(string $slotId, bool $publicOnly = true): ?array
{
    $decoded = decode_slot_instance_id($slotId);
    if ($decoded === null) {
        return null;
    }

    $template = fetch_slot_templates($decoded['template_id'])[0] ?? null;
    if (!$template) {
        return null;
    }

    $exceptions = fetch_slot_exceptions([$decoded['template_id']]);
    $bookingCounts = fetch_booking_counts([$decoded['template_id']], $decoded['booking_date'], $decoded['booking_date']);
    $slot = build_slot_instance(
        $template,
        $decoded['booking_date'],
        $exceptions[$decoded['template_id']][$decoded['booking_date']] ?? null,
        $bookingCounts[$slotId] ?? 0
    );

    if ($slot === null) {
        return null;
    }
    if (slot_blocked_by_closure($slot, trial_closures_for_range($decoded['booking_date'], $decoded['booking_date']))) {
        return null;
    }
    if ($publicOnly && $slot['status'] !== 'open') {
        return null;
    }

    return $slot;
}

function customer_has_existing_booking(string $genre, string $email, string $phone): bool
{
    $stmt = db()->prepare(
        "SELECT id
         FROM trial_bookings
         WHERE genre = :genre
           AND status <> 'cancelled'
           AND (email = :email OR phone = :phone)
         LIMIT 1"
    );
    $stmt->execute([
        'genre' => $genre,
        'email' => $email,
        'phone' => $phone,
    ]);

    return (bool)$stmt->fetchColumn();
}
