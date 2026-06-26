<?php

declare(strict_types=1);

/*
 * Secret-free configuration example for Find Pilates x SLIM integration.
 * Copy to the environment-specific private config path used by the existing app.
 * Do not commit real credentials, tokens, hashes, or production paths.
 */

return [
    'app' => [
        'base_url' => 'https://example.com',
        'environment' => 'local',
        'debug' => false,
    ],

    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'findpilates_local',
        'username' => 'findpilates_user',
        'password' => 'change-me-outside-git',
        'charset' => 'utf8mb4',
    ],

    'mail' => [
        'from_address' => 'noreply@example.com',
        'from_name' => 'Find Pilates',
        'admin_to' => 'staff@example.com',
    ],

    'admission' => [
        'photo_storage_dir' => __DIR__ . '/../storage/admission_photos',
        'max_photo_bytes' => 5 * 1024 * 1024,
        'allowed_photo_mime_types' => [
            'image/jpeg',
            'image/png',
        ],
        'idempotency_ttl_seconds' => 86400,
    ],

    'extension' => [
        'api_base_path' => '/api/v1/extension',
        'pairing_code_ttl_seconds' => 300,
        'access_token_ttl_seconds' => 8 * 60 * 60,
        'allowed_origins' => [
            'chrome-extension://replace-with-edge-extension-id',
        ],
        'transfer_enabled' => false,
        'dry_run_default' => true,
        'auto_navigation' => false,
        'auto_submit' => false,
    ],

    'slim' => [
        'login_url' => 'https://www.slim-sng.jp/slim/web/m/sng/login/',
        'profile_version' => 'unverified-0',
    ],

    'security' => [
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'csrf_token_bytes' => 32,
    ],
];
