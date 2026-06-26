# Find Pilates x SLIM SNG Integration

Find Pilates admission form, admin workflow, Edge extension, and SLIM SNG assisted-transfer integration workspace.

## Current Status

This repository currently contains the Prompt 1 public admission form and MySQL persistence foundation plus the existing Find Pilates PHP application copied from the local `findpilates.jp` workspace.

- integration specification
- progress checklist
- SLIM field inventory placeholder
- secret-free configuration example
- local check script
- existing PHP application under `public_html/`
- existing schema and migration files
- admission MySQL repository and migration
- server-side admission fee tests
- dry-run legacy admission JSON import command

## Key Documents

- `docs/slim-integration-spec.md`
- `docs/slim-integration-progress.md`
- `docs/slim-field-inventory.md`
- `FindPilates_SLIM_最終設計.md`
- `FindPilates_Codex_実装プロンプト集.md`

## Prompt 1 Migration

Apply this migration before using the admission form in a DB-backed environment:

```powershell
database/migrations/20260626_admissions_mysql.sql
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
php .\scripts\import-admissions-json.php --source=.\tests\fixtures\admission_legacy_sample.json
```

## Private Data

Do not commit `config/findpilates.php`, runtime session/data files, backups, logs, mail data, admin seed SQL, customer data, or production DB dumps.
