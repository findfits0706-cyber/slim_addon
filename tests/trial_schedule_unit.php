<?php
declare(strict_types=1);

require_once __DIR__ . '/../public_html/app/config.php';
require_once __DIR__ . '/../public_html/app/trial-schedule.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$baseDate = new DateTimeImmutable('today +30 days');
$singleDate = $baseDate->format('Y-m-d');
$weeklyStart = $baseDate->modify('next wednesday');
$weeklyEnd = $weeklyStart->modify('+28 days');
$multiStart = $baseDate->modify('next monday');
$multiEnd = $multiStart->modify('+4 days');

$single = schedule_candidates_from_input([
    'create_kind' => 'pilates',
    'repeat_mode' => 'single',
    'single_date' => $singleDate,
    'start_time' => '10:00',
    'end_time' => '10:50',
    'lesson_name' => '単発テスト',
    'capacity' => '1',
    'status' => 'open',
]);
assert_true($single['errors'] === [], 'single should have no errors');
assert_true(count($single['candidates']) === 1, 'single should generate one candidate');

$weekly = schedule_candidates_from_input([
    'create_kind' => 'pilates',
    'repeat_mode' => 'weekly',
    'repeat_start_date' => $weeklyStart->format('Y-m-d'),
    'repeat_end_date' => $weeklyEnd->format('Y-m-d'),
    'weekdays' => ['3'],
    'start_time' => '11:00',
    'end_time' => '11:50',
    'lesson_name' => '毎週テスト',
    'capacity' => '2',
    'status' => 'open',
]);
assert_true(count($weekly['candidates']) === 5, 'weekly Wednesdays in a 29 day range should be five');

$biweekly = schedule_candidates_from_input([
    'create_kind' => 'visit',
    'repeat_mode' => 'biweekly',
    'repeat_start_date' => $weeklyStart->format('Y-m-d'),
    'repeat_end_date' => $weeklyEnd->format('Y-m-d'),
    'weekdays' => ['3'],
    'start_time' => '12:00',
    'end_time' => '12:30',
    'lesson_name' => '隔週テスト',
    'capacity' => '1',
    'status' => 'open',
]);
assert_true(count($biweekly['candidates']) === 3, 'biweekly Wednesdays in a 29 day range should be three');

$multi = schedule_candidates_from_input([
    'create_kind' => 'pilates',
    'repeat_mode' => 'multi_weekday',
    'repeat_start_date' => $multiStart->format('Y-m-d'),
    'repeat_end_date' => $multiEnd->format('Y-m-d'),
    'weekdays' => ['1', '3', '5'],
    'start_time' => '13:00',
    'end_time' => '13:50',
    'lesson_name' => '複数曜日テスト',
    'capacity' => '1',
    'status' => 'open',
]);
assert_true(count($multi['candidates']) === 3, 'multi weekday should generate Mon/Wed/Fri');

$bulk = schedule_candidates_from_input([
    'create_kind' => 'self_esthe',
    'repeat_mode' => 'self_esthe_bulk',
    'repeat_start_date' => $weeklyStart->format('Y-m-d'),
    'repeat_end_date' => $weeklyStart->format('Y-m-d'),
    'weekdays' => [(string)(int)$weeklyStart->format('w')],
    'bulk_start_time' => '10:00',
    'bulk_end_time' => '13:00',
    'duration_minutes' => '50',
    'cleanup_minutes' => '10',
    'lesson_name' => 'セルフエステ分割',
    'capacity' => '1',
    'status' => 'open',
]);
assert_true(count($bulk['candidates']) === 3, 'self esthe 10-13 with 50+10 should generate three slots');

assert_true(week_start_monday('2028-02-29')->format('Y-m-d') === '2028-02-28', 'leap day should resolve to its Monday');
assert_true(csv_safe_value('=SUM(A1:A2)') === "'=SUM(A1:A2)", 'csv formula value should be escaped');

echo "trial_schedule_unit: OK\n";
