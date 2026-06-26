<?php

declare(strict_types=1);

/*
 * Secret-free configuration example for the existing Find Pilates PHP app
 * and the planned SLIM integration.
 *
 * Copy to config/findpilates.php on the target environment and fill real
 * values outside Git. Do not commit credentials, tokens, hashes, or
 * production-only paths.
 */

return [
    // Existing application keys read by public_html/app/config.php.
    'db_host' => 'mysql.example.ne.jp',
    'db_name' => 'database_name',
    'db_user' => 'database_user',
    'db_pass' => 'change-me-outside-git',
    'admin_email' => 'staff@example.com',
    'from_email' => 'info@example.com',
    'from_name' => 'Find Pilates',

    // Planned admission and extension integration keys.
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
];
