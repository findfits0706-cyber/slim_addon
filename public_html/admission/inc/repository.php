<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/schema.php';
require_once __DIR__ . '/slim.php';

function admission_json_encode(array $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSONの生成に失敗しました。');
    }
    return $json;
}

function admission_json_decode(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function admission_repository_ready(): bool
{
    return db_table_exists_cached('admissions');
}

function admission_normalized_payload(array $data): array
{
    return [
        'use_type' => (string)($data['use_type'] ?? 'new'),
        'course' => (string)($data['course'] ?? ''),
        'main_member_status' => (string)($data['main_member_status'] ?? 'existing'),
        'main_member_number' => (string)($data['main_member_number'] ?? ''),
        'slim_member_number' => (string)($data['slim_member_number'] ?? ''),
        'actual_procedure_date' => (string)($data['actual_procedure_date'] ?? ''),
        'start_date' => (string)($data['start_date'] ?? ''),
        'main_membership' => (string)($data['main_membership'] ?? ''),
        'addon' => (string)($data['addon'] ?? ''),
        'surname' => (string)($data['surname'] ?? ''),
        'given_name' => (string)($data['given_name'] ?? ''),
        'surname_kana' => (string)($data['surname_kana'] ?? ''),
        'given_name_kana' => (string)($data['given_name_kana'] ?? ''),
        'birth' => (string)($data['birth'] ?? ''),
        'gender' => (string)($data['gender'] ?? ''),
        'phone_type' => (string)($data['phone_type'] ?? ''),
        'phone' => (string)($data['phone'] ?? ''),
        'email' => (string)($data['email'] ?? ''),
        'postal_code' => (string)($data['postal_code'] ?? ''),
        'prefecture' => (string)($data['prefecture'] ?? ''),
        'city_area' => (string)($data['city_area'] ?? ''),
        'street_address' => (string)($data['street_address'] ?? ''),
        'building' => (string)($data['building'] ?? ''),
        'emergency_name' => (string)($data['emergency_name'] ?? ''),
        'emergency_relationship' => (string)($data['emergency_relationship'] ?? ''),
        'emergency_phone' => (string)($data['emergency_phone'] ?? ''),
        'guardian_name' => (string)($data['guardian_name'] ?? ''),
        'name' => (string)($data['name'] ?? trim((string)($data['surname'] ?? '') . ' ' . (string)($data['given_name'] ?? ''))),
        'kana' => (string)($data['kana'] ?? trim((string)($data['surname_kana'] ?? '') . ' ' . (string)($data['given_name_kana'] ?? ''))),
    ];
}

function admission_record_from_db_row(array $row, array $photos = [], array $operations = []): array
{
    $data = admission_json_decode($row['original_payload'] ?? null);
    $normalized = admission_json_decode($row['normalized_payload'] ?? null);
    $normalized = array_merge(admission_normalized_payload($data), $normalized);
    $fees = admission_json_decode($row['fee_snapshot'] ?? null);
    $mailStatus = admission_json_decode($row['mail_status'] ?? null);
    $createdAt = (string)($row['created_at'] ?? '');
    $updatedAt = (string)($row['updated_at'] ?? '');
    $createdTs = strtotime($createdAt);
    $updatedTs = strtotime($updatedAt);
    $progress = slim_operation_progress($operations);
    $readiness = validate_slim_readiness($normalized, $operations, $photos[0] ?? []);

    return [
        'db_id' => (int)($row['id'] ?? 0),
        'id' => (string)($row['application_id'] ?? ''),
        'status' => (string)($row['application_status'] ?? 'new'),
        'slim_status' => (string)($row['slim_status'] ?? 'not_started'),
        'version' => (int)($row['version'] ?? 1),
        'created_at' => $createdAt,
        'created_at_ts' => $createdTs === false ? 0 : $createdTs,
        'updated_at' => $updatedAt,
        'updated_at_ts' => $updatedTs === false ? 0 : $updatedTs,
        'admin_note' => (string)($row['admin_note'] ?? ''),
        'mail_status' => $mailStatus,
        'photo' => $photos[0] ?? [],
        'data' => $data,
        'normalized' => $normalized,
        'fees' => $fees,
        'operations' => $operations,
        'operation_progress' => $progress,
        'readiness' => $readiness,
    ];
}

function admission_fetch_photo_records(int $admissionId): array
{
    $stmt = db()->prepare(
        'SELECT id, storage_path, original_filename, mime_type, file_size, sha256, created_at
           FROM admission_photos
          WHERE admission_id = :admission_id
          ORDER BY id ASC'
    );
    $stmt->execute(['admission_id' => $admissionId]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id' => (int)$row['id'],
            'ok' => true,
            'path' => (string)$row['storage_path'],
            'archived_path' => (string)$row['storage_path'],
            'filename' => (string)$row['original_filename'],
            'archived_filename' => (string)$row['original_filename'],
            'mime' => (string)$row['mime_type'],
            'size' => (int)$row['file_size'],
            'sha256' => (string)$row['sha256'],
            'created_at' => (string)$row['created_at'],
            'error' => '',
        ];
    }
    return $items;
}

function admission_slim_tables_ready(): bool
{
    return db_table_exists_cached('admission_slim_operations')
        && db_table_exists_cached('admission_slim_events');
}

function admission_fetch_slim_operations(int $admissionId, ?PDO $pdo = null): array
{
    if (!admission_slim_tables_ready()) {
        return [];
    }

    $pdo = $pdo ?: db();
    $stmt = $pdo->prepare(
        'SELECT *
           FROM admission_slim_operations
          WHERE admission_id = :admission_id
          ORDER BY sequence_no ASC, id ASC'
    );
    $stmt->execute(['admission_id' => $admissionId]);

    $operations = [];
    foreach ($stmt->fetchAll() as $row) {
        $operations[] = [
            'id' => (int)$row['id'],
            'admission_id' => (int)$row['admission_id'],
            'sequence_no' => (int)$row['sequence_no'],
            'operation_key' => (string)$row['operation_key'],
            'operation_type' => (string)$row['operation_type'],
            'page_type' => (string)$row['page_type'],
            'course_id' => (int)$row['course_id'],
            'course_code' => (string)$row['course_code'],
            'business_label' => (string)$row['business_label'],
            'slim_option_texts' => admission_json_decode($row['slim_option_texts'] ?? null),
            'reason_id' => $row['reason_id'] === null ? null : (string)$row['reason_id'],
            'reason_label' => $row['reason_label'] === null ? null : (string)$row['reason_label'],
            'payment_cycle' => $row['payment_cycle'] === null ? null : (string)$row['payment_cycle'],
            'payment_cycle_label' => $row['payment_cycle_label'] === null ? null : (string)$row['payment_cycle_label'],
            'application_date' => (string)($row['application_date'] ?? ''),
            'start_date' => (string)($row['start_date'] ?? ''),
            'status' => (string)$row['status'],
            'attempts' => (int)$row['attempts'],
            'last_error_code' => (string)($row['last_error_code'] ?? ''),
            'last_error_summary' => (string)($row['last_error_summary'] ?? ''),
            'readiness_errors' => admission_json_decode($row['readiness_errors'] ?? null),
            'started_at' => (string)($row['started_at'] ?? ''),
            'started_by' => $row['started_by'] === null ? null : (int)$row['started_by'],
            'filled_at' => (string)($row['filled_at'] ?? ''),
            'filled_by' => $row['filled_by'] === null ? null : (int)$row['filled_by'],
            'completed_at' => (string)($row['completed_at'] ?? ''),
            'completed_by' => $row['completed_by'] === null ? null : (int)$row['completed_by'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ];
    }

    return $operations;
}

function admission_insert_slim_event(PDO $pdo, int $admissionId, ?int $operationId, ?int $actorAdminId, string $action, array $result = []): void
{
    if (!admission_slim_tables_ready()) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admission_slim_events (
            request_id, admission_id, operation_id, actor_admin_id,
            extension_installation_id, action, result_json, page_profile_version, created_at
         ) VALUES (
            :request_id, :admission_id, :operation_id, :actor_admin_id,
            NULL, :action, :result_json, NULL, NOW()
         )'
    );
    $stmt->execute([
        'request_id' => 'admin-' . bin2hex(random_bytes(16)),
        'admission_id' => $admissionId,
        'operation_id' => $operationId,
        'actor_admin_id' => $actorAdminId,
        'action' => $action,
        'result_json' => admission_json_encode($result),
    ]);
}

function admission_insert_slim_operation(PDO $pdo, int $admissionId, array $operation): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO admission_slim_operations (
            admission_id, sequence_no, operation_key, operation_type, page_type,
            course_id, course_code, business_label, slim_option_texts,
            reason_id, reason_label, payment_cycle, payment_cycle_label,
            application_date, start_date, status, readiness_errors,
            attempts, created_at, updated_at
         ) VALUES (
            :admission_id, :sequence_no, :operation_key, :operation_type, :page_type,
            :course_id, :course_code, :business_label, :slim_option_texts,
            :reason_id, :reason_label, :payment_cycle, :payment_cycle_label,
            :application_date, :start_date, :status, :readiness_errors,
            0, NOW(), NOW()
         )'
    );
    $stmt->execute([
        'admission_id' => $admissionId,
        'sequence_no' => (int)$operation['sequence_no'],
        'operation_key' => (string)$operation['operation_key'],
        'operation_type' => (string)$operation['operation_type'],
        'page_type' => (string)$operation['page_type'],
        'course_id' => (int)$operation['course_id'],
        'course_code' => (string)$operation['course_code'],
        'business_label' => (string)$operation['business_label'],
        'slim_option_texts' => admission_json_encode(is_array($operation['slim_option_texts'] ?? null) ? $operation['slim_option_texts'] : []),
        'reason_id' => $operation['reason_id'] ?? null,
        'reason_label' => $operation['reason_label'] ?? null,
        'payment_cycle' => $operation['payment_cycle'] ?? null,
        'payment_cycle_label' => $operation['payment_cycle_label'] ?? null,
        'application_date' => admission_db_date($operation['application_date'] ?? null),
        'start_date' => admission_db_date($operation['start_date'] ?? null),
        'status' => (string)$operation['status'],
        'readiness_errors' => admission_json_encode(is_array($operation['readiness_errors'] ?? null) ? $operation['readiness_errors'] : []),
    ]);
}

function admission_sync_slim_operations(PDO $pdo, int $admissionId, array $normalized, array $record = []): array
{
    if (!admission_slim_tables_ready()) {
        return [];
    }

    $photo = is_array($record['photo'] ?? null) ? $record['photo'] : [];
    $actorAdminId = isset($record['actor_admin_id']) ? (int)$record['actor_admin_id'] : null;
    $newOperations = slim_operations_with_readiness($normalized, $photo);
    $existingOperations = admission_fetch_slim_operations($admissionId, $pdo);

    if (slim_should_block_operation_regeneration($existingOperations, $newOperations)) {
        admission_insert_slim_event($pdo, $admissionId, null, $actorAdminId, 'operations_regeneration_blocked', [
            'existing_count' => count($existingOperations),
            'new_count' => count($newOperations),
        ]);
        $pdo->prepare(
            "UPDATE admissions
                SET slim_status = 'needs_review',
                    updated_at = NOW()
              WHERE id = :id"
        )->execute(['id' => $admissionId]);
        return $existingOperations;
    }

    $preservedSequences = [];
    if (slim_has_started_operations($existingOperations)) {
        foreach ($existingOperations as $operation) {
            if (slim_operation_is_started($operation)) {
                $preservedSequences[(int)$operation['sequence_no']] = true;
            }
        }

        $pdo->prepare(
            "DELETE FROM admission_slim_operations
              WHERE admission_id = :admission_id
                AND status NOT IN ('in_progress', 'filled', 'completed')
                AND started_at IS NULL
                AND filled_at IS NULL
                AND completed_at IS NULL"
        )->execute(['admission_id' => $admissionId]);
    } else {
        $pdo->prepare('DELETE FROM admission_slim_operations WHERE admission_id = :admission_id')->execute(['admission_id' => $admissionId]);
    }

    foreach ($newOperations as $operation) {
        if (isset($preservedSequences[(int)$operation['sequence_no']])) {
            continue;
        }
        admission_insert_slim_operation($pdo, $admissionId, $operation);
    }

    $syncedOperations = admission_fetch_slim_operations($admissionId, $pdo);

    $status = slim_status_from_operations($normalized, $syncedOperations);
    $pdo->prepare(
        'UPDATE admissions
            SET slim_status = :slim_status,
                updated_at = NOW()
          WHERE id = :id'
    )->execute([
        'slim_status' => $status,
        'id' => $admissionId,
    ]);

    admission_insert_slim_event($pdo, $admissionId, null, $actorAdminId, 'operations_regenerated', [
        'operation_count' => count($syncedOperations),
        'preserved_count' => count($preservedSequences),
        'blocked_count' => slim_operation_progress($syncedOperations)['blocked'],
        'slim_status' => $status,
    ]);

    return $syncedOperations;
}

function admission_load_records_from_db(): array
{
    if (!admission_repository_ready()) {
        return [];
    }

    $stmt = db()->query('SELECT * FROM admissions ORDER BY created_at DESC, id DESC');
    $records = [];
    foreach ($stmt->fetchAll() as $row) {
        $admissionId = (int)$row['id'];
        $photos = admission_fetch_photo_records($admissionId);
        $operations = admission_fetch_slim_operations($admissionId);
        $records[] = admission_record_from_db_row($row, $photos, $operations);
    }
    return $records;
}

function admission_find_record_by_application_id(string $applicationId): ?array
{
    if (!admission_repository_ready()) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM admissions WHERE application_id = :application_id LIMIT 1');
    $stmt->execute(['application_id' => $applicationId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $admissionId = (int)$row['id'];
    return admission_record_from_db_row($row, admission_fetch_photo_records($admissionId), admission_fetch_slim_operations($admissionId));
}

function admission_find_record_by_idempotency_key(string $idempotencyKey): ?array
{
    if ($idempotencyKey === '' || !admission_repository_ready()) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM admissions WHERE idempotency_key = :idempotency_key LIMIT 1');
    $stmt->execute(['idempotency_key' => $idempotencyKey]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $admissionId = (int)$row['id'];
    return admission_record_from_db_row($row, admission_fetch_photo_records($admissionId), admission_fetch_slim_operations($admissionId));
}

function admission_db_date(?string $date): ?string
{
    return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

function admission_db_datetime(?string $datetime): ?string
{
    if (!$datetime) {
        return null;
    }
    $timestamp = strtotime($datetime);
    return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
}

function admission_save_record_to_db(array $record, ?string $idempotencyKey = null): array
{
    if (!admission_repository_ready()) {
        throw new RuntimeException('入会申込用のMySQLテーブルが見つかりません。マイグレーションを反映してください。');
    }

    $existing = null;
    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $existing = admission_find_record_by_idempotency_key($idempotencyKey);
    }
    if ($existing !== null) {
        return $existing;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $applicationId = (string)$record['id'];
        $data = is_array($record['data'] ?? null) ? $record['data'] : [];
        $fees = is_array($record['fees'] ?? null) ? $record['fees'] : [];
        $photo = is_array($record['photo'] ?? null) ? $record['photo'] : [];
        $mailStatus = is_array($record['mail_status'] ?? null) ? $record['mail_status'] : [];
        $normalized = is_array($record['normalized'] ?? null)
            ? array_merge(admission_normalized_payload($data), $record['normalized'])
            : admission_normalized_payload($data);

        $stmt = $pdo->prepare('SELECT id, original_payload FROM admissions WHERE application_id = :application_id LIMIT 1');
        $stmt->execute(['application_id' => $applicationId]);
        $existingRow = $stmt->fetch();
        $admissionId = (int)($existingRow['id'] ?? 0);
        $existingOriginal = admission_json_decode($existingRow['original_payload'] ?? null);
        $originalData = is_array($record['original_data'] ?? null)
            ? $record['original_data']
            : ($admissionId > 0 && $existingOriginal !== [] ? $existingOriginal : $data);
        $columnData = array_merge($data, $normalized);

        $params = [
            'application_id' => $applicationId,
            'idempotency_key' => $idempotencyKey,
            'application_status' => (string)($record['status'] ?? 'new'),
            'slim_status' => (string)($record['slim_status'] ?? 'not_started'),
            'use_type' => (string)($columnData['use_type'] ?? ''),
            'main_member_status' => (string)($columnData['main_member_status'] ?? ''),
            'main_member_number' => (string)($columnData['main_member_number'] ?? ''),
            'slim_member_number' => (string)($columnData['slim_member_number'] ?? ''),
            'actual_procedure_date' => admission_db_date($columnData['actual_procedure_date'] ?? null),
            'start_date' => admission_db_date($columnData['start_date'] ?? null),
            'course_key' => (string)($columnData['course'] ?? ''),
            'main_membership_key' => (string)($columnData['main_membership'] ?? ''),
            'addon_key' => (string)($columnData['addon'] ?? ''),
            'surname' => (string)($columnData['surname'] ?? ''),
            'given_name' => (string)($columnData['given_name'] ?? ''),
            'surname_kana' => (string)($columnData['surname_kana'] ?? ''),
            'given_name_kana' => (string)($columnData['given_name_kana'] ?? ''),
            'birth' => admission_db_date($columnData['birth'] ?? null),
            'gender' => (string)($columnData['gender'] ?? ''),
            'phone_type' => (string)($columnData['phone_type'] ?? ''),
            'phone' => (string)($columnData['phone'] ?? ''),
            'email' => (string)($columnData['email'] ?? ''),
            'postal_code' => (string)($columnData['postal_code'] ?? ''),
            'prefecture' => (string)($columnData['prefecture'] ?? ''),
            'city_area' => (string)($columnData['city_area'] ?? ''),
            'street_address' => (string)($columnData['street_address'] ?? ''),
            'building' => (string)($columnData['building'] ?? ''),
            'emergency_name' => (string)($columnData['emergency_name'] ?? ''),
            'emergency_relationship' => (string)($columnData['emergency_relationship'] ?? ''),
            'emergency_phone' => (string)($columnData['emergency_phone'] ?? ''),
            'guardian_name' => (string)($columnData['guardian_name'] ?? ''),
            'original_payload' => admission_json_encode($originalData),
            'normalized_payload' => admission_json_encode($normalized),
            'fee_snapshot' => admission_json_encode($fees),
            'mail_status' => admission_json_encode($mailStatus),
            'admin_note' => (string)($record['admin_note'] ?? ''),
            'created_at' => admission_db_datetime($record['created_at'] ?? null) ?: date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($admissionId > 0) {
            $params['id'] = $admissionId;
            $update = $pdo->prepare(
                'UPDATE admissions
                    SET application_status = :application_status,
                        slim_status = :slim_status,
                        use_type = :use_type,
                        main_member_status = :main_member_status,
                        main_member_number = :main_member_number,
                        slim_member_number = :slim_member_number,
                        actual_procedure_date = :actual_procedure_date,
                        start_date = :start_date,
                        course_key = :course_key,
                        main_membership_key = :main_membership_key,
                        addon_key = :addon_key,
                        surname = :surname,
                        given_name = :given_name,
                        surname_kana = :surname_kana,
                        given_name_kana = :given_name_kana,
                        birth = :birth,
                        gender = :gender,
                        phone_type = :phone_type,
                        phone = :phone,
                        email = :email,
                        postal_code = :postal_code,
                        prefecture = :prefecture,
                        city_area = :city_area,
                        street_address = :street_address,
                        building = :building,
                        emergency_name = :emergency_name,
                        emergency_relationship = :emergency_relationship,
                        emergency_phone = :emergency_phone,
                        guardian_name = :guardian_name,
                        original_payload = :original_payload,
                        normalized_payload = :normalized_payload,
                        fee_snapshot = :fee_snapshot,
                        mail_status = :mail_status,
                        admin_note = :admin_note,
                        version = version + 1,
                        updated_at = :updated_at
                  WHERE id = :id'
            );
            $updateParams = $params;
            unset($updateParams['application_id'], $updateParams['idempotency_key'], $updateParams['created_at']);
            $updateParams['id'] = $admissionId;
            $update->execute($updateParams);
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO admissions (
                    application_id, idempotency_key, application_status, slim_status,
                    use_type, main_member_status, main_member_number, slim_member_number,
                    actual_procedure_date, start_date, course_key, main_membership_key, addon_key,
                    surname, given_name, surname_kana, given_name_kana, birth, gender,
                    phone_type, phone, email, postal_code, prefecture, city_area, street_address, building,
                    emergency_name, emergency_relationship, emergency_phone, guardian_name,
                    original_payload, normalized_payload, fee_snapshot, mail_status, admin_note,
                    created_at, updated_at
                 ) VALUES (
                    :application_id, :idempotency_key, :application_status, :slim_status,
                    :use_type, :main_member_status, :main_member_number, :slim_member_number,
                    :actual_procedure_date, :start_date, :course_key, :main_membership_key, :addon_key,
                    :surname, :given_name, :surname_kana, :given_name_kana, :birth, :gender,
                    :phone_type, :phone, :email, :postal_code, :prefecture, :city_area, :street_address, :building,
                    :emergency_name, :emergency_relationship, :emergency_phone, :guardian_name,
                    :original_payload, :normalized_payload, :fee_snapshot, :mail_status, :admin_note,
                    :created_at, :updated_at
                 )'
            );
            $insert->execute($params);
            $admissionId = (int)$pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM admission_sensitive WHERE admission_id = :admission_id')->execute(['admission_id' => $admissionId]);
        $sensitive = $pdo->prepare(
            'INSERT INTO admission_sensitive (admission_id, health_payload, terms_snapshot, created_at, updated_at)
             VALUES (:admission_id, :health_payload, :terms_snapshot, NOW(), NOW())'
        );
        $sensitive->execute([
            'admission_id' => $admissionId,
            'health_payload' => admission_json_encode([
                'health_checks' => $data['health_checks'] ?? [],
                'medical_memo' => (string)($data['medical_memo'] ?? ''),
            ]),
            'terms_snapshot' => admission_json_encode([
                'terms_agree' => (string)($data['terms_agree'] ?? ''),
                'agreed_at' => date('Y-m-d H:i:s'),
            ]),
        ]);

        $pdo->prepare('DELETE FROM admission_preferences WHERE admission_id = :admission_id')->execute(['admission_id' => $admissionId]);
        $prefInsert = $pdo->prepare(
            'INSERT INTO admission_preferences (admission_id, preference_order, preferred_date, preferred_time)
             VALUES (:admission_id, :preference_order, :preferred_date, :preferred_time)'
        );
        for ($i = 1; $i <= 3; $i++) {
            $date = admission_db_date($data['procedure_date_' . $i] ?? null);
            $time = (string)($data['procedure_time_' . $i] ?? '');
            if ($date === null && $time === '') {
                continue;
            }
            $prefInsert->execute([
                'admission_id' => $admissionId,
                'preference_order' => $i,
                'preferred_date' => $date,
                'preferred_time' => $time,
            ]);
        }

        $pdo->prepare('DELETE FROM admission_photos WHERE admission_id = :admission_id')->execute(['admission_id' => $admissionId]);
        $photoPath = (string)($photo['archived_path'] ?? $photo['path'] ?? '');
        if ($photoPath !== '') {
            $fileSize = is_file($photoPath) ? (int)filesize($photoPath) : (int)($photo['size'] ?? 0);
            $sha256 = is_file($photoPath) ? hash_file('sha256', $photoPath) : (string)($photo['sha256'] ?? '');
            $photoInsert = $pdo->prepare(
                'INSERT INTO admission_photos (admission_id, storage_path, original_filename, mime_type, file_size, sha256, created_at)
                 VALUES (:admission_id, :storage_path, :original_filename, :mime_type, :file_size, :sha256, NOW())'
            );
            $photoInsert->execute([
                'admission_id' => $admissionId,
                'storage_path' => $photoPath,
                'original_filename' => (string)($photo['filename'] ?? basename($photoPath)),
                'mime_type' => (string)($photo['mime'] ?? 'application/octet-stream'),
                'file_size' => $fileSize,
                'sha256' => $sha256,
            ]);
        }

        admission_sync_slim_operations($pdo, $admissionId, $normalized, array_merge($record, [
            'photo' => $photo,
        ]));

        $pdo->commit();
        return admission_find_record_by_application_id($applicationId) ?? $record;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function admission_update_mail_status(string $applicationId, array $mailStatus): void
{
    if (!admission_repository_ready()) {
        return;
    }

    $stmt = db()->prepare(
        'UPDATE admissions
            SET mail_status = :mail_status,
                updated_at = NOW()
          WHERE application_id = :application_id'
    );
    $stmt->execute([
        'application_id' => $applicationId,
        'mail_status' => admission_json_encode($mailStatus),
    ]);
}
