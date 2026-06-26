# Test Entry

Current foundation check:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-checks.ps1
```

Use strict mode in CI or when PHP must be installed:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-checks.ps1 -Strict
```

At Prompt 0 time this workspace does not contain the existing PHP application, migrations, or SLIM HTML fixtures. The check therefore validates foundation files and runs `php -l` only for PHP files that exist.

When the real repository is added, extend this entrypoint with the existing project test command instead of replacing local project conventions.
