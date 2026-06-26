<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/app/extension-api.php';

function assert_same_value(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $label . ' expected ' . var_export($expected, true) . ' got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_true_value(bool $actual, string $label): void
{
    if (!$actual) {
        fwrite(STDERR, $label . ' expected true' . PHP_EOL);
        exit(1);
    }
}

function assert_false_value(bool $actual, string $label): void
{
    if ($actual) {
        fwrite(STDERR, $label . ' expected false' . PHP_EOL);
        exit(1);
    }
}

$pairing = extension_generate_pairing_code();
assert_true_value(preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $pairing['display_code']) === 1, 'pairing display format');
assert_same_value($pairing['normalized_code'], extension_normalize_pairing_code(strtolower($pairing['display_code'])), 'pairing normalization');
assert_same_value(64, strlen(extension_hash_secret($pairing['normalized_code'])), 'sha256 hash length');

$token = extension_generate_secret_token();
assert_true_value(str_starts_with($token, 'fpslim_'), 'token prefix');
assert_true_value(extension_photo_token_is_safe($token), 'photo token accepts generated token');
assert_false_value(extension_photo_token_is_safe('../' . $token), 'photo token rejects path traversal');

assert_same_value('...12345678', extension_mask_installation_id('installation-12345678'), 'installation masking');
assert_true_value(extension_validate_member_number('FP-12345'), 'member number valid');
assert_false_value(extension_validate_member_number('FP 12345'), 'member number rejects spaces');

$listItem = extension_admission_list_item([
    'id' => 1,
    'application_id' => 'adm_test_001',
    'created_at' => '2026-07-01 10:00:00',
    'slim_status' => 'preparing',
    'version' => 3,
    'surname' => 'Secret',
    'phone' => '09000000000',
], []);
$listJson = json_encode($listItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
assert_true_value(isset($listItem['application_id'], $listItem['submitted_at'], $listItem['slim_status']), 'list has minimal keys');
assert_false_value(str_contains((string)$listJson, 'Secret'), 'list excludes name');
assert_false_value(str_contains((string)$listJson, '09000000000'), 'list excludes phone');

$transfer = extension_transfer_payload([
    'id' => 'adm_test_001',
    'created_at' => '2026-07-01 10:00:00',
    'slim_status' => 'preparing',
    'version' => 4,
    'admin_note' => 'do not expose admin note',
    'data' => [
        'medical_memo' => 'do not expose health memo',
        'health_checks' => ['x'],
    ],
    'normalized' => [
        'name' => 'Sample User',
        'surname' => 'Sample',
        'given_name' => 'User',
        'surname_kana' => 'SAMPLE',
        'given_name_kana' => 'USER',
        'actual_procedure_date' => '2026-07-01',
        'start_date' => '2026-07-08',
        'main_member_number' => 'M001',
        'slim_member_number' => 'S001',
    ],
    'operations' => [
        [
            'id' => 10,
            'sequence_no' => 1,
            'operation_key' => '01_admission_procedure_FP',
            'operation_type' => 'admission_procedure',
            'page_type' => 'admission_procedure',
            'course_id' => 151,
            'course_code' => 'FP',
            'business_label' => 'Find Pilates basic standalone',
            'slim_option_texts' => ['FP'],
            'status' => 'ready',
            'readiness_errors' => [],
        ],
    ],
    'photo' => [
        'path' => 'hidden/path/photo.jpg',
        'mime' => 'image/jpeg',
        'size' => 1234,
    ],
    'readiness' => [
        'errors' => [],
        'warnings' => [],
    ],
]);
$transferJson = json_encode($transfer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
assert_same_value('adm_test_001', $transfer['application_id'], 'transfer application id');
assert_same_value('Sample User', $transfer['display_name'], 'transfer selected display name allowed');
assert_false_value(str_contains((string)$transferJson, 'admin note'), 'transfer excludes admin note');
assert_false_value(str_contains((string)$transferJson, 'health memo'), 'transfer excludes health memo');
assert_false_value(str_contains((string)$transferJson, 'hidden/path'), 'transfer excludes photo path');
assert_same_value('FIND-SLIM/S001.jpg', $transfer['photo']['download_filename'], 'photo download filename');

echo "extension_api_unit: OK\n";
