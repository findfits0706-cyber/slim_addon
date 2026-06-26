<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../admission/inc/repository.php';

final class ExtensionApiException extends RuntimeException
{
    public int $status;
    public string $apiCode;

    public function __construct(int $status, string $apiCode, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->apiCode = $apiCode;
    }
}

function extension_config(): array
{
    $defaults = [
        'api_base_path' => '/api/v1/extension',
        'pairing_code_ttl_seconds' => 300,
        'access_token_ttl_seconds' => 8 * 60 * 60,
        'lock_ttl_seconds' => 600,
        'photo_token_ttl_seconds' => 300,
        'allowed_origins' => [],
        'transfer_enabled' => false,
        'json_body_max_bytes' => 65536,
        'pairing_attempt_window_seconds' => 600,
        'pairing_attempt_limit' => 10,
    ];

    $private = $GLOBALS['privateConfig']['extension'] ?? [];
    if (!is_array($private)) {
        $private = [];
    }

    return array_merge($defaults, $private);
}

function extension_tables_ready(): bool
{
    foreach ([
        'extension_pairing_codes',
        'extension_pairing_attempts',
        'extension_access_tokens',
        'extension_photo_tokens',
        'extension_api_events',
        'admission_locks',
    ] as $tableName) {
        if (!db_table_exists_cached($tableName)) {
            return false;
        }
    }

    return admission_repository_ready() && admission_slim_tables_ready();
}

function extension_fail(int $status, string $code, string $message): never
{
    throw new ExtensionApiException($status, $code, $message);
}

function extension_hash_secret(string $value): string
{
    return hash('sha256', $value);
}

function extension_base64url_encode(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function extension_now_sql(): string
{
    return date('Y-m-d H:i:s');
}

function extension_sql_after(int $seconds): string
{
    return date('Y-m-d H:i:s', time() + $seconds);
}

function extension_iso_datetime(?string $sqlDateTime): string
{
    if ($sqlDateTime === null || trim($sqlDateTime) === '') {
        return '';
    }

    $timestamp = strtotime($sqlDateTime);
    return $timestamp === false ? '' : date(DATE_ATOM, $timestamp);
}

function extension_request_id(): string
{
    $header = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
    if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $header)) {
        return $header;
    }

    return 'ext-' . bin2hex(random_bytes(16));
}

function extension_send_base_headers(): void
{
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowedOrigins = extension_config()['allowed_origins'] ?? [];
    if ($origin !== '' && is_array($allowedOrigins) && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Request-ID, X-Extension-Installation-Id');
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    }
}

function extension_send_json(int $status, array $payload): never
{
    if (!headers_sent()) {
        http_response_code($status);
        extension_send_base_headers();
        header('Content-Type: application/json; charset=UTF-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function extension_success(string $requestId, array $data = [], int $status = 200): never
{
    extension_send_json($status, [
        'ok' => true,
        'request_id' => $requestId,
        'data' => $data,
    ]);
}

function extension_error(string $requestId, int $status, string $code, string $message): never
{
    extension_send_json($status, [
        'ok' => false,
        'request_id' => $requestId,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ]);
}

function extension_read_json_body(): array
{
    $maxBytes = (int)(extension_config()['json_body_max_bytes'] ?? 65536);
    $body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($body === false) {
        extension_fail(400, 'invalid_body', 'Request body could not be read.');
    }
    if (strlen($body) > $maxBytes) {
        extension_fail(413, 'body_too_large', 'Request body is too large.');
    }
    if (trim($body) === '') {
        return [];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        extension_fail(400, 'invalid_json', 'Request body must be JSON object.');
    }

    return $decoded;
}

function extension_normalize_pairing_code(string $code): string
{
    return strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $code));
}

function extension_generate_pairing_code(): array
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $normalized = '';
    for ($i = 0; $i < 8; $i++) {
        $normalized .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return [
        'display_code' => substr($normalized, 0, 4) . '-' . substr($normalized, 4, 4),
        'normalized_code' => $normalized,
    ];
}

function extension_generate_secret_token(): string
{
    return 'fpslim_' . extension_base64url_encode(random_bytes(32));
}

function extension_safe_installation_id(string $installationId): string
{
    $installationId = trim($installationId);
    if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $installationId)) {
        return '';
    }

    return $installationId;
}

function extension_safe_version(string $version): string
{
    $version = trim($version);
    if ($version === '') {
        return '';
    }

    return substr(preg_replace('/[^A-Za-z0-9._:+-]/', '', $version) ?? '', 0, 64);
}

function extension_mask_installation_id(string $installationId): string
{
    $suffix = substr($installationId, -8);
    return $suffix === '' ? '' : '...' . $suffix;
}

function extension_admin_display_name(array $row): string
{
    $displayName = trim((string)($row['display_name'] ?? ''));
    return $displayName !== '' ? $displayName : (string)($row['username'] ?? '');
}

function extension_requester_hash(string $installationId = ''): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return extension_hash_secret($ip . '|' . $installationId);
}

function extension_pairing_rate_limited(PDO $pdo, string $requesterHash): bool
{
    $windowSeconds = (int)(extension_config()['pairing_attempt_window_seconds'] ?? 600);
    $limit = (int)(extension_config()['pairing_attempt_limit'] ?? 10);
    $since = extension_sql_after(-1 * max(60, $windowSeconds));

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
           FROM extension_pairing_attempts
          WHERE requester_hash = :requester_hash
            AND success = 0
            AND attempted_at >= :since'
    );
    $stmt->execute([
        'requester_hash' => $requesterHash,
        'since' => $since,
    ]);

    return (int)$stmt->fetchColumn() >= $limit;
}

function extension_record_pairing_attempt(PDO $pdo, string $requesterHash, bool $success): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO extension_pairing_attempts (requester_hash, success, attempted_at)
         VALUES (:requester_hash, :success, NOW())'
    );
    $stmt->execute([
        'requester_hash' => $requesterHash,
        'success' => $success ? 1 : 0,
    ]);
}

function extension_insert_api_event(PDO $pdo, string $requestId, ?int $adminUserId, ?string $installationId, string $action, array $result = []): void
{
    if (!db_table_exists_cached('extension_api_events')) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO extension_api_events (
                request_id, admin_user_id, installation_id, action, result_json, created_at
             ) VALUES (
                :request_id, :admin_user_id, :installation_id, :action, :result_json, NOW()
             )'
        );
        $stmt->execute([
            'request_id' => $requestId,
            'admin_user_id' => $adminUserId,
            'installation_id' => $installationId,
            'action' => $action,
            'result_json' => admission_json_encode($result),
        ]);
    } catch (Throwable $e) {
        error_log('Extension API event failed: ' . $e->getMessage());
    }
}

function extension_create_pairing_code(int $adminUserId): array
{
    if (!extension_tables_ready()) {
        extension_fail(503, 'extension_tables_missing', 'Extension API migration has not been applied.');
    }

    $pairing = extension_generate_pairing_code();
    $expiresAt = extension_sql_after((int)extension_config()['pairing_code_ttl_seconds']);
    $requestId = 'admin-' . bin2hex(random_bytes(16));
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO extension_pairing_codes (code_hash, admin_user_id, requester_hash, expires_at, created_at)
         VALUES (:code_hash, :admin_user_id, NULL, :expires_at, NOW())'
    );
    $stmt->execute([
        'code_hash' => extension_hash_secret($pairing['normalized_code']),
        'admin_user_id' => $adminUserId,
        'expires_at' => $expiresAt,
    ]);

    extension_insert_api_event($pdo, $requestId, $adminUserId, null, 'pairing_code_issued', [
        'expires_at' => $expiresAt,
    ]);

    return [
        'display_code' => $pairing['display_code'],
        'expires_at' => $expiresAt,
    ];
}

function extension_revoke_access_token(int $tokenId, int $adminUserId): bool
{
    if (!extension_tables_ready()) {
        return false;
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'UPDATE extension_access_tokens
            SET revoked_at = NOW()
          WHERE id = :id
            AND revoked_at IS NULL'
    );
    $stmt->execute(['id' => $tokenId]);
    $changed = $stmt->rowCount() > 0;
    if ($changed) {
        extension_insert_api_event($pdo, 'admin-' . bin2hex(random_bytes(16)), $adminUserId, null, 'access_token_revoked', [
            'token_id' => $tokenId,
        ]);
    }

    return $changed;
}

function extension_revoke_all_access_tokens(int $adminUserId): int
{
    if (!extension_tables_ready()) {
        return 0;
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'UPDATE extension_access_tokens
            SET revoked_at = NOW()
          WHERE revoked_at IS NULL
            AND expires_at > NOW()'
    );
    $stmt->execute();
    $count = $stmt->rowCount();
    extension_insert_api_event($pdo, 'admin-' . bin2hex(random_bytes(16)), $adminUserId, null, 'all_access_tokens_revoked', [
        'count' => $count,
    ]);

    return $count;
}

function extension_active_access_tokens(): array
{
    if (!extension_tables_ready()) {
        return [];
    }

    $stmt = db()->query(
        'SELECT t.id, t.admin_user_id, t.installation_id, t.extension_version, t.expires_at,
                t.last_used_at, t.created_at, u.username, u.display_name
           FROM extension_access_tokens t
           LEFT JOIN admin_users u ON u.id = t.admin_user_id
          WHERE t.revoked_at IS NULL
            AND t.expires_at > NOW()
          ORDER BY t.created_at DESC'
    );

    $tokens = [];
    foreach ($stmt->fetchAll() as $row) {
        $tokens[] = [
            'id' => (int)$row['id'],
            'admin_user_id' => (int)$row['admin_user_id'],
            'staff_display_name' => extension_admin_display_name($row),
            'installation_id_masked' => extension_mask_installation_id((string)$row['installation_id']),
            'extension_version' => (string)$row['extension_version'],
            'expires_at' => (string)$row['expires_at'],
            'last_used_at' => (string)($row['last_used_at'] ?? ''),
            'created_at' => (string)$row['created_at'],
        ];
    }

    return $tokens;
}

function extension_pair(array $input, string $requestId): array
{
    if (!extension_tables_ready()) {
        extension_fail(503, 'extension_tables_missing', 'Extension API migration has not been applied.');
    }
    if (empty(extension_config()['transfer_enabled'])) {
        extension_fail(403, 'extension_disabled', 'Extension transfer API is disabled.');
    }

    $pairingCode = extension_normalize_pairing_code((string)($input['pairing_code'] ?? ''));
    $installationId = extension_safe_installation_id((string)($input['installation_id'] ?? ''));
    $extensionVersion = extension_safe_version((string)($input['extension_version'] ?? ''));
    if ($pairingCode === '' || $installationId === '') {
        extension_fail(400, 'invalid_pairing_request', 'pairing_code and installation_id are required.');
    }

    $pdo = db();
    $requesterHash = extension_requester_hash($installationId);
    if (extension_pairing_rate_limited($pdo, $requesterHash)) {
        extension_fail(429, 'pairing_rate_limited', 'Too many pairing attempts.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT c.*, u.username, u.display_name
               FROM extension_pairing_codes c
               LEFT JOIN admin_users u ON u.id = c.admin_user_id
              WHERE c.code_hash = :code_hash
              LIMIT 1
              FOR UPDATE'
        );
        $stmt->execute(['code_hash' => extension_hash_secret($pairingCode)]);
        $codeRow = $stmt->fetch();
        if (!$codeRow || !empty($codeRow['used_at']) || strtotime((string)$codeRow['expires_at']) < time()) {
            extension_record_pairing_attempt($pdo, $requesterHash, false);
            $pdo->commit();
            extension_fail(401, 'invalid_pairing_code', 'Pairing code is invalid, expired, or already used.');
        }

        $accessToken = extension_generate_secret_token();
        $expiresAt = extension_sql_after((int)extension_config()['access_token_ttl_seconds']);

        $tokenStmt = $pdo->prepare(
            'INSERT INTO extension_access_tokens (
                token_hash, admin_user_id, installation_id, extension_version, expires_at, created_at
             ) VALUES (
                :token_hash, :admin_user_id, :installation_id, :extension_version, :expires_at, NOW()
             )'
        );
        $tokenStmt->execute([
            'token_hash' => extension_hash_secret($accessToken),
            'admin_user_id' => (int)$codeRow['admin_user_id'],
            'installation_id' => $installationId,
            'extension_version' => $extensionVersion,
            'expires_at' => $expiresAt,
        ]);

        $pdo->prepare('UPDATE extension_pairing_codes SET used_at = NOW(), requester_hash = :requester_hash WHERE id = :id')->execute([
            'requester_hash' => $requesterHash,
            'id' => (int)$codeRow['id'],
        ]);
        extension_record_pairing_attempt($pdo, $requesterHash, true);
        extension_insert_api_event($pdo, $requestId, (int)$codeRow['admin_user_id'], $installationId, 'paired', [
            'extension_version' => $extensionVersion,
            'expires_at' => $expiresAt,
        ]);
        $pdo->commit();

        return [
            'access_token' => $accessToken,
            'expires_at' => extension_iso_datetime($expiresAt),
            'staff' => [
                'display_name' => extension_admin_display_name($codeRow),
            ],
        ];
    } catch (ExtensionApiException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function extension_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function extension_installation_header(): string
{
    return extension_safe_installation_id((string)($_SERVER['HTTP_X_EXTENSION_INSTALLATION_ID'] ?? ''));
}

function extension_authenticate(): array
{
    if (!extension_tables_ready()) {
        extension_fail(503, 'extension_tables_missing', 'Extension API migration has not been applied.');
    }
    if (empty(extension_config()['transfer_enabled'])) {
        extension_fail(403, 'extension_disabled', 'Extension transfer API is disabled.');
    }

    $token = extension_bearer_token();
    $installationId = extension_installation_header();
    if ($token === '' || $installationId === '') {
        extension_fail(401, 'unauthorized', 'Bearer token and installation header are required.');
    }

    $stmt = db()->prepare(
        'SELECT t.*, u.username, u.display_name
           FROM extension_access_tokens t
           LEFT JOIN admin_users u ON u.id = t.admin_user_id
          WHERE t.token_hash = :token_hash
          LIMIT 1'
    );
    $stmt->execute(['token_hash' => extension_hash_secret($token)]);
    $row = $stmt->fetch();
    if (!$row || !empty($row['revoked_at']) || strtotime((string)$row['expires_at']) < time()) {
        extension_fail(401, 'token_expired_or_revoked', 'Token is expired or revoked.');
    }
    if (!hash_equals((string)$row['installation_id'], $installationId)) {
        extension_fail(401, 'installation_mismatch', 'Token does not belong to this installation.');
    }

    $lastUsedAt = strtotime((string)($row['last_used_at'] ?? ''));
    if ($lastUsedAt === false || $lastUsedAt < time() - 300) {
        db()->prepare('UPDATE extension_access_tokens SET last_used_at = NOW() WHERE id = :id')->execute(['id' => (int)$row['id']]);
    }

    return [
        'token_id' => (int)$row['id'],
        'admin_user_id' => (int)$row['admin_user_id'],
        'staff_display_name' => extension_admin_display_name($row),
        'installation_id' => (string)$row['installation_id'],
        'extension_version' => (string)$row['extension_version'],
        'expires_at' => (string)$row['expires_at'],
    ];
}

function extension_me(array $auth): array
{
    return [
        'staff' => [
            'display_name' => $auth['staff_display_name'],
        ],
        'installation_id_masked' => extension_mask_installation_id((string)$auth['installation_id']),
        'extension_version' => (string)$auth['extension_version'],
        'expires_at' => extension_iso_datetime((string)$auth['expires_at']),
        'api_version' => '2026-06-26.prompt3',
    ];
}

function extension_operation_progress_for_admission(int $admissionId): array
{
    return slim_operation_progress(admission_fetch_slim_operations($admissionId));
}

function extension_admission_list_item(array $row, array $operations = []): array
{
    return [
        'application_id' => (string)$row['application_id'],
        'submitted_at' => extension_iso_datetime((string)$row['created_at']),
        'slim_status' => (string)$row['slim_status'],
        'operation_progress' => slim_operation_progress($operations),
        'version' => (int)$row['version'],
    ];
}

function extension_list_admissions(array $query): array
{
    $scope = (string)($query['scope'] ?? 'unregistered');
    $page = max(1, (int)($query['page'] ?? 1));
    $limit = min(50, max(1, (int)($query['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;
    $params = [];
    $where = [];

    if ($scope === 'today') {
        $where[] = 'DATE(created_at) = CURDATE()';
    } elseif ($scope === 'in_progress') {
        $where[] = "slim_status IN ('preparing', 'in_progress', 'needs_review')";
    } elseif ($scope === 'search') {
        $q = trim((string)($query['q'] ?? ''));
        if ($q === '') {
            extension_fail(400, 'search_query_required', 'q is required when scope=search.');
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $searchColumns = ['application_id', 'surname', 'given_name', 'surname_kana', 'given_name_kana', 'phone'];
        $searchWhere = [];
        foreach ($searchColumns as $index => $column) {
            $param = 'q' . $index;
            $params[$param] = $like;
            $searchWhere[] = $column . ' LIKE :' . $param;
        }
        $where[] = '(' . implode(' OR ', $searchWhere) . ')';
    } else {
        $scope = 'unregistered';
        $where[] = "slim_status <> 'completed'";
    }

    $sql = 'SELECT * FROM admissions';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . ($limit + 1) . ' OFFSET ' . $offset;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $hasMore = count($rows) > $limit;
    $rows = array_slice($rows, 0, $limit);

    $items = [];
    foreach ($rows as $row) {
        $operations = admission_fetch_slim_operations((int)$row['id']);
        $items[] = extension_admission_list_item($row, $operations);
    }

    return [
        'scope' => $scope,
        'page' => $page,
        'items' => $items,
        'has_more' => $hasMore,
    ];
}

function extension_transfer_field_keys(): array
{
    return [
        'use_type',
        'course',
        'main_member_status',
        'main_member_number',
        'slim_member_number',
        'actual_procedure_date',
        'start_date',
        'main_membership',
        'addon',
        'surname',
        'given_name',
        'surname_kana',
        'given_name_kana',
        'birth',
        'gender',
        'phone_type',
        'phone',
        'email',
        'postal_code',
        'prefecture',
        'city_area',
        'street_address',
        'building',
        'emergency_name',
        'emergency_relationship',
        'emergency_phone',
        'guardian_name',
    ];
}

function extension_public_operation(array $operation): array
{
    return [
        'id' => (int)($operation['id'] ?? 0),
        'sequence_no' => (int)($operation['sequence_no'] ?? 0),
        'operation_key' => (string)($operation['operation_key'] ?? ''),
        'operation_type' => (string)($operation['operation_type'] ?? ''),
        'page_type' => (string)($operation['page_type'] ?? ''),
        'course_id' => (int)($operation['course_id'] ?? 0),
        'course_code' => (string)($operation['course_code'] ?? ''),
        'business_label' => (string)($operation['business_label'] ?? ''),
        'slim_option_texts' => is_array($operation['slim_option_texts'] ?? null) ? array_values($operation['slim_option_texts']) : [],
        'reason_id' => $operation['reason_id'] ?? null,
        'reason_label' => $operation['reason_label'] ?? null,
        'payment_cycle' => $operation['payment_cycle'] ?? null,
        'payment_cycle_label' => $operation['payment_cycle_label'] ?? null,
        'application_date' => (string)($operation['application_date'] ?? ''),
        'start_date' => (string)($operation['start_date'] ?? ''),
        'status' => (string)($operation['status'] ?? ''),
        'attempts' => (int)($operation['attempts'] ?? 0),
        'last_error_code' => (string)($operation['last_error_code'] ?? ''),
        'last_error_summary' => (string)($operation['last_error_summary'] ?? ''),
        'readiness_errors' => is_array($operation['readiness_errors'] ?? null) ? array_values($operation['readiness_errors']) : [],
    ];
}

function extension_workflow_label(array $normalized): string
{
    $useType = (string)($normalized['use_type'] ?? 'new');
    if ($useType !== 'add') {
        return 'standalone_' . ((string)($normalized['course'] ?? 'basic') ?: 'basic');
    }

    $status = (string)($normalized['main_member_status'] ?? 'existing');
    return 'addon_' . ($status === 'simultaneous' ? 'simultaneous' : 'existing');
}

function extension_photo_download_filename(string $applicationId, string $slimMemberNumber, string $mime): string
{
    $base = $slimMemberNumber !== '' ? $slimMemberNumber : $applicationId;
    $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?? 'admission';
    $ext = match ($mime) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };

    return 'FIND-SLIM/' . $base . '.' . $ext;
}

function extension_transfer_payload(array $record): array
{
    $normalized = is_array($record['normalized'] ?? null) ? $record['normalized'] : [];
    $fields = [];
    foreach (extension_transfer_field_keys() as $key) {
        $fields[$key] = (string)($normalized[$key] ?? '');
    }

    $operations = array_map('extension_public_operation', is_array($record['operations'] ?? null) ? $record['operations'] : []);
    $photo = is_array($record['photo'] ?? null) ? $record['photo'] : [];
    $photoAvailable = !empty($photo['path']) || !empty($photo['archived_path']);
    $photoMime = (string)($photo['mime'] ?? '');

    return [
        'application_id' => (string)($record['id'] ?? ''),
        'submitted_at' => extension_iso_datetime((string)($record['created_at'] ?? '')),
        'display_name' => (string)($normalized['name'] ?? trim((string)($normalized['surname'] ?? '') . ' ' . (string)($normalized['given_name'] ?? ''))),
        'workflow_label' => extension_workflow_label($normalized),
        'actual_procedure_date' => (string)($normalized['actual_procedure_date'] ?? ''),
        'start_date' => (string)($normalized['start_date'] ?? ''),
        'main_member_number' => (string)($normalized['main_member_number'] ?? ''),
        'slim_member_number' => (string)($normalized['slim_member_number'] ?? ''),
        'transfer_fields' => $fields,
        'operations' => $operations,
        'photo' => [
            'available' => $photoAvailable,
            'mime_type' => $photoAvailable ? $photoMime : '',
            'size' => $photoAvailable ? (int)($photo['size'] ?? 0) : 0,
            'download_filename' => $photoAvailable ? extension_photo_download_filename((string)($record['id'] ?? ''), (string)($normalized['slim_member_number'] ?? ''), $photoMime) : '',
        ],
        'readiness' => is_array($record['readiness'] ?? null) ? $record['readiness'] : ['errors' => [], 'warnings' => []],
        'slim_status' => (string)($record['slim_status'] ?? 'not_started'),
        'operation_progress' => is_array($record['operation_progress'] ?? null) ? $record['operation_progress'] : slim_operation_progress($operations),
        'version' => (int)($record['version'] ?? 1),
    ];
}

function extension_find_admission_row(PDO $pdo, string $applicationId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM admissions WHERE application_id = :application_id LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['application_id' => $applicationId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function extension_require_admission(string $applicationId): array
{
    $record = admission_find_record_by_application_id($applicationId);
    if ($record === null) {
        extension_fail(404, 'admission_not_found', 'Admission was not found.');
    }

    return $record;
}

function extension_fetch_lock(PDO $pdo, int $admissionId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT l.*, u.username, u.display_name
           FROM admission_locks l
           LEFT JOIN admin_users u ON u.id = l.owner_admin_id
          WHERE l.admission_id = :admission_id
          LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['admission_id' => $admissionId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function extension_lock_public(?array $lock): ?array
{
    if ($lock === null) {
        return null;
    }

    return [
        'owner_display_name' => extension_admin_display_name($lock),
        'installation_id_masked' => extension_mask_installation_id((string)$lock['installation_id']),
        'expires_at' => extension_iso_datetime((string)$lock['expires_at']),
    ];
}

function extension_lock_is_owned_by(array $lock, array $auth): bool
{
    return (int)$lock['owner_admin_id'] === (int)$auth['admin_user_id']
        && hash_equals((string)$lock['installation_id'], (string)$auth['installation_id']);
}

function extension_acquire_lock(string $applicationId, array $input, array $auth, string $requestId): array
{
    $version = isset($input['version']) ? (int)$input['version'] : 0;
    if ($version <= 0) {
        extension_fail(400, 'version_required', 'version is required.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $admission = extension_find_admission_row($pdo, $applicationId, true);
        if ($admission === null) {
            extension_fail(404, 'admission_not_found', 'Admission was not found.');
        }
        if ((int)$admission['version'] !== $version) {
            extension_fail(409, 'version_conflict', 'Admission version has changed.');
        }

        $lock = extension_fetch_lock($pdo, (int)$admission['id'], true);
        if ($lock !== null && strtotime((string)$lock['expires_at']) < time()) {
            $pdo->prepare('DELETE FROM admission_locks WHERE id = :id')->execute(['id' => (int)$lock['id']]);
            $lock = null;
        }
        if ($lock !== null && !extension_lock_is_owned_by($lock, $auth)) {
            $pdo->commit();
            extension_fail(409, 'lock_conflict', 'Admission is locked by another staff member.');
        }

        $expiresAt = extension_sql_after((int)extension_config()['lock_ttl_seconds']);
        if ($lock === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO admission_locks (
                    admission_id, owner_admin_id, installation_id, acquired_at, heartbeat_at, expires_at
                 ) VALUES (
                    :admission_id, :owner_admin_id, :installation_id, NOW(), NOW(), :expires_at
                 )'
            );
            $stmt->execute([
                'admission_id' => (int)$admission['id'],
                'owner_admin_id' => (int)$auth['admin_user_id'],
                'installation_id' => (string)$auth['installation_id'],
                'expires_at' => $expiresAt,
            ]);
        } else {
            $pdo->prepare(
                'UPDATE admission_locks
                    SET heartbeat_at = NOW(), expires_at = :expires_at
                  WHERE id = :id'
            )->execute([
                'expires_at' => $expiresAt,
                'id' => (int)$lock['id'],
            ]);
        }

        extension_insert_admission_event($pdo, $requestId, (int)$admission['id'], null, $auth, 'lock_acquired', [
            'expires_at' => $expiresAt,
        ]);
        $pdo->commit();

        $lock = extension_fetch_lock($pdo, (int)$admission['id']);
        return [
            'lock' => extension_lock_public($lock),
            'version' => (int)$admission['version'],
        ];
    } catch (ExtensionApiException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function extension_extend_lock(string $applicationId, array $auth, string $requestId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $admission = extension_find_admission_row($pdo, $applicationId, true);
        if ($admission === null) {
            extension_fail(404, 'admission_not_found', 'Admission was not found.');
        }
        $lock = extension_fetch_lock($pdo, (int)$admission['id'], true);
        if ($lock === null || strtotime((string)$lock['expires_at']) < time() || !extension_lock_is_owned_by($lock, $auth)) {
            extension_fail(409, 'lock_lost', 'Active lock was not found for this installation.');
        }

        $expiresAt = extension_sql_after((int)extension_config()['lock_ttl_seconds']);
        $pdo->prepare(
            'UPDATE admission_locks
                SET heartbeat_at = NOW(), expires_at = :expires_at
              WHERE id = :id'
        )->execute([
            'expires_at' => $expiresAt,
            'id' => (int)$lock['id'],
        ]);
        extension_insert_admission_event($pdo, $requestId, (int)$admission['id'], null, $auth, 'lock_heartbeat', [
            'expires_at' => $expiresAt,
        ]);
        $pdo->commit();

        return [
            'lock' => extension_lock_public(extension_fetch_lock($pdo, (int)$admission['id'])),
        ];
    } catch (ExtensionApiException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function extension_release_lock(string $applicationId, array $auth, string $requestId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $admission = extension_find_admission_row($pdo, $applicationId, true);
        if ($admission === null) {
            extension_fail(404, 'admission_not_found', 'Admission was not found.');
        }
        $lock = extension_fetch_lock($pdo, (int)$admission['id'], true);
        if ($lock !== null && extension_lock_is_owned_by($lock, $auth)) {
            $pdo->prepare('DELETE FROM admission_locks WHERE id = :id')->execute(['id' => (int)$lock['id']]);
            extension_insert_admission_event($pdo, $requestId, (int)$admission['id'], null, $auth, 'lock_released', []);
        }
        $pdo->commit();

        return ['released' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function extension_require_owned_lock(PDO $pdo, int $admissionId, array $auth): void
{
    $lock = extension_fetch_lock($pdo, $admissionId, true);
    if ($lock === null || strtotime((string)$lock['expires_at']) < time() || !extension_lock_is_owned_by($lock, $auth)) {
        extension_fail(409, 'lock_required', 'An active lock for this admission is required.');
    }
}

function extension_insert_admission_event(PDO $pdo, string $requestId, int $admissionId, ?int $operationId, array $auth, string $action, array $result = [], ?string $pageProfileVersion = null): void
{
    if (!admission_slim_tables_ready()) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO admission_slim_events (
                request_id, admission_id, operation_id, actor_admin_id,
                extension_installation_id, action, result_json, page_profile_version, created_at
             ) VALUES (
                :request_id, :admission_id, :operation_id, :actor_admin_id,
                :extension_installation_id, :action, :result_json, :page_profile_version, NOW()
             )'
        );
        $stmt->execute([
            'request_id' => $requestId,
            'admission_id' => $admissionId,
            'operation_id' => $operationId,
            'actor_admin_id' => (int)$auth['admin_user_id'],
            'extension_installation_id' => (string)$auth['installation_id'],
            'action' => $action,
            'result_json' => admission_json_encode($result),
            'page_profile_version' => $pageProfileVersion,
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
    }
}

function extension_load_operation(PDO $pdo, int $operationId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM admission_slim_operations WHERE id = :id LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $operationId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $row['slim_option_texts'] = admission_json_decode($row['slim_option_texts'] ?? null);
    $row['readiness_errors'] = admission_json_decode($row['readiness_errors'] ?? null);
    return $row;
}

function extension_operation_preconditions_met(PDO $pdo, array $operation): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
           FROM admission_slim_operations
          WHERE admission_id = :admission_id
            AND sequence_no < :sequence_no
            AND status <> 'completed'"
    );
    $stmt->execute([
        'admission_id' => (int)$operation['admission_id'],
        'sequence_no' => (int)$operation['sequence_no'],
    ]);

    return (int)$stmt->fetchColumn() === 0;
}

function extension_refresh_admission_status(PDO $pdo, int $admissionId): void
{
    $stmt = $pdo->prepare('SELECT * FROM admissions WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $admissionId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $original = admission_json_decode($row['original_payload'] ?? null);
    $normalized = array_merge(admission_normalized_payload($original), admission_json_decode($row['normalized_payload'] ?? null));
    $operations = admission_fetch_slim_operations($admissionId, $pdo);
    $status = slim_status_from_operations($normalized, $operations);
    $pdo->prepare('UPDATE admissions SET slim_status = :slim_status, updated_at = NOW() WHERE id = :id')->execute([
        'slim_status' => $status,
        'id' => $admissionId,
    ]);
}

function extension_sanitize_counts(array $counts): array
{
    $allowed = ['filled', 'matched', 'skipped_nonempty', 'differences', 'missing', 'errors'];
    $sanitized = [];
    foreach ($allowed as $key) {
        $sanitized[$key] = max(0, (int)($counts[$key] ?? 0));
    }

    return $sanitized;
}

function extension_sanitize_error_codes(array $codes): array
{
    $items = [];
    foreach ($codes as $code) {
        $code = substr(preg_replace('/[^A-Za-z0-9._:-]/', '', (string)$code) ?? '', 0, 80);
        if ($code !== '') {
            $items[] = $code;
        }
    }

    return array_values(array_unique($items));
}

function extension_fill_result(int $operationId, array $input, array $auth, string $requestId): array
{
    $eventRequestId = (string)($input['request_id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $eventRequestId)) {
        extension_fail(400, 'request_id_required', 'A stable request_id is required.');
    }

    $pdo = db();
    $existing = $pdo->prepare('SELECT id FROM admission_slim_events WHERE request_id = :request_id LIMIT 1');
    $existing->execute(['request_id' => $eventRequestId]);
    if ($existing->fetch()) {
        return ['idempotent' => true];
    }

    $pdo->beginTransaction();
    try {
        $operation = extension_load_operation($pdo, $operationId, true);
        if ($operation === null) {
            extension_fail(404, 'operation_not_found', 'Operation was not found.');
        }
        extension_require_owned_lock($pdo, (int)$operation['admission_id'], $auth);
        if ((string)($input['page_type'] ?? '') !== (string)$operation['page_type']) {
            extension_fail(422, 'page_type_mismatch', 'Current page does not match operation page type.');
        }
        if (!extension_operation_preconditions_met($pdo, $operation)) {
            extension_fail(409, 'operation_order_conflict', 'Previous operations must be completed first.');
        }

        $counts = extension_sanitize_counts(is_array($input['counts'] ?? null) ? $input['counts'] : []);
        $errorCodes = extension_sanitize_error_codes(is_array($input['error_codes'] ?? null) ? $input['error_codes'] : []);
        $hasProblems = $counts['differences'] > 0 || $counts['missing'] > 0 || $counts['errors'] > 0 || $errorCodes !== [];
        $status = $hasProblems ? 'needs_review' : 'filled';
        $summary = sprintf(
            'filled=%d matched=%d skipped=%d differences=%d missing=%d errors=%d',
            $counts['filled'],
            $counts['matched'],
            $counts['skipped_nonempty'],
            $counts['differences'],
            $counts['missing'],
            $counts['errors']
        );

        $stmt = $pdo->prepare(
            'UPDATE admission_slim_operations
                SET attempts = attempts + 1,
                    status = :status,
                    last_error_code = :last_error_code,
                    last_error_summary = :last_error_summary,
                    filled_at = CASE WHEN :filled_ok = 1 THEN NOW() ELSE filled_at END,
                    filled_by = CASE WHEN :filled_ok = 1 THEN :filled_by ELSE filled_by END,
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'last_error_code' => $errorCodes[0] ?? '',
            'last_error_summary' => $summary,
            'filled_ok' => $hasProblems ? 0 : 1,
            'filled_by' => (int)$auth['admin_user_id'],
            'id' => $operationId,
        ]);

        extension_insert_admission_event($pdo, $eventRequestId, (int)$operation['admission_id'], $operationId, $auth, 'fill_result', [
            'counts' => $counts,
            'error_codes' => $errorCodes,
            'extension_version' => extension_safe_version((string)($input['extension_version'] ?? $auth['extension_version'] ?? '')),
        ], extension_safe_version((string)($input['page_profile_version'] ?? '')));
        extension_refresh_admission_status($pdo, (int)$operation['admission_id']);
        $pdo->commit();

        return [
            'operation_id' => $operationId,
            'status' => $status,
            'idempotent' => false,
        ];
    } catch (ExtensionApiException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function extension_complete_operation(int $operationId, array $auth, string $requestId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $operation = extension_load_operation($pdo, $operationId, true);
        if ($operation === null) {
            extension_fail(404, 'operation_not_found', 'Operation was not found.');
        }
        extension_require_owned_lock($pdo, (int)$operation['admission_id'], $auth);
        if (!extension_operation_preconditions_met($pdo, $operation)) {
            extension_fail(409, 'operation_order_conflict', 'Previous operations must be completed first.');
        }
        if (!empty($operation['readiness_errors']) || in_array((string)$operation['status'], ['blocked', 'needs_review'], true)) {
            extension_fail(422, 'operation_not_ready', 'Operation is not ready to complete.');
        }

        $pdo->prepare(
            "UPDATE admission_slim_operations
                SET status = 'completed',
                    completed_at = NOW(),
                    completed_by = :completed_by,
                    updated_at = NOW()
              WHERE id = :id"
        )->execute([
            'completed_by' => (int)$auth['admin_user_id'],
            'id' => $operationId,
        ]);
        extension_insert_admission_event($pdo, $requestId, (int)$operation['admission_id'], $operationId, $auth, 'operation_completed', []);
        extension_refresh_admission_status($pdo, (int)$operation['admission_id']);
        $pdo->commit();

        return [
            'operation_id' => $operationId,
            'status' => 'completed',
        ];
    } catch (ExtensionApiException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function extension_validate_member_number(string $value): bool
{
    return preg_match('/^[A-Za-z0-9._-]{1,64}$/', $value) === 1;
}

function extension_update_member_number(string $applicationId, array $input, array $auth, string $requestId): array
{
    $mainProvided = array_key_exists('main_member_number', $input);
    $slimProvided = array_key_exists('slim_member_number', $input);
    if (!$mainProvided && !$slimProvided) {
        extension_fail(400, 'member_number_required', 'main_member_number or slim_member_number is required.');
    }

    $mainMemberNumber = $mainProvided ? trim((string)$input['main_member_number']) : null;
    $slimMemberNumber = $slimProvided ? trim((string)$input['slim_member_number']) : null;
    if ($mainMemberNumber !== null && $mainMemberNumber !== '' && !extension_validate_member_number($mainMemberNumber)) {
        extension_fail(422, 'invalid_main_member_number', 'main_member_number format is invalid.');
    }
    if ($slimMemberNumber !== null && $slimMemberNumber !== '' && !extension_validate_member_number($slimMemberNumber)) {
        extension_fail(422, 'invalid_slim_member_number', 'slim_member_number format is invalid.');
    }

    $version = isset($input['version']) ? (int)$input['version'] : 0;
    if ($version <= 0) {
        extension_fail(400, 'version_required', 'version is required.');
    }
    $confirmOverwrite = !empty($input['confirm_overwrite']);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $admission = extension_find_admission_row($pdo, $applicationId, true);
        if ($admission === null) {
            extension_fail(404, 'admission_not_found', 'Admission was not found.');
        }
        if ((int)$admission['version'] !== $version) {
            extension_fail(409, 'version_conflict', 'Admission version has changed.');
        }
        extension_require_owned_lock($pdo, (int)$admission['id'], $auth);

        $conflicts = [];
        if ($mainMemberNumber !== null && (string)$admission['main_member_number'] !== '' && (string)$admission['main_member_number'] !== $mainMemberNumber) {
            $conflicts[] = 'main_member_number';
        }
        if ($slimMemberNumber !== null && (string)$admission['slim_member_number'] !== '' && (string)$admission['slim_member_number'] !== $slimMemberNumber) {
            $conflicts[] = 'slim_member_number';
        }
        if ($conflicts !== [] && !$confirmOverwrite) {
            extension_fail(409, 'member_number_conflict', 'Existing member number differs. confirm_overwrite is required.');
        }

        $original = admission_json_decode($admission['original_payload'] ?? null);
        $normalized = array_merge(admission_normalized_payload($original), admission_json_decode($admission['normalized_payload'] ?? null));
        if ($mainMemberNumber !== null) {
            $normalized['main_member_number'] = $mainMemberNumber;
        } else {
            $mainMemberNumber = (string)$admission['main_member_number'];
        }
        if ($slimMemberNumber !== null) {
            $normalized['slim_member_number'] = $slimMemberNumber;
        } else {
            $slimMemberNumber = (string)$admission['slim_member_number'];
        }

        $stmt = $pdo->prepare(
            'UPDATE admissions
                SET main_member_number = :main_member_number,
                    slim_member_number = :slim_member_number,
                    normalized_payload = :normalized_payload,
                    version = version + 1,
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            'main_member_number' => $mainMemberNumber,
            'slim_member_number' => $slimMemberNumber,
            'normalized_payload' => admission_json_encode($normalized),
            'id' => (int)$admission['id'],
        ]);

        $photos = admission_fetch_photo_records((int)$admission['id']);
        admission_sync_slim_operations($pdo, (int)$admission['id'], $normalized, [
            'photo' => $photos[0] ?? [],
            'actor_admin_id' => (int)$auth['admin_user_id'],
        ]);
        extension_insert_admission_event($pdo, $requestId, (int)$admission['id'], null, $auth, 'member_number_updated', [
            'fields' => array_values(array_filter([
                $mainProvided ? 'main_member_number' : '',
                $slimProvided ? 'slim_member_number' : '',
            ])),
        ]);
        $pdo->commit();

        $updated = extension_find_admission_row($pdo, $applicationId, false);
        return [
            'application_id' => $applicationId,
            'version' => (int)($updated['version'] ?? ($version + 1)),
        ];
    } catch (ExtensionApiException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function extension_issue_photo_token(string $applicationId, array $auth, string $requestId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $admission = extension_find_admission_row($pdo, $applicationId, true);
        if ($admission === null) {
            extension_fail(404, 'admission_not_found', 'Admission was not found.');
        }
        extension_require_owned_lock($pdo, (int)$admission['id'], $auth);
        $stmt = $pdo->prepare(
            'SELECT id, storage_path, mime_type, file_size
               FROM admission_photos
              WHERE admission_id = :admission_id
              ORDER BY id ASC
              LIMIT 1'
        );
        $stmt->execute(['admission_id' => (int)$admission['id']]);
        $photo = $stmt->fetch();
        if (!$photo) {
            extension_fail(404, 'photo_not_found', 'Photo is not available.');
        }

        $token = extension_generate_secret_token();
        $expiresAt = extension_sql_after((int)extension_config()['photo_token_ttl_seconds']);
        $insert = $pdo->prepare(
            'INSERT INTO extension_photo_tokens (
                token_hash, admission_id, photo_id, admin_user_id, installation_id, expires_at, created_at
             ) VALUES (
                :token_hash, :admission_id, :photo_id, :admin_user_id, :installation_id, :expires_at, NOW()
             )'
        );
        $insert->execute([
            'token_hash' => extension_hash_secret($token),
            'admission_id' => (int)$admission['id'],
            'photo_id' => (int)$photo['id'],
            'admin_user_id' => (int)$auth['admin_user_id'],
            'installation_id' => (string)$auth['installation_id'],
            'expires_at' => $expiresAt,
        ]);
        extension_insert_admission_event($pdo, $requestId, (int)$admission['id'], null, $auth, 'photo_token_issued', [
            'expires_at' => $expiresAt,
        ]);
        $pdo->commit();

        $filename = extension_photo_download_filename($applicationId, (string)$admission['slim_member_number'], (string)$photo['mime_type']);
        return [
            'photo_url' => base_path('/api/v1/extension/photos/' . rawurlencode($token)),
            'expires_at' => extension_iso_datetime($expiresAt),
            'download_filename' => $filename,
        ];
    } catch (ExtensionApiException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function extension_admission_photo_allowed_roots(): array
{
    $configPath = __DIR__ . '/../admission/inc/config.php';
    $config = is_file($configPath) ? require $configPath : [];
    $roots = [];
    foreach ([
        $config['admin']['photo_archive_dir'] ?? '',
        $config['photo']['tmp_dir'] ?? '',
        dirname(__DIR__, 2) . '/storage/admission_photos',
    ] as $root) {
        $real = is_string($root) && $root !== '' ? realpath($root) : false;
        if ($real !== false) {
            $roots[] = rtrim($real, DIRECTORY_SEPARATOR);
        }
    }

    return array_values(array_unique($roots));
}

function extension_path_is_under_roots(string $path, array $roots): bool
{
    $real = realpath($path);
    if ($real === false) {
        return false;
    }

    foreach ($roots as $root) {
        if ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return true;
        }
    }

    return false;
}

function extension_photo_header_filename(string $downloadFilename): string
{
    $basename = basename(str_replace('\\', '/', $downloadFilename));
    $basename = preg_replace('/[^A-Za-z0-9._-]/', '_', $basename) ?? 'admission-photo.jpg';
    return $basename === '' ? 'admission-photo.jpg' : $basename;
}

function extension_photo_token_is_safe(string $token): bool
{
    return preg_match('/^fpslim_[A-Za-z0-9_-]{32,80}$/', $token) === 1;
}

function extension_serve_photo(string $token): never
{
    if (!extension_tables_ready()) {
        extension_fail(503, 'extension_tables_missing', 'Extension API migration has not been applied.');
    }
    if (empty(extension_config()['transfer_enabled'])) {
        extension_fail(403, 'extension_disabled', 'Extension transfer API is disabled.');
    }
    if (!extension_photo_token_is_safe($token)) {
        extension_fail(404, 'photo_not_found', 'Photo token was not found.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT pt.id AS token_id, pt.admission_id, pt.photo_id, pt.expires_at,
                    a.application_id, a.slim_member_number,
                    p.storage_path, p.mime_type
               FROM extension_photo_tokens pt
               INNER JOIN admissions a ON a.id = pt.admission_id
               INNER JOIN admission_photos p ON p.id = pt.photo_id
              WHERE pt.token_hash = :token_hash
                AND pt.used_at IS NULL
              LIMIT 1
              FOR UPDATE'
        );
        $stmt->execute(['token_hash' => extension_hash_secret($token)]);
        $row = $stmt->fetch();
        if (!$row || strtotime((string)$row['expires_at']) < time()) {
            extension_fail(404, 'photo_not_found', 'Photo token was not found or expired.');
        }

        $path = (string)$row['storage_path'];
        $roots = extension_admission_photo_allowed_roots();
        if (!extension_path_is_under_roots($path, $roots) || !is_file($path)) {
            extension_fail(404, 'photo_not_found', 'Photo file was not found.');
        }

        $mime = (string)$row['mime_type'];
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $mime = 'application/octet-stream';
        }

        $downloadFilename = extension_photo_download_filename((string)$row['application_id'], (string)$row['slim_member_number'], $mime);
        $pdo->prepare('UPDATE extension_photo_tokens SET used_at = NOW() WHERE id = :id')->execute(['id' => (int)$row['token_id']]);
        $pdo->commit();

        if (!headers_sent()) {
            http_response_code(200);
            extension_send_base_headers();
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . extension_photo_header_filename($downloadFilename) . '"');
            header('Content-Length: ' . (string)filesize($path));
        }
        readfile($path);
        exit;
    } catch (ExtensionApiException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
