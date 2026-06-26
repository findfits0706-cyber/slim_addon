# Find Pilates x SLIM SNG Integration

Find Pilates admission form, admin workflow, Edge extension, and SLIM SNG assisted-transfer integration workspace.

## Current Status

This repository currently contains the Prompt 0 foundation:

- integration specification
- progress checklist
- SLIM field inventory placeholder
- secret-free configuration example
- local check script

The existing PHP application, schema/migrations, and saved SLIM HTML fixtures have not been added yet.

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
