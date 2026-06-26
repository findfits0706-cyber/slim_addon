# SLIM Integration Progress

Status legend:

- Done: completed in this workspace.
- Not started: ready after prerequisites are added.
- Blocked: cannot be implemented safely with the files currently present.
- Waiting real screen: requires saved or live SLIM HTML.

## Prompt 0: Baseline, Spec, Safety Foundation

| Item | Status | Notes |
|---|---|---|
| Read design and prompt documents | Done | `FindPilates_SLIM_最終設計.md`, `FindPilates_Codex_実装プロンプト集.md` |
| Check git status | Done | `git status` failed because this folder is not a Git repository. No repository state was changed. |
| Inspect repository structure | Done | Only the two source Markdown files were present at start. |
| Avoid unrelated existing changes | Done | No existing application files were modified. |
| Create integration spec | Done | `docs/slim-integration-spec.md` |
| Create progress checklist | Done | this file |
| Create field inventory | Done | `docs/slim-field-inventory.md` |
| Check/update `.gitignore` | Done | New `.gitignore` added with private data and generated output rules. |
| Add secret-free config example | Done | `config/findpilates.example.php` |
| Add test entrypoint | Done | `scripts/run-checks.ps1`, `tests/README.md` |
| Inspect existing PHP execution path | Blocked | `public_html/` is not present. |
| Inspect saved SLIM HTML | Blocked | No saved HTML fixtures are present. |

## Phase Checklist

### Phase 1: Public Form, Fee Engine, MySQL Persistence

| Item | Status |
|---|---|
| Confirm existing PHP files and routes | Done |
| Remove JSON as production storage | Done |
| Implement server fee service | Done |
| Update public form fields | Done |
| Implement MySQL migrations and repository | Done |
| Implement idempotent submit | Done |
| Implement photo protected storage | Done |
| Add JSON import dry-run CLI | Done |
| Update admin admissions compatibility | Done |
| Add automated tests | Done |

### Phase 2: Admin UI and Operation Queue

| Item | Status |
|---|---|
| Separate application status and SLIM status | Done |
| Add normalized transfer values | Done |
| Add actual procedure date workflow | Done |
| Implement course catalog | Done |
| Generate operations for all patterns | Done |
| Add locks and audit events | Done |

### Phase 3: Extension API and Short-Lived Auth

| Item | Status |
|---|---|
| Pairing code exchange | Done |
| Extension bearer token storage | Done |
| Minimum admissions list/detail responses | Done |
| Lock heartbeat and release | Done |
| Progress event endpoints | Done |
| Photo token endpoint | Done |

### Phase 4: Edge Foundation and SLIM Analysis

| Item | Status |
|---|---|
| Edge manifest and side panel | Not started |
| Pairing UI | Not started |
| Application selection and fixed target | Not started |
| Page detector | Waiting real screen |
| DOM analysis JSON output | Waiting real screen |
| Anonymous fixture tests | Waiting real screen |

### Phase 5: Screen-Level Transfer

| Item | Status |
|---|---|
| Fill engine | Not started |
| Admission procedure profile | Waiting real screen |
| Addition notification profile | Waiting real screen |
| Difference detection and highlight | Not started |
| Progress reporting | Not started |
| Photo download helper | Not started |

### Phase 6: Pilot and Deployment

| Item | Status |
|---|---|
| E2E anonymous scenarios | Not started |
| Production deployment guide | Not started |
| Edge pilot guide | Not started |
| Runbook | Not started |
| Rollback procedure | Not started |

### Phase 7: Optional Full Automation

| Item | Status |
|---|---|
| Start conditions verified | Not started |
| Auto navigation | Not started |
| Auto submit | Not started |
| Completion detection | Not started |

## Test Entry

Current command:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-checks.ps1
```

Last result in this workspace:

- Passed.
- PHP syntax checks passed.
- `tests/trial_schedule_unit.php` passed.
- `tests/admission_fee_unit.php` passed.
- `tests/slim_operations_unit.php` passed.
- `tests/extension_api_unit.php` passed.
- `scripts/import-admissions-json.php --source=tests/fixtures/admission_legacy_sample.json` dry-run passed.
- `php.exe` execution required elevated tool permission because the sandbox denied it.

Current expected behavior:

- verifies foundation files exist
- syntax-checks PHP files if PHP is installed
- runs imported trial schedule unit checks
- runs Prompt 1 admission fee and validation unit checks
- runs Prompt 2 SLIM operation generation and readiness unit checks
- runs Prompt 3 extension API response-safety unit checks
- runs legacy admission JSON import dry-run with an anonymous fixture

DB-backed admission save/list, SLIM operation persistence, and live extension endpoint behavior still need a safe local or staging MySQL database with the Prompt 1, Prompt 2, and Prompt 3 migrations applied.

## Prompt 3: Extension API and Short-Lived Auth

Implemented in this workspace:

- `/admin/extension.php` issues one-time pairing codes and lists/revokes active extension access tokens.
- `/api/v1/extension/` routes JSON endpoints through `public_html/app/extension-api.php`.
- `POST /pair` exchanges a one-time pairing code and `installation_id` for an 8-hour bearer token.
- Access tokens are stored as hashes only and must be used with `Authorization: Bearer` plus `X-Extension-Installation-Id`.
- Admission list responses are intentionally minimal and exclude names/phone numbers.
- Transfer detail responses exclude health payload, terms details, admin notes, DB paths, and SLIM credentials.
- Admission locks support acquire, heartbeat, and release with owner conflict responses.
- Operation fill-result and completion endpoints write non-PII SLIM events and enforce order/lock checks.
- Member number update requires version and lock checks, with overwrite confirmation for differences.
- Photo downloads use one-time short-lived tokens and never expose physical paths.
- CORS allowlisting is configurable while bearer token authentication remains mandatory.

Migration:

- Apply `database/migrations/20260626_extension_api.sql` after the Prompt 1 and Prompt 2 migrations.
- Set `extension.transfer_enabled` to `true` in private config only when the extension API should be live.

Docs:

- `docs/extension-api.md` describes endpoint contracts, examples, errors, pairing, revocation, and Xserver settings.

Human review still recommended:

- Apply the Prompt 3 migration to a safe DB and open `/admin/extension.php`.
- Confirm pairing code issuance and token revocation in staging.
- Exercise API endpoints with a temporary token using only anonymous test admissions.
- Confirm Xserver rewrite behavior for `/api/v1/extension/{route}`; use `index.php?route=...` if rewrite is unavailable.

## Prompt 2: Admin UI and Operation Queue

Implemented in this workspace:

- Application workflow status and SLIM preparation status are separate values.
- Admin admission list includes SLIM status filtering and completed/total operation progress.
- Admin detail shows original application data separately from normalized transfer values.
- Admin detail requires `actual_procedure_date` before the record can be considered ready for SLIM transfer.
- Health data remains outside the SLIM transfer copy text and normalized transfer payload.
- Course rules are centralized in `public_html/admission/inc/slim.php`.
- `build_slim_operations(array $normalizedAdmission): array` generates the per-application queue instead of a fixed STEP 1-4 workflow.
- Existing main-gym member addon applications are blocked until `main_member_number` is present.
- Addition-notification operations after a simultaneous main-gym admission stay blocked until the new SLIM member number is entered.
- Legacy `weekend` applications generate no operations and require manual review.
- Operation regeneration does not destructively replace started, filled, or completed operations when the plan changes; the admission is marked `needs_review`.
- `admission_slim_operations`, `admission_slim_events`, and `admission_locks` are added by migration.

Migration:

- Apply `database/migrations/20260626_admission_slim_operations.sql` after `database/migrations/20260626_admissions_mysql.sql`.
- After applying the migration, open the admin admission detail, set the actual procedure date, enter any required member numbers, and save. The operation queue is generated during the save transaction.

Operation examples covered by tests:

- standalone basic: `151`
- standalone double: `135`
- existing member basic addon: `145`
- existing member double addon: `146`
- simultaneous main-gym patterns: `80,145`, `130,146`, `74,145`, `133,146`, `140,145`, `140,141,145`, `140,141,146`, `140,144,145`, `140,144,146`
- legacy weekend: no operations and manual review
- missing actual procedure date: not ready
- missing existing member number: not ready
- plan change after a completed operation: destructive regeneration is blocked

Human review still recommended:

- Apply the Prompt 2 migration to a safe DB and save the successfully submitted anonymous admission from admin.
- Confirm the operation queue matches the actual membership selection.
- Confirm the list SLIM status filter and progress counts work for the saved application.
- Confirm `needs_review` behavior with a non-production record before relying on it operationally.

## Prompt 1: Public Form, Fee Engine, MySQL Persistence

Implemented in this workspace:

- Public admission form now collects split surname/given name, split kana, birth date, gender, `phone_type`, and split address fields.
- Public initial-visit selection was removed; PHP recalculates Pilates first-month fee and visit count from `start_date`.
- Confirm/send paths recalculate fees server-side and ignore posted fee or visit values.
- `admission/tmp/admissions.json` is no longer used as the production admission store.
- `public_html/admission/inc/repository.php` stores admissions, sensitive health/terms payloads, procedure preferences, and photo metadata in MySQL.
- `send.php` saves the DB record before mail send, records mail status afterwards, and treats mail failure as saved-but-warning.
- Duplicate submits use a session idempotency key and return the existing admission record.
- Photos are stored under `storage/admission_photos/` and are ignored by Git.
- `scripts/import-admissions-json.php` provides dry-run by default and `--commit` import using `legacy-json-<application_id>` idempotency keys.
- `public_html/admin/admissions.php` can list and edit MySQL-backed admission records through the existing admin UI.
- `public_html/admin/dashboard.php` counts new admissions from MySQL when the `admissions` table exists.

Migration:

- Apply `database/migrations/20260626_admissions_mysql.sql`.
- The migration intentionally does not add foreign keys, matching the existing schema style; the repository refreshes child rows inside one DB transaction.
- Rollback before production use should be DB-backup based. For a schema-only rollback, drop `admission_photos`, `admission_preferences`, `admission_sensitive`, then `admissions` after exporting any needed data.

Fee examples covered by tests:

- Basic standalone start day 1/8/15/22: 8,800 / 6,600 / 4,400 / 2,200.
- Double standalone start day 1/8/15/22: 12,650 / 9,488 / 6,325 / 3,163.
- Addon basic start day 8/22: 2,888 / 963.
- Addon double start day 8: 5,775.
- Existing main-gym member addon has main-gym first-month fee 0.
- Simultaneous main-gym admission keeps main-gym daily proration as a separate component.

Human review still recommended:

- Public admission form on smartphone and desktop.
- Admin admissions list/edit screen after applying the migration to a safe DB.
- One complete anonymous submission in staging, including mail failure behavior and photo metadata.
- Campaign wording on public landing content, because Prompt 1 disables campaign auto-application in the form config.

## Prompt 1 Start Conditions

Prompt 1 should not be implemented until these are present:

- existing PHP application under `public_html/` (present)
- existing config, DB, auth, CSRF, admission, and admin files (present)
- existing schema or migration convention (present)
- secret-free local config example (present)
- a safe local test database or test double strategy

The PHP application has been copied from the local `findpilates.jp` workspace. Prompt 1 can now start with code review and implementation, but DB-backed behavior will still need either a safe local test database or focused repository/unit tests that do not depend on production credentials.

## Existing App Import

Imported into this repository:

- `public_html/`
- `database/migrations/`
- `schema.sql`
- existing docs and tests
- `xserver_php/php.ini`

Intentionally not imported:

- `config/findpilates.php`
- `admin_seed.sql`
- `backups/`
- production logs, mail data, htpasswd data
- `public_html/data/`
- `public_html/admission/tmp/`
- zip archives

Validation after import:

- `powershell -ExecutionPolicy Bypass -File .\scripts\run-checks.ps1`: passed
- `php .\tests\trial_schedule_unit.php`: passed
