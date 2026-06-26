<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/extension-api.php';

$requestId = extension_request_id();

try {
    extension_send_base_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $route = trim((string)($_GET['route'] ?? ''), '/');
    if ($route === '') {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
        $base = rtrim(base_path((string)extension_config()['api_base_path']), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $route = trim(substr($path, strlen($base)), '/');
        }
        if (str_starts_with($route, 'index.php/')) {
            $route = substr($route, strlen('index.php/'));
        }
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $parts = $route === '' ? [] : explode('/', $route);

    if ($method === 'POST' && $route === 'pair') {
        extension_success($requestId, extension_pair(extension_read_json_body(), $requestId));
    }

    if (count($parts) === 2 && $method === 'GET' && $parts[0] === 'photos') {
        extension_serve_photo(rawurldecode($parts[1]));
    }

    $auth = extension_authenticate();

    if ($method === 'GET' && $route === 'me') {
        extension_success($requestId, extension_me($auth));
    }

    if ($method === 'GET' && $route === 'admissions') {
        extension_success($requestId, extension_list_admissions($_GET));
    }

    if (count($parts) === 3 && $method === 'GET' && $parts[0] === 'admissions' && $parts[2] === 'transfer') {
        $record = extension_require_admission(rawurldecode($parts[1]));
        extension_success($requestId, extension_transfer_payload($record));
    }

    if (count($parts) === 3 && $parts[0] === 'admissions') {
        $applicationId = rawurldecode($parts[1]);
        if ($method === 'POST' && $parts[2] === 'lock') {
            extension_success($requestId, extension_acquire_lock($applicationId, extension_read_json_body(), $auth, $requestId));
        }
        if ($method === 'POST' && $parts[2] === 'heartbeat') {
            extension_success($requestId, extension_extend_lock($applicationId, $auth, $requestId));
        }
        if ($method === 'DELETE' && $parts[2] === 'lock') {
            extension_success($requestId, extension_release_lock($applicationId, $auth, $requestId));
        }
        if ($method === 'POST' && $parts[2] === 'member-number') {
            extension_success($requestId, extension_update_member_number($applicationId, extension_read_json_body(), $auth, $requestId));
        }
        if ($method === 'POST' && $parts[2] === 'photo-token') {
            extension_success($requestId, extension_issue_photo_token($applicationId, $auth, $requestId));
        }
    }

    if (count($parts) === 3 && $method === 'POST' && $parts[0] === 'operations') {
        $operationId = (int)$parts[1];
        if ($operationId <= 0) {
            extension_fail(400, 'invalid_operation_id', 'Operation id is invalid.');
        }
        if ($parts[2] === 'fill-result') {
            extension_success($requestId, extension_fill_result($operationId, extension_read_json_body(), $auth, $requestId));
        }
        if ($parts[2] === 'complete') {
            extension_success($requestId, extension_complete_operation($operationId, $auth, $requestId));
        }
    }

    extension_fail(404, 'endpoint_not_found', 'Endpoint was not found.');
} catch (ExtensionApiException $e) {
    extension_error($requestId, $e->status, $e->apiCode, $e->getMessage());
} catch (Throwable $e) {
    error_log('Extension API error: ' . $e->getMessage());
    extension_error($requestId, 500, 'internal_error', 'Internal server error.');
}
