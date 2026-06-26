<?php
declare(strict_types=1);

session_start();

$root = dirname(__DIR__);
$config = require $root . '/public_html/admission/inc/config.php';
require_once $root . '/public_html/admission/inc/functions.php';

function assert_same_value(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $label . ' expected ' . var_export($expected, true) . ' got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function base_data(array $override = []): array
{
    return array_merge([
        'use_type' => 'new',
        'course' => 'basic',
        'main_member_status' => 'existing',
        'main_member_number' => '',
        'main_membership' => '',
        'addon' => 'basic',
        'initial_visits' => '999',
        'start_date' => '2026-07-01',
        'campaign_code' => '',
    ], $override);
}

$cases = [
    ['basic 1', base_data(['course' => 'basic', 'start_date' => '2026-07-01']), 8800, 8],
    ['basic 8', base_data(['course' => 'basic', 'start_date' => '2026-07-08']), 6600, 6],
    ['basic 15', base_data(['course' => 'basic', 'start_date' => '2026-07-15']), 4400, 4],
    ['basic 22', base_data(['course' => 'basic', 'start_date' => '2026-07-22']), 2200, 2],
    ['double 1', base_data(['course' => 'double', 'start_date' => '2026-07-01']), 12650, 16],
    ['double 8', base_data(['course' => 'double', 'start_date' => '2026-07-08']), 9488, 12],
    ['double 15', base_data(['course' => 'double', 'start_date' => '2026-07-15']), 6325, 8],
    ['double 22', base_data(['course' => 'double', 'start_date' => '2026-07-22']), 3163, 4],
    ['addon basic 8', base_data(['use_type' => 'add', 'addon' => 'basic', 'main_membership' => 'find_master', 'start_date' => '2026-07-08']), 2888, 6],
    ['addon basic 22', base_data(['use_type' => 'add', 'addon' => 'basic', 'main_membership' => 'find_master', 'start_date' => '2026-07-22']), 963, 2],
    ['addon double 8', base_data(['use_type' => 'add', 'addon' => 'double', 'main_membership' => 'find_master', 'start_date' => '2026-07-08']), 5775, 12],
];

foreach ($cases as [$label, $data, $expectedFee, $expectedVisits]) {
    $fees = calculate_fees($config, $data);
    assert_same_value($expectedFee, (int)$fees['pilates_current_month_fee'], $label . ' pilates fee');
    assert_same_value($expectedVisits, (int)$fees['initial_visits'], $label . ' visits');
}

$existingMain = calculate_fees($config, base_data([
    'use_type' => 'add',
    'main_member_status' => 'existing',
    'main_membership' => 'find_master',
    'addon' => 'basic',
    'start_date' => '2026-07-08',
]));
assert_same_value(0, (int)$existingMain['main_club_initial_fee'], 'existing main member main fee');

$simultaneous = calculate_fees($config, base_data([
    'use_type' => 'add',
    'main_member_status' => 'simultaneous',
    'main_membership' => 'find_master',
    'addon' => 'basic',
    'start_date' => '2026-07-16',
]));
if ((int)$simultaneous['main_club_initial_fee'] <= 0) {
    fwrite(STDERR, 'simultaneous main member should have prorated main fee' . PHP_EOL);
    exit(1);
}

$_SESSION['csrf_token'] = 'test-token';
$validData = array_merge(admission_blank_data(), [
    'csrf_token' => 'test-token',
    'use_type' => 'add',
    'course' => 'basic',
    'main_member_status' => 'simultaneous',
    'main_membership' => 'weekend',
    'addon' => 'basic',
    'start_date' => '2026-07-01',
    'procedure_date_1' => '2026-07-02',
    'procedure_time_1' => '10:00-11:30',
    'surname' => '山田',
    'given_name' => '花子',
    'surname_kana' => 'ヤマダ',
    'given_name_kana' => 'ハナコ',
    'name' => '山田 花子',
    'kana' => 'ヤマダ ハナコ',
    'birth' => '1990-01-01',
    'gender' => '女性',
    'phone_type' => 'mobile',
    'phone' => '09012345678',
    'email' => 'user@example.com',
    'prefecture' => '栃木県',
    'city_area' => '那須塩原市',
    'street_address' => '西大和1-8',
    'emergency_name' => '山田太郎',
    'emergency_relationship' => '父',
    'emergency_phone' => '09012345679',
    'terms_agree' => '1',
    'health_checks' => array_keys($config['health_checks']),
    'photo_token' => 'already-validated.jpg',
]);
$errors = validate_form($config, $validData, true);
assert_same_value(true, isset($errors['main_membership']), 'weekend rejected');

echo "admission_fee_unit: OK\n";
