<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/trial.php';
require_once __DIR__ . '/schema.php';

function trial_admin_business_hours(): array
{
    return [
        'open' => '09:00',
        'close' => '21:00',
        'step_minutes' => 30,
        'max_generate_count' => 250,
    ];
}

function valid_date_string(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
}

function valid_time_string(string $time): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
}

function sql_time(string $time): string
{
    return substr($time, 0, 5) . ':00';
}

function time_to_minutes(string $time): int
{
    $parts = explode(':', substr($time, 0, 5));
    return ((int)$parts[0] * 60) + (int)$parts[1];
}

function minutes_to_time(int $minutes): string
{
    $minutes = max(0, min($minutes, 23 * 60 + 59));
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function time_ranges_overlap(string $startA, string $endA, string $startB, string $endB): bool
{
    return time_to_minutes($startA) < time_to_minutes($endB)
        && time_to_minutes($startB) < time_to_minutes($endA);
}

function week_start_monday(string $date): DateTimeImmutable
{
    $base = valid_date_string($date) ? new DateTimeImmutable($date) : new DateTimeImmutable('today');
    return $base->modify('monday this week');
}

function week_days(DateTimeImmutable $weekStart): array
{
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $day = $weekStart->modify('+' . $i . ' days');
        $days[] = [
            'date' => $day->format('Y-m-d'),
            'label' => $day->format('n/j'),
            'weekday' => weekday_label((int)$day->format('w')),
            'is_today' => $day->format('Y-m-d') === (new DateTimeImmutable('today'))->format('Y-m-d'),
        ];
    }

    return $days;
}

function each_date(string $startDate, string $endDate): array
{
    $dates = [];
    $cursor = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);

    while ($cursor <= $end) {
        $dates[] = $cursor->format('Y-m-d');
        $cursor = $cursor->modify('+1 day');
    }

    return $dates;
}

function admin_slot_instances_between(string $startDate, string $endDate, array $filters = []): array
{
    $templates = fetch_slot_templates();
    $templateIds = array_map(static fn(array $row): int => (int)$row['id'], $templates);
    $exceptions = fetch_slot_exceptions($templateIds);
    $bookingCounts = fetch_booking_counts($templateIds, $startDate, $endDate);
    $dates = each_date($startDate, $endDate);
    $slots = [];

    foreach ($templates as $template) {
        foreach ($dates as $date) {
            $slot = build_slot_instance(
                $template,
                $date,
                $exceptions[(int)$template['id']][$date] ?? null,
                $bookingCounts[encode_slot_instance_id((int)$template['id'], $date)] ?? 0
            );

            if ($slot === null || !admin_slot_matches_filters($slot, $filters)) {
                continue;
            }

            $slots[] = $slot;
        }
    }

    usort($slots, static fn(array $a, array $b): int => [
        $a['booking_date'],
        $a['start_time'],
        $a['genre'],
        $a['template_id'],
    ] <=> [
        $b['booking_date'],
        $b['start_time'],
        $b['genre'],
        $b['template_id'],
    ]);

    return $slots;
}

function admin_slot_matches_filters(array $slot, array $filters): bool
{
    $genre = (string)($filters['genre'] ?? '');
    if ($genre !== '' && (string)$slot['genre'] !== $genre) {
        return false;
    }

    $status = (string)($filters['status'] ?? '');
    if ($status === 'full' && empty($slot['full'])) {
        return false;
    }
    if ($status === 'changed' && (string)$slot['exception_type'] === '') {
        return false;
    }
    if ($status === 'substitute' && (string)$slot['exception_type'] !== 'substitute') {
        return false;
    }
    if ($status !== '' && !in_array($status, ['full', 'changed', 'substitute'], true) && (string)$slot['status'] !== $status) {
        return false;
    }

    $instructor = trim((string)($filters['instructor'] ?? ''));
    if ($instructor !== '' && mb_stripos((string)$slot['instructor_name'], $instructor) === false) {
        return false;
    }

    return true;
}

function normalize_weekdays(array $rawWeekdays): array
{
    $weekdays = [];
    foreach ($rawWeekdays as $weekday) {
        $value = (string)$weekday;
        if (ctype_digit($value) && array_key_exists((int)$value, weekday_options())) {
            $weekdays[] = (int)$value;
        }
    }

    return array_values(array_unique($weekdays));
}

function schedule_candidates_from_input(array $input): array
{
    $hours = trial_admin_business_hours();
    $errors = [];
    $candidates = [];

    $kind = trim((string)($input['create_kind'] ?? 'pilates'));
    $repeatMode = trim((string)($input['repeat_mode'] ?? 'single'));
    $genre = $kind === 'closed' ? trim((string)($input['closed_genre'] ?? 'pilates')) : $kind;
    if (!array_key_exists($genre, genre_options())) {
        $errors[] = '種別を選択してください。';
    }

    $lessonName = trim((string)($input['lesson_name'] ?? ''));
    if ($lessonName === '') {
        $lessonName = $kind === 'closed' ? '予約受付停止' : (genre_options()[$genre] ?? '体験枠');
    }

    $instructorName = trim((string)($input['instructor_name'] ?? ''));
    $locationName = trim((string)($input['location_name'] ?? ''));
    $boothName = trim((string)($input['booth_name'] ?? ''));
    $equipmentName = trim((string)($input['equipment_name'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $status = $kind === 'closed' ? 'closed' : trim((string)($input['status'] ?? 'open'));
    if (!array_key_exists($status, slot_status_options())) {
        $errors[] = '公開状態を選択してください。';
    }

    $capacityRaw = trim((string)($input['capacity'] ?? '1'));
    if (!ctype_digit($capacityRaw) || (int)$capacityRaw < 1 || (int)$capacityRaw > 99) {
        $errors[] = '定員は1〜99の整数で入力してください。';
    }
    $capacity = max(1, (int)$capacityRaw);

    $startTime = trim((string)($input['start_time'] ?? ''));
    $endTime = trim((string)($input['end_time'] ?? ''));

    if ($repeatMode !== 'self_esthe_bulk') {
        if (!valid_time_string($startTime) || !valid_time_string($endTime)) {
            $errors[] = '開始・終了時刻を正しく入力してください。';
        } elseif (time_to_minutes($startTime) >= time_to_minutes($endTime)) {
            $errors[] = '終了時刻は開始時刻より後にしてください。';
        }
    }

    $today = new DateTimeImmutable('today');
    $appendCandidate = static function (string $date, string $slotStart, string $slotEnd) use (&$candidates, $genre, $lessonName, $instructorName, $locationName, $boothName, $equipmentName, $description, $status, $capacity, $kind): void {
        $candidates[] = [
            'date' => $date,
            'start_time' => $slotStart,
            'end_time' => $slotEnd,
            'genre' => $genre,
            'lesson_name' => $lessonName,
            'instructor_name' => $instructorName,
            'location_name' => $locationName,
            'booth_name' => $boothName,
            'equipment_name' => $equipmentName,
            'description' => $description,
            'status' => $status,
            'capacity' => $capacity,
            'kind' => $kind,
        ];
    };

    if ($repeatMode === 'single') {
        $date = trim((string)($input['single_date'] ?? ''));
        if (!valid_date_string($date)) {
            $errors[] = '単発日付を入力してください。';
        } elseif (new DateTimeImmutable($date) < $today) {
            $errors[] = '過去日の新規登録はできません。';
        } else {
            $appendCandidate($date, $startTime, $endTime);
        }
    } elseif (in_array($repeatMode, ['weekly', 'biweekly', 'multi_weekday', 'self_esthe_bulk'], true)) {
        $rangeStart = trim((string)($input['repeat_start_date'] ?? ''));
        $rangeEnd = trim((string)($input['repeat_end_date'] ?? ''));
        if (!valid_date_string($rangeStart) || !valid_date_string($rangeEnd)) {
            $errors[] = '繰り返しの開始日・終了日を入力してください。';
        } elseif ($rangeStart > $rangeEnd) {
            $errors[] = '終了日は開始日以降にしてください。';
        } elseif (new DateTimeImmutable($rangeStart) < $today) {
            $errors[] = '過去日を含む期間では新規登録できません。';
        } else {
            $weekdays = normalize_weekdays((array)($input['weekdays'] ?? []));
            if ($weekdays === []) {
                $weekdays[] = (int)(new DateTimeImmutable($rangeStart))->format('w');
            }

            if ($repeatMode === 'self_esthe_bulk') {
                $genre = 'self_esthe';
                $bulkStart = trim((string)($input['bulk_start_time'] ?? ''));
                $bulkEnd = trim((string)($input['bulk_end_time'] ?? ''));
                $duration = (int)($input['duration_minutes'] ?? 50);
                $cleanup = (int)($input['cleanup_minutes'] ?? 10);
                if (!valid_time_string($bulkStart) || !valid_time_string($bulkEnd)) {
                    $errors[] = '受付開始・終了時刻を正しく入力してください。';
                } elseif ($duration < 10 || $duration > 240 || $cleanup < 0 || $cleanup > 120) {
                    $errors[] = '利用時間または清掃時間が不正です。';
                } else {
                    foreach (each_date($rangeStart, $rangeEnd) as $date) {
                        if (!in_array((int)(new DateTimeImmutable($date))->format('w'), $weekdays, true)) {
                            continue;
                        }
                        $cursor = time_to_minutes($bulkStart);
                        $bulkEndMinutes = time_to_minutes($bulkEnd);
                        while ($cursor + $duration <= $bulkEndMinutes) {
                            $appendCandidate($date, minutes_to_time($cursor), minutes_to_time($cursor + $duration));
                            $lastIndex = array_key_last($candidates);
                            $candidates[$lastIndex]['genre'] = 'self_esthe';
                            $candidates[$lastIndex]['cleanup_minutes'] = $cleanup;
                            $cursor += $duration + $cleanup;
                        }
                    }
                }
            } else {
                $intervalWeeks = $repeatMode === 'biweekly' ? 2 : 1;
                $seriesStart = new DateTimeImmutable($rangeStart);
                foreach (each_date($rangeStart, $rangeEnd) as $date) {
                    $dt = new DateTimeImmutable($date);
                    if (!in_array((int)$dt->format('w'), $weekdays, true)) {
                        continue;
                    }
                    $weekDiff = (int)floor(((int)$seriesStart->diff($dt)->format('%a')) / 7);
                    if ($intervalWeeks > 1 && $weekDiff % $intervalWeeks !== 0) {
                        continue;
                    }
                    $appendCandidate($date, $startTime, $endTime);
                }
            }
        }
    } else {
        $errors[] = '開催方法を選択してください。';
    }

    if (count($candidates) > (int)$hours['max_generate_count']) {
        $errors[] = '作成件数が多すぎます。期間または対象曜日を絞ってください。';
    }

    foreach ($candidates as $candidate) {
        if (time_to_minutes((string)$candidate['start_time']) < time_to_minutes((string)$hours['open'])
            || time_to_minutes((string)$candidate['end_time']) > time_to_minutes((string)$hours['close'])) {
            $errors[] = '営業時間外の枠が含まれています。営業時間は' . $hours['open'] . '〜' . $hours['close'] . 'です。';
            break;
        }
    }

    return [
        'errors' => array_values(array_unique($errors)),
        'candidates' => $candidates,
        'repeat_mode' => $repeatMode,
    ];
}

function schedule_conflicts(array $candidates, int $ignoreTemplateId = 0): array
{
    if ($candidates === []) {
        return [];
    }

    $dates = array_column($candidates, 'date');
    $startDate = min($dates);
    $endDate = max($dates);
    try {
        $existingSlots = admin_slot_instances_between($startDate, $endDate);
    } catch (Throwable $e) {
        return [[
            'date' => '-',
            'time' => '-',
            'message' => function_exists('db_error_message') ? db_error_message($e) : $e->getMessage(),
            'blocking' => true,
            'system_error' => true,
        ]];
    }
    $conflicts = [];

    foreach ($candidates as $candidate) {
        foreach ($existingSlots as $slot) {
            if ($ignoreTemplateId > 0 && (int)$slot['template_id'] === $ignoreTemplateId) {
                continue;
            }
            if ((string)$slot['booking_date'] !== (string)$candidate['date']) {
                continue;
            }
            if (!time_ranges_overlap((string)$candidate['start_time'], (string)$candidate['end_time'], (string)$slot['start_time'], (string)$slot['end_time'])) {
                continue;
            }

            $sameInstructor = trim((string)$candidate['instructor_name']) !== ''
                && trim((string)$slot['instructor_name']) !== ''
                && trim((string)$candidate['instructor_name']) === trim((string)$slot['instructor_name']);
            $sameContent = (string)$candidate['genre'] === (string)$slot['genre']
                && (string)$candidate['lesson_name'] === (string)$slot['lesson_name']
                && substr((string)$candidate['start_time'], 0, 5) === substr((string)$slot['start_time'], 0, 5);

            if ($sameInstructor || $sameContent) {
                $conflicts[] = [
                    'date' => (string)$candidate['date'],
                    'time' => format_time_range((string)$candidate['start_time'], (string)$candidate['end_time']),
                    'message' => ($sameInstructor ? '担当者重複' : '同一内容の重複') . ': ' . $slot['lesson_name'] . ' / ' . $slot['instructor_name'],
                    'blocking' => true,
                ];
            }
        }
    }

    try {
        if (db_table_exists_for_schedule('trial_closures')) {
            $conflicts = array_merge($conflicts, closure_conflicts($candidates, $startDate, $endDate));
        }
    } catch (Throwable $e) {
        $conflicts[] = [
            'date' => '-',
            'time' => '-',
            'message' => function_exists('db_error_message') ? db_error_message($e) : $e->getMessage(),
            'blocking' => true,
            'system_error' => true,
        ];
    }

    return $conflicts;
}

function db_table_exists_for_schedule(string $tableName): bool
{
    return db_table_exists_cached($tableName);
}

function closure_conflicts(array $candidates, string $startDate, string $endDate): array
{
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
    $closures = $stmt->fetchAll();
    $conflicts = [];

    foreach ($candidates as $candidate) {
        foreach ($closures as $closure) {
            if ((string)$candidate['date'] < (string)$closure['start_date'] || (string)$candidate['date'] > (string)$closure['end_date']) {
                continue;
            }
            $closureType = (string)$closure['closure_type'];
            $applies = $closureType === 'all'
                || $closureType === (string)$candidate['genre']
                || in_array($closureType, ['maintenance', 'cleaning', 'shooting', 'internal'], true);
            if (!$applies) {
                continue;
            }
            if (!empty($closure['start_time']) && !empty($closure['end_time'])
                && !time_ranges_overlap((string)$candidate['start_time'], (string)$candidate['end_time'], (string)$closure['start_time'], (string)$closure['end_time'])) {
                continue;
            }
            $conflicts[] = [
                'date' => (string)$candidate['date'],
                'time' => format_time_range((string)$candidate['start_time'], (string)$candidate['end_time']),
                'message' => '休館・利用停止: ' . (string)$closure['title'],
                'blocking' => true,
            ];
        }
    }

    return $conflicts;
}

function insert_single_slot_template(PDO $pdo, array $candidate): int
{
    $data = [
        'slot_type' => 'single',
        'genre' => $candidate['genre'],
        'lesson_name' => $candidate['lesson_name'],
        'instructor_name' => $candidate['instructor_name'] !== '' ? $candidate['instructor_name'] : null,
        'weekday' => null,
        'single_date' => $candidate['date'],
        'start_time' => sql_time((string)$candidate['start_time']),
        'end_time' => sql_time((string)$candidate['end_time']),
        'capacity' => (int)$candidate['capacity'],
        'repeat_start_date' => null,
        'repeat_end_date' => null,
        'description' => $candidate['description'] !== '' ? $candidate['description'] : null,
        'status' => $candidate['status'],
    ];
    add_optional_slot_template_data($data, $candidate);
    insert_slot_template_row($pdo, $data);

    return (int)$pdo->lastInsertId();
}

function insert_repeat_slot_template(PDO $pdo, array $prototype, int $weekday, string $startDate, string $endDate): int
{
    $data = [
        'slot_type' => 'repeat',
        'genre' => $prototype['genre'],
        'lesson_name' => $prototype['lesson_name'],
        'instructor_name' => $prototype['instructor_name'] !== '' ? $prototype['instructor_name'] : null,
        'weekday' => $weekday,
        'single_date' => null,
        'start_time' => sql_time((string)$prototype['start_time']),
        'end_time' => sql_time((string)$prototype['end_time']),
        'capacity' => (int)$prototype['capacity'],
        'repeat_start_date' => $startDate,
        'repeat_end_date' => $endDate,
        'description' => $prototype['description'] !== '' ? $prototype['description'] : null,
        'status' => $prototype['status'],
    ];
    add_optional_slot_template_data($data, $prototype);
    insert_slot_template_row($pdo, $data);

    return (int)$pdo->lastInsertId();
}

function add_optional_slot_template_data(array &$data, array $source): void
{
    foreach (['location_name', 'booth_name', 'equipment_name'] as $column) {
        if (db_column_exists_cached('trial_slot_templates', $column)) {
            $value = trim((string)($source[$column] ?? ''));
            $data[$column] = $value !== '' ? $value : null;
        }
    }
    if (db_column_exists_cached('trial_slot_templates', 'cleanup_minutes')) {
        $data['cleanup_minutes'] = max(0, (int)($source['cleanup_minutes'] ?? 0));
    }
    if (db_column_exists_cached('trial_slot_templates', 'admin_note')) {
        $data['admin_note'] = null;
    }
}

function insert_slot_template_row(PDO $pdo, array $data): void
{
    $columns = array_keys($data);
    $columnSql = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
    $valueSql = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));
    $stmt = $pdo->prepare('INSERT INTO trial_slot_templates (' . $columnSql . ') VALUES (' . $valueSql . ')');
    $stmt->execute($data);
}

function trial_schedule_acquire_write_lock(PDO $pdo): void
{
    $lock = $pdo->query("SELECT GET_LOCK('findpilates_trial_schedule_write', 10)")->fetchColumn();
    if ((int)$lock !== 1) {
        throw new RuntimeException('現在ほかの管理者が保存処理中です。少し待ってから再度お試しください。');
    }
}

function trial_schedule_release_write_lock(PDO $pdo): void
{
    try {
        $pdo->query("SELECT RELEASE_LOCK('findpilates_trial_schedule_write')");
    } catch (Throwable $e) {
        error_log('Failed to release schedule lock: ' . $e->getMessage());
    }
}

function conflicts_have_system_error(array $conflicts): bool
{
    foreach ($conflicts as $conflict) {
        if (!empty($conflict['system_error'])) {
            return true;
        }
    }

    return false;
}

function schedule_save_from_input(array $input): array
{
    $built = schedule_candidates_from_input($input);
    if ($built['errors'] !== []) {
        return ['created' => 0, 'skipped' => 0, 'errors' => $built['errors']];
    }

    $candidates = $built['candidates'];
    $conflicts = schedule_conflicts($candidates);
    if (conflicts_have_system_error($conflicts)) {
        return [
            'created' => 0,
            'skipped' => 0,
            'errors' => array_map(static fn(array $conflict): string => $conflict['message'], $conflicts),
        ];
    }
    $blockingConflicts = array_values(array_filter($conflicts, static fn(array $conflict): bool => !empty($conflict['blocking'])));
    if ($blockingConflicts !== [] && empty($input['skip_conflicts'])) {
        return [
            'created' => 0,
            'skipped' => count($blockingConflicts),
            'errors' => array_map(static fn(array $conflict): string => $conflict['date'] . ' ' . $conflict['time'] . ' ' . $conflict['message'], $blockingConflicts),
        ];
    }

    $conflictKeys = [];
    foreach ($blockingConflicts as $conflict) {
        $conflictKeys[$conflict['date'] . '|' . $conflict['time']] = true;
    }

    $pdo = db();
    $created = 0;
    $skipped = 0;

    trial_schedule_acquire_write_lock($pdo);
    try {
        $pdo->beginTransaction();
        $conflicts = schedule_conflicts($candidates);
        if (conflicts_have_system_error($conflicts)) {
            throw new RuntimeException((string)($conflicts[0]['message'] ?? '競合確認に失敗しました。'));
        }
        $blockingConflicts = array_values(array_filter($conflicts, static fn(array $conflict): bool => !empty($conflict['blocking'])));
        if ($blockingConflicts !== [] && empty($input['skip_conflicts'])) {
            throw new RuntimeException(implode("\n", array_map(static fn(array $conflict): string => $conflict['date'] . ' ' . $conflict['time'] . ' ' . $conflict['message'], $blockingConflicts)));
        }
        $conflictKeys = [];
        foreach ($blockingConflicts as $conflict) {
            $conflictKeys[$conflict['date'] . '|' . $conflict['time']] = true;
        }

        $repeatMode = (string)$built['repeat_mode'];
        if (in_array($repeatMode, ['weekly', 'multi_weekday'], true) && $conflictKeys === []) {
            $weekdays = normalize_weekdays((array)($input['weekdays'] ?? []));
            if ($weekdays === []) {
                $weekdays[] = (int)(new DateTimeImmutable((string)$input['repeat_start_date']))->format('w');
            }
            $prototype = $candidates[0];
            foreach ($weekdays as $weekday) {
                insert_repeat_slot_template($pdo, $prototype, $weekday, (string)$input['repeat_start_date'], (string)$input['repeat_end_date']);
                $created++;
            }
        } else {
            foreach ($candidates as $candidate) {
                $key = $candidate['date'] . '|' . format_time_range((string)$candidate['start_time'], (string)$candidate['end_time']);
                if (isset($conflictKeys[$key]) && !empty($input['skip_conflicts'])) {
                    $skipped++;
                    continue;
                }
                insert_single_slot_template($pdo, $candidate);
                $created++;
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        trial_schedule_release_write_lock($pdo);
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => []];
}

function csv_safe_value(string $value): string
{
    $check = ltrim($value, " \t\r\n");
    if ($check !== '' && preg_match('/^[=+\-@]/u', $check) === 1) {
        return "'" . $value;
    }

    return $value;
}
