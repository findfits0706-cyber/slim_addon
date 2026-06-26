# Extension API

Prompt 3 implements the JSON API used by the Microsoft Edge extension.

Base path:

```text
/api/v1/extension/
```

If Apache rewrite is unavailable, the same router can be called as:

```text
/api/v1/extension/index.php?route=me
```

All JSON responses use this envelope:

```json
{
  "ok": true,
  "request_id": "ext-example",
  "data": {}
}
```

Errors use:

```json
{
  "ok": false,
  "request_id": "ext-example",
  "error": {
    "code": "token_expired_or_revoked",
    "message": "Token is expired or revoked."
  }
}
```

Every API response sends `Cache-Control: no-store`.

## Configuration

Set these in private `config/findpilates.php`:

```php
'extension' => [
    'api_base_path' => '/api/v1/extension',
    'pairing_code_ttl_seconds' => 300,
    'access_token_ttl_seconds' => 8 * 60 * 60,
    'lock_ttl_seconds' => 600,
    'photo_token_ttl_seconds' => 300,
    'allowed_origins' => [
        'chrome-extension://example-extension-id',
    ],
    'transfer_enabled' => true,
],
```

Keep `transfer_enabled` false until the migration is applied and the Edge extension is ready.

## Migrations

Apply in order:

```text
database/migrations/20260626_admissions_mysql.sql
database/migrations/20260626_admission_slim_operations.sql
database/migrations/20260626_extension_api.sql
```

The API stores only SHA-256 hashes of pairing codes, bearer tokens, and photo tokens.

## Pairing

Admin staff open `/admin/extension.php`, press `Edgeを接続`, and see a one-time code for 5 minutes.

The extension exchanges it:

```bash
curl -X POST https://findpilates.example/api/v1/extension/pair \
  -H "Content-Type: application/json" \
  -d '{"pairing_code":"ABCD-EFGH","installation_id":"example-installation-uuid","extension_version":"0.1.0"}'
```

Response:

```json
{
  "ok": true,
  "request_id": "ext-example",
  "data": {
    "access_token": "fpslim_example",
    "expires_at": "2026-07-01T18:00:00+09:00",
    "staff": {
      "display_name": "Example Staff"
    }
  }
}
```

The access token is valid for 8 hours by default. Store it in extension session storage, not persistent local storage.

Authenticated requests must send:

```text
Authorization: Bearer fpslim_example
X-Extension-Installation-Id: example-installation-uuid
```

## Endpoints

### `GET /me`

Returns connected staff, token expiry, installation suffix, extension version, and API version.

### `GET /admissions`

Query:

```text
scope=today|unregistered|in_progress|search
q=example
page=1
limit=25
```

List items intentionally contain only:

- `application_id`
- `submitted_at`
- `slim_status`
- `operation_progress`
- `version`

Names and phone numbers are not returned in list responses.

### `GET /admissions/{applicationId}/transfer`

Returns selected admission transfer data:

- application id and submitted time
- display name
- workflow label
- actual procedure date
- start date
- member numbers
- normalized transfer fields
- operations
- photo availability only
- readiness errors and warnings
- version

It does not return health payload, terms details, admin notes, DB paths, SLIM credentials, or internal campaign data.

### `POST /admissions/{applicationId}/lock`

Body:

```json
{
  "version": 3
}
```

Returns a 10-minute lock by default. If another staff token holds the lock, the API returns `409 lock_conflict` with only the owner display name and expiry.

### `POST /admissions/{applicationId}/heartbeat`

Extends the current installation's lock.

### `DELETE /admissions/{applicationId}/lock`

Releases only the current installation's lock.

### `POST /operations/{operationId}/fill-result`

Body:

```json
{
  "request_id": "fill-example-0001",
  "page_type": "admission_procedure",
  "page_profile_version": "admission-procedure-unverified-0",
  "counts": {
    "filled": 10,
    "matched": 2,
    "skipped_nonempty": 0,
    "differences": 0,
    "missing": 0,
    "errors": 0
  },
  "error_codes": [],
  "extension_version": "0.1.0"
}
```

Field values and PII must not be sent. `request_id` is idempotent.

### `POST /operations/{operationId}/complete`

Marks an operation complete only after staff confirms SLIM registration. Previous operations must already be completed.

### `POST /admissions/{applicationId}/member-number`

Body:

```json
{
  "version": 4,
  "slim_member_number": "S000123"
}
```

Requires an active lock and matching version. If an existing member number differs, retry with `confirm_overwrite: true` after explicit staff confirmation.

### `POST /admissions/{applicationId}/photo-token`

Requires an active lock. Returns a one-time, short-lived `photo_url` and a suggested download filename such as:

```text
FIND-SLIM/S000123.jpg
```

### `GET /photos/{token}`

Streams the photo once with `Content-Type`, `Content-Disposition`, and `Cache-Control: no-store`. It never returns the physical storage path.

## Error Codes

Common codes:

- `invalid_json`
- `extension_disabled`
- `extension_tables_missing`
- `invalid_pairing_code`
- `pairing_rate_limited`
- `unauthorized`
- `token_expired_or_revoked`
- `installation_mismatch`
- `admission_not_found`
- `version_conflict`
- `lock_conflict`
- `lock_required`
- `operation_order_conflict`
- `operation_not_ready`
- `member_number_conflict`
- `photo_not_found`

## Token Revocation

Open `/admin/extension.php` to revoke one active token or all active tokens. Revoked tokens immediately fail with `401 token_expired_or_revoked`.

## Security Notes

- Do not put access tokens in URL query parameters.
- Do not log Authorization headers.
- Do not send health data, admin notes, or full form payloads to the extension.
- Use the bearer token and `X-Extension-Installation-Id`; CORS origin is not authentication.
- Pairing attempts are rate-limited by a requester hash.
- Photo tokens are one-time and expire quickly.
