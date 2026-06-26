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
| Confirm existing PHP files and routes | Ready: PHP repo copied; detailed review still needed |
| Remove JSON as production storage | Not started |
| Implement server fee service | Not started |
| Update public form fields | Not started |
| Implement MySQL migrations and repository | Not started |
| Implement idempotent submit | Not started |
| Implement photo protected storage | Not started |
| Add JSON import dry-run CLI | Not started |
| Update admin admissions compatibility | Not started |
| Add automated tests | Not started |

### Phase 2: Admin UI and Operation Queue

| Item | Status |
|---|---|
| Separate application status and SLIM status | Not started |
| Add normalized transfer values | Not started |
| Add actual procedure date workflow | Not started |
| Implement course catalog | Not started |
| Generate operations for all patterns | Not started |
| Add locks and audit events | Not started |

### Phase 3: Extension API and Short-Lived Auth

| Item | Status |
|---|---|
| Pairing code exchange | Not started |
| Extension bearer token storage | Not started |
| Minimum admissions list/detail responses | Not started |
| Lock heartbeat and release | Not started |
| Progress event endpoints | Not started |
| Photo token endpoint | Not started |

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
- `config/findpilates.example.php` had no PHP syntax errors.
- `php.exe` execution required elevated tool permission because the sandbox denied it.

Current expected behavior:

- verifies foundation files exist
- syntax-checks PHP files if PHP is installed
- safely reports when the application repository is not present

No production application test can run until `public_html/` and related files are added.

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
