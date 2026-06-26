<?php
declare(strict_types=1);

function slim_course_catalog(): array
{
    return [
        'pilates_basic_standalone' => [
            'course_id' => 151,
            'course_code' => 'FP',
            'business_label' => 'Find Pilates basic standalone',
            'operation_type' => 'admission_procedure',
            'page_type' => 'admission_procedure',
            'slim_option_texts' => ['FP'],
        ],
        'pilates_double_standalone' => [
            'course_id' => 135,
            'course_code' => 'FP2',
            'business_label' => 'Find Pilates double standalone',
            'operation_type' => 'admission_procedure',
            'page_type' => 'admission_procedure',
            'slim_option_texts' => ['FP2'],
        ],
        'pilates_basic_addon' => [
            'course_id' => 145,
            'course_code' => 'P3',
            'business_label' => 'Find Pilates basic addon',
            'operation_type' => 'addition_notification',
            'page_type' => 'addition_notification',
            'slim_option_texts' => ['P3'],
        ],
        'pilates_double_addon' => [
            'course_id' => 146,
            'course_code' => 'P3W',
            'business_label' => 'Find Pilates double addon',
            'operation_type' => 'addition_notification',
            'page_type' => 'addition_notification',
            'slim_option_texts' => ['P3W'],
        ],
        'main_find_master' => [
            'course_id' => 80,
            'course_code' => 'MA',
            'business_label' => 'Master member',
            'operation_type' => 'admission_procedure',
            'page_type' => 'admission_procedure',
            'slim_option_texts' => ['MA'],
        ],
        'main_day_free' => [
            'course_id' => 130,
            'course_code' => 'DF',
            'business_label' => 'Day free member',
            'operation_type' => 'admission_procedure',
            'page_type' => 'admission_procedure',
            'slim_option_texts' => ['DF'],
        ],
        'main_night_holiday' => [
            'course_id' => 74,
            'course_code' => 'GEH',
            'business_label' => 'Night and holiday member',
            'operation_type' => 'admission_procedure',
            'page_type' => 'admission_procedure',
            'slim_option_texts' => ['GEH'],
        ],
        'main_night_holiday_u34' => [
            'course_id' => 133,
            'course_code' => 'A34',
            'business_label' => 'Night and holiday under 34',
            'operation_type' => 'admission_procedure',
            'page_type' => 'admission_procedure',
            'slim_option_texts' => ['A34'],
        ],
        'main_gym_free' => [
            'course_id' => 140,
            'course_code' => 'FM',
            'business_label' => 'Find members',
            'operation_type' => 'admission_procedure',
            'page_type' => 'admission_procedure',
            'slim_option_texts' => ['FM'],
        ],
        'addon_pool_1' => [
            'course_id' => 141,
            'course_code' => 'P1',
            'business_label' => 'Pool 1 addon',
            'operation_type' => 'addition_notification',
            'page_type' => 'addition_notification',
            'slim_option_texts' => ['P1'],
        ],
        'addon_studio_1' => [
            'course_id' => 144,
            'course_code' => 'S1',
            'business_label' => 'Studio 1 addon',
            'operation_type' => 'addition_notification',
            'page_type' => 'addition_notification',
            'slim_option_texts' => ['S1'],
        ],
    ];
}

function slim_catalog_course(string $key): ?array
{
    $catalog = slim_course_catalog();
    return $catalog[$key] ?? null;
}

function slim_normalized_value(array $admission, string $key): string
{
    return trim((string)($admission[$key] ?? ''));
}

function slim_addon_catalog_key(string $addon): string
{
    return $addon === 'double' || $addon === 'master'
        ? 'pilates_double_addon'
        : 'pilates_basic_addon';
}

function slim_operation_from_catalog(string $catalogKey, int $sequenceNo, array $admission): array
{
    $course = slim_catalog_course($catalogKey);
    if ($course === null) {
        throw new InvalidArgumentException('Unknown SLIM course catalog key: ' . $catalogKey);
    }

    $operationType = (string)$course['operation_type'];
    $isAddition = $operationType === 'addition_notification';
    $operationKey = sprintf('%02d_%s_%s', $sequenceNo, $operationType, (string)$course['course_code']);

    return [
        'sequence_no' => $sequenceNo,
        'operation_key' => $operationKey,
        'operation_type' => $operationType,
        'page_type' => (string)$course['page_type'],
        'course_id' => (int)$course['course_id'],
        'course_code' => (string)$course['course_code'],
        'business_label' => (string)$course['business_label'],
        'slim_option_texts' => array_values(array_map('strval', $course['slim_option_texts'] ?? [])),
        'reason_id' => $isAddition ? '9999' : null,
        'reason_label' => $isAddition ? 'other' : null,
        'payment_cycle' => 'monthly',
        'payment_cycle_label' => 'monthly',
        'application_date' => slim_normalized_value($admission, 'actual_procedure_date'),
        'start_date' => slim_normalized_value($admission, 'start_date'),
        'status' => 'pending',
        'readiness_errors' => [],
    ];
}

function build_slim_operations(array $normalizedAdmission): array
{
    $useType = slim_normalized_value($normalizedAdmission, 'use_type') === 'add' ? 'add' : 'new';
    $course = slim_normalized_value($normalizedAdmission, 'course');
    $addon = slim_normalized_value($normalizedAdmission, 'addon');
    $mainMemberStatus = slim_normalized_value($normalizedAdmission, 'main_member_status') === 'simultaneous'
        ? 'simultaneous'
        : 'existing';
    $mainMembership = slim_normalized_value($normalizedAdmission, 'main_membership');

    if ($mainMembership === 'weekend') {
        return [];
    }

    if ($useType === 'new') {
        $catalogKey = $course === 'double' || $course === 'pilates_plus'
            ? 'pilates_double_standalone'
            : 'pilates_basic_standalone';
        return [slim_operation_from_catalog($catalogKey, 1, $normalizedAdmission)];
    }

    if ($mainMemberStatus === 'existing') {
        return [slim_operation_from_catalog(slim_addon_catalog_key($addon), 1, $normalizedAdmission)];
    }

    $mainMap = [
        'find_master' => ['main_find_master'],
        'day_free' => ['main_day_free'],
        'night_holiday' => ['main_night_holiday'],
        'night_holiday_u34' => ['main_night_holiday_u34'],
        'gym_free' => ['main_gym_free'],
        'gym_pool' => ['main_gym_free', 'addon_pool_1'],
        'gym_studio' => ['main_gym_free', 'addon_studio_1'],
    ];

    $mainCatalogKeys = $mainMap[$mainMembership] ?? [];
    if ($mainCatalogKeys === []) {
        return [];
    }

    $catalogKeys = array_merge($mainCatalogKeys, [slim_addon_catalog_key($addon)]);
    $operations = [];
    foreach ($catalogKeys as $index => $catalogKey) {
        $operations[] = slim_operation_from_catalog($catalogKey, $index + 1, $normalizedAdmission);
    }

    return $operations;
}

function validate_slim_readiness(array $normalizedAdmission, array $operations, array $photo = []): array
{
    $errors = [];
    $warnings = [];

    $requiredFields = [
        'surname',
        'given_name',
        'surname_kana',
        'given_name_kana',
        'birth',
        'gender',
        'phone',
        'phone_type',
        'start_date',
        'actual_procedure_date',
    ];

    foreach ($requiredFields as $field) {
        if (slim_normalized_value($normalizedAdmission, $field) === '') {
            $errors[] = $field . '_missing';
        }
    }

    if (slim_normalized_value($normalizedAdmission, 'main_membership') === 'weekend') {
        $errors[] = 'legacy_weekend_plan';
    }

    if ($operations === []) {
        $errors[] = 'operations_missing';
    }

    $useType = slim_normalized_value($normalizedAdmission, 'use_type') === 'add' ? 'add' : 'new';
    $mainMemberStatus = slim_normalized_value($normalizedAdmission, 'main_member_status') === 'simultaneous'
        ? 'simultaneous'
        : 'existing';

    if ($useType === 'add' && $mainMemberStatus === 'existing' && slim_normalized_value($normalizedAdmission, 'main_member_number') === '') {
        $errors[] = 'main_member_number_missing';
    }

    if (empty($photo['archived_path']) && empty($photo['path'])) {
        $warnings[] = 'photo_missing';
    }

    return [
        'errors' => array_values(array_unique($errors)),
        'warnings' => array_values(array_unique($warnings)),
    ];
}

function slim_operations_with_readiness(array $normalizedAdmission, array $photo = []): array
{
    $operations = build_slim_operations($normalizedAdmission);
    $readiness = validate_slim_readiness($normalizedAdmission, $operations, $photo);
    $globalErrors = $readiness['errors'];
    $useType = slim_normalized_value($normalizedAdmission, 'use_type') === 'add' ? 'add' : 'new';
    $mainMemberStatus = slim_normalized_value($normalizedAdmission, 'main_member_status') === 'simultaneous'
        ? 'simultaneous'
        : 'existing';

    foreach ($operations as $index => $operation) {
        $operationErrors = $globalErrors;
        $isAddition = ($operation['operation_type'] ?? '') === 'addition_notification';

        if ($useType === 'add' && $mainMemberStatus === 'simultaneous' && $isAddition && slim_normalized_value($normalizedAdmission, 'slim_member_number') === '') {
            $operationErrors[] = 'slim_member_number_missing';
        }

        $operation['readiness_errors'] = array_values(array_unique($operationErrors));
        $operation['status'] = $operation['readiness_errors'] === [] && $index === 0 ? 'ready' : 'blocked';
        if ($operation['readiness_errors'] === [] && $index > 0) {
            $operation['status'] = 'pending';
        }
        $operations[$index] = $operation;
    }

    return $operations;
}

function slim_operation_plan_signature(array $operations): array
{
    return array_map(static function (array $operation): string {
        return implode(':', [
            (string)($operation['sequence_no'] ?? ''),
            (string)($operation['operation_type'] ?? ''),
            (string)($operation['page_type'] ?? ''),
            (string)($operation['course_id'] ?? ''),
            (string)($operation['course_code'] ?? ''),
        ]);
    }, $operations);
}

function slim_has_started_operations(array $operations): bool
{
    foreach ($operations as $operation) {
        if (slim_operation_is_started($operation)) {
            return true;
        }
    }
    return false;
}

function slim_operation_is_started(array $operation): bool
{
    $status = (string)($operation['status'] ?? '');
    if (in_array($status, ['in_progress', 'filled', 'completed'], true)) {
        return true;
    }

    return !empty($operation['started_at']) || !empty($operation['filled_at']) || !empty($operation['completed_at']);
}

function slim_should_block_operation_regeneration(array $existingOperations, array $newOperations): bool
{
    if (!slim_has_started_operations($existingOperations)) {
        return false;
    }

    return slim_operation_plan_signature($existingOperations) !== slim_operation_plan_signature($newOperations);
}

function slim_operation_progress(array $operations): array
{
    $total = count($operations);
    $completed = 0;
    $blocked = 0;
    $ready = 0;

    foreach ($operations as $operation) {
        $status = (string)($operation['status'] ?? '');
        if ($status === 'completed') {
            $completed++;
        } elseif ($status === 'ready') {
            $ready++;
        } elseif ($status === 'blocked' || $status === 'needs_review') {
            $blocked++;
        }
    }

    return [
        'total' => $total,
        'completed' => $completed,
        'ready' => $ready,
        'blocked' => $blocked,
        'incomplete' => max(0, $total - $completed),
    ];
}

function slim_status_from_operations(array $normalizedAdmission, array $operations): string
{
    if (slim_normalized_value($normalizedAdmission, 'main_membership') === 'weekend' || $operations === []) {
        return 'needs_review';
    }

    $progress = slim_operation_progress($operations);
    if ($progress['total'] > 0 && $progress['completed'] === $progress['total']) {
        return 'completed';
    }

    foreach ($operations as $operation) {
        if (in_array((string)($operation['status'] ?? ''), ['in_progress', 'filled'], true)) {
            return 'in_progress';
        }
    }

    return 'preparing';
}
