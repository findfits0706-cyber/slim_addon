# Find Pilates x SLIM SNG Integration

Find Pilates admission form, admin workflow, Edge extension, and SLIM SNG assisted-transfer integration workspace.

## Current Status

This repository currently contains the Prompt 0 foundation plus the existing
Find Pilates PHP application copied from the local `findpilates.jp` workspace.

- integration specification
- progress checklist
- SLIM field inventory placeholder
- secret-free configuration example
- local check script
- existing PHP application under `public_html/`
- existing schema and migration files

## Key Documents

- `docs/slim-integration-spec.md`
- `docs/slim-integration-progress.md`
- `docs/slim-field-inventory.md`
- `FindPilates_SLIM_最終設計.md`
- `FindPilates_Codex_実装プロンプト集.md`

## Checks

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-checks.ps1
```

If PHP execution is restricted by the environment, run the same command where `php.exe` is allowed.

Existing unit test:

```powershell
php .\tests\trial_schedule_unit.php
```

## Private Data

Do not commit `config/findpilates.php`, runtime session/data files, backups,
logs, mail data, admin seed SQL, customer data, or production DB dumps.
