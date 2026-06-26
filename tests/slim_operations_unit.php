<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/admission/inc/slim.php';

function assert_same_value(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $label . ' expected ' . var_export($expected, true) . ' got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function normalized_admission(array $override = []): array
{
    return array_merge([
        'use_type' => 'new',
        'course' => 'basic',
        'main_member_status' => 'existing',
        'main_member_number' => 'M0001',
        'slim_member_number' => 'S0001',
        'main_membership' => 'find_master',
        'addon' => 'basic',
        'actual_procedure_date' => '2026-07-01',
        'start_date' => '2026-07-08',
        'surname' => 'Sample',
        'given_name' => 'User',
        'surname_kana' => 'SAMPLE',
        'given_name_kana' => 'USER',
        'birth' => '1990-01-01',
        'gender' => 'other',
        'phone_type' => 'mobile',
        'phone' => '09000000000',
    ], $override);
}

function course_ids(array $operations): array
{
    return array_map(static fn(array $operation): int => (int)$operation['course_id'], $operations);
}

$cases = [
    ['single basic', normalized_admission(['use_type' => 'new', 'course' => 'basic']), [151]],
    ['single double', normalized_admission(['use_type' => 'new', 'course' => 'double']), [135]],
    ['existing basic', normalized_admission(['use_type' => 'add', 'main_member_status' => 'existing', 'addon' => 'basic']), [145]],
    ['existing double', normalized_admission(['use_type' => 'add', 'main_member_status' => 'existing', 'addon' => 'double']), [146]],
    ['simultaneous find_master basic', normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'find_master', 'addon' => 'basic']), [80, 145]],
    ['simultaneous day_free double', normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'day_free', 'addon' => 'double']), [130, 146]],
    ['simultaneous night_holiday basic', normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'night_holiday', 'addon' => 'basic']), [74, 145]],
    ['simultaneous u34 double', normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'night_holiday_u34', 'addon' => 'double']), [133, 146]],
    ['simultaneous gym_free basic', normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'gym_free', 'addon' => 'basic']), [140, 145]],
    ['simultaneous gym_pool basic', normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'gym_pool', 'addon' => 'basic']), [140, 141, 145]],
    ['simultaneous gym_pool double', normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'gym_pool', 'addon' => 'double']), [140, 141, 146]],
    ['simultaneous gym_studio basic', normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'gym_studio', 'addon' => 'basic']), [140, 144, 145]],
    ['simultaneous gym_studio double', normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'gym_studio', 'addon' => 'double']), [140, 144, 146]],
];

foreach ($cases as [$label, $admission, $expectedIds]) {
    assert_same_value($expectedIds, course_ids(build_slim_operations($admission)), $label);
}

$weekend = normalized_admission(['use_type' => 'add', 'main_member_status' => 'simultaneous', 'main_membership' => 'weekend']);
assert_same_value([], build_slim_operations($weekend), 'weekend has no operations');
$weekendReadiness = validate_slim_readiness($weekend, build_slim_operations($weekend));
assert_same_value(true, in_array('legacy_weekend_plan', $weekendReadiness['errors'], true), 'weekend needs review');

$missingProcedureDate = slim_operations_with_readiness(normalized_admission(['actual_procedure_date' => '']));
assert_same_value('blocked', (string)$missingProcedureDate[0]['status'], 'missing procedure date not ready');
assert_same_value(true, in_array('actual_procedure_date_missing', $missingProcedureDate[0]['readiness_errors'], true), 'missing procedure date error');

$missingMainNumber = slim_operations_with_readiness(normalized_admission([
    'use_type' => 'add',
    'main_member_status' => 'existing',
    'main_member_number' => '',
    'addon' => 'basic',
]));
assert_same_value('blocked', (string)$missingMainNumber[0]['status'], 'missing main member number not ready');
assert_same_value(true, in_array('main_member_number_missing', $missingMainNumber[0]['readiness_errors'], true), 'missing main member number error');

$completedExisting = [
    [
        'sequence_no' => 1,
        'operation_type' => 'admission_procedure',
        'page_type' => 'admission_procedure',
        'course_id' => 151,
        'course_code' => 'FP',
        'status' => 'completed',
        'completed_at' => '2026-07-01 10:00:00',
    ],
];
$changedPlan = build_slim_operations(normalized_admission(['use_type' => 'new', 'course' => 'double']));
assert_same_value(true, slim_should_block_operation_regeneration($completedExisting, $changedPlan), 'completed operation blocks destructive regeneration');
assert_same_value(true, slim_operation_is_started($completedExisting[0]), 'completed operation is started');
assert_same_value(false, slim_operation_is_started(['status' => 'ready']), 'ready operation is not started');

echo "slim_operations_unit: OK\n";
