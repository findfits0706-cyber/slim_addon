# Find Pilates x SLIM SNG Integration

Find Pilates admission form, admin workflow, Edge extension, and SLIM SNG assisted-transfer integration workspace.

## Current Status

This repository currently contains the Prompt 4 Microsoft Edge extension foundation plus the existing Find Pilates PHP application copied from the local `findpilates.jp` workspace.

- integration specification
- progress checklist
- SLIM field inventory placeholder
- secret-free configuration example
- local check script
- existing PHP application under `public_html/`
- existing schema and migration files
- admission MySQL repository and migration
- SLIM operation queue repository and migration
- admin readiness/progress UI
- Edge extension pairing and JSON API foundation
- short-lived bearer token, lock, and photo-token migrations
- Edge extension side panel foundation under `edge-extension/`
- SLIM page detector, sanitized inspection mode, and dry-run planning
- server-side admission fee tests
- SLIM operation generation tests
- extension API response safety tests
- Edge extension page-detector/inspector/dry-run tests
- dry-run legacy admission JSON import command

## Key Documents

- `docs/slim-integration-spec.md`
- `docs/slim-integration-progress.md`
- `docs/slim-field-inventory.md`
- `docs/extension-api.md`
- `edge-extension/README.md`
- `FindPilates_SLIM_最終設計.md`
- `FindPilates_Codex_実装プロンプト集.md`

## Migrations

Apply these migrations in order before using the admission and SLIM preparation workflow in a DB-backed environment:

```powershell
database/migrations/20260626_admissions_mysql.sql
database/migrations/20260626_admission_slim_operations.sql
database/migrations/20260626_extension_api.sql
```

Legacy admission JSON can be checked without writing:

```powershell
php .\scripts\import-admissions-json.php --source=.\public_html\admission\tmp\admissions.json
```

Add `--commit` only after applying the migration and confirming the source file is the intended legacy input.

## Checks

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-checks.ps1
```

If PHP execution is restricted by the environment, run the same command where `php.exe` is allowed.

Individual checks:

```powershell
php .\tests\trial_schedule_unit.php
php .\tests\admission_fee_unit.php
php .\tests\slim_operations_unit.php
php .\tests\extension_api_unit.php
node .\edge-extension\tests\run-tests.mjs
php .\scripts\import-admissions-json.php --source=.\tests\fixtures\admission_legacy_sample.json
```

## Private Data

Do not commit `config/findpilates.php`, runtime session/data files, backups, logs, mail data, admin seed SQL, customer data, or production DB dumps.
