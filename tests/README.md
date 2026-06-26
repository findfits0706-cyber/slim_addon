# Test Entry

Current check command:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-checks.ps1
```

Use strict mode in CI or when PHP must be installed:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-checks.ps1 -Strict
```

The check currently:

- verifies required foundation documents and config example exist
- syntax-checks tracked PHP files when PHP is available
- runs `tests/trial_schedule_unit.php`
- runs `tests/admission_fee_unit.php`
- runs `tests/slim_operations_unit.php`
- runs the legacy admission JSON import command in dry-run mode with `tests/fixtures/admission_legacy_sample.json`

Individual checks:

```powershell
php .\tests\trial_schedule_unit.php
php .\tests\admission_fee_unit.php
php .\tests\slim_operations_unit.php
php .\scripts\import-admissions-json.php --source=.\tests\fixtures\admission_legacy_sample.json
```

DB-backed admission save/list and SLIM operation persistence behavior are not covered here until a safe local or staging MySQL database is provided.
