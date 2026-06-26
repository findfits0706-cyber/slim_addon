<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/schema.php';

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
    ];
}

function admission_record_from_db_row(array $row, array $photos = []): array
{
    $data = admission_json_decode($row['original_payload'] ?? null);
    $fees = admission_json_decode($row['fee_snapshot'] ?? null);
    $mailStatus = admission_json_decode($row['mail_status'] ?? null);
    $createdAt = (string)($row['created_at'] ?? '');
    $updatedAt = (string)($row['updated_at'] ?? '');
    $createdTs = strtotime($createdAt);
    $updatedTs = strtotime($updatedAt);

    return [
        'id' => (string)($row['application_id'] ?? ''),
        'status' => (string)($row['application_status'] ?? 'new'),
        'slim_status' => (string)($row['slim_status'] ?? 'not_started'),
        'created_at' => $createdAt,
        'created_at_ts' => $createdTs === false ? 0 : $createdTs,
        'updated_at' => $updatedAt,
        'updated_at_ts' => $updatedTs === false ? 0 : $updatedTs,
        'admin_note' => (string)($row['admin_note'] ?? ''),
        'mail_status' => $mailStatus,
        'photo' => $photos[0] ?? [],
        'data' => $data,
        'fees' => $fees,
    ];
}

function admission_fetch_photo_records(int $admissionId): array
{
    $stmt = db()->prepare(
        'SELECT storage_path, original_filename, mime_type, file_size, sha256, created_at
           FROM admission_photos
          WHERE admission_id = :admission_id
          ORDER BY id ASC'
    );
    $stmt->execute(['admission_id' => $admissionId]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
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

function admission_load_records_from_db(): array
{
    if (!admission_repository_ready()) {
        return [];
    }

    $stmt = db()->query('SELECT * FROM admissions ORDER BY created_at DESC, id DESC');
    $records = [];
    foreach ($stmt->fetchAll() as $row) {
        $photos = admission_fetch_photo_records((int)$row['id']);
        $records[] = admission_record_from_db_row($row, $photos);
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
    return admission_record_from_db_row($row, admission_fetch_photo_records((int)$row['id']));
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
    return admission_record_from_db_row($row, admission_fetch_photo_records((int)$row['id']));
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
        $normalized = admission_normalized_payload($data);

        $stmt = $pdo->prepare('SELECT id FROM admissions WHERE application_id = :application_id LIMIT 1');
        $stmt->execute(['application_id' => $applicationId]);
        $admissionId = (int)($stmt->fetchColumn() ?: 0);

        $params = [
            'application_id' => $applicationId,
            'idempotency_key' => $idempotencyKey,
            'application_status' => (string)($record['status'] ?? 'new'),
            'slim_status' => (string)($record['slim_status'] ?? 'not_started'),
            'use_type' => (string)($data['use_type'] ?? ''),
            'main_member_status' => (string)($data['main_member_status'] ?? ''),
            'main_member_number' => (string)($data['main_member_number'] ?? ''),
            'slim_member_number' => (string)($data['slim_member_number'] ?? ''),
            'actual_procedure_date' => admission_db_date($data['actual_procedure_date'] ?? null),
            'start_date' => admission_db_date($data['start_date'] ?? null),
            'course_key' => (string)($data['course'] ?? ''),
            'main_membership_key' => (string)($data['main_membership'] ?? ''),
            'addon_key' => (string)($data['addon'] ?? ''),
            'surname' => (string)($data['surname'] ?? ''),
            'given_name' => (string)($data['given_name'] ?? ''),
            'surname_kana' => (string)($data['surname_kana'] ?? ''),
            'given_name_kana' => (string)($data['given_name_kana'] ?? ''),
            'birth' => admission_db_date($data['birth'] ?? null),
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
            'original_payload' => admission_json_encode($data),
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
