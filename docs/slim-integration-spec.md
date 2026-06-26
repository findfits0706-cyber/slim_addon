# Find Pilates x SLIM SNG Integration Spec

Status: Prompt 0 foundation. This document is the working source of truth until real repository code and saved SLIM HTML are added.

## Purpose

Build a staged integration that receives a Find Pilates admission application once, stores and reviews it safely, and lets staff transfer only the required values into the current SLIM SNG screen. The first production version is a screen-by-screen assisted transfer. Staff still press the final SLIM registration button.

The core safety goals are:

- Keep input, fee rules, course rules, and SLIM operation order in one place.
- Transfer to the currently visible SLIM page only when the target application is fixed.
- Fill only empty fields. Do not overwrite different existing values.
- Make exceptions visible: missing fields, mismatches, unknown page, lock conflicts, and expired sessions.
- Keep the design ready for future automation without enabling full automatic registration now.

## Repository Baseline

Prompt 0 was run in `C:\Users\user\Desktop\MyProject\SLIM_Addon`.

Initial observed files:

- `FindPilates_SLIM_最終設計.md`
- `FindPilates_Codex_実装プロンプト集.md`

Imported after repository initialization:

- `public_html/`
- `database/migrations/`
- `schema.sql`
- existing project docs and tests
- `xserver_php/php.ini`
- secret-free `config/findpilates.example.php`

Still not present:

- saved SLIM HTML fixtures
- local test database credentials or a DB test double

Because saved SLIM HTML is not present, this document records confirmed product rules and marks SLIM field details that require real screen files as unresolved.

## System Architecture

```text
Public admission form
    |
    | HTTPS / server-side validation
    v
MySQL
  - admission data
  - sensitive admission data
  - photo metadata
  - fee snapshot
  - SLIM operation queue
    |
    +--> Admin UI
    |      - review original input
    |      - edit normalized transfer values
    |      - set actual procedure date
    |      - inspect SLIM progress and locks
    |      - issue Edge pairing codes
    |
    +--> Extension API
           - short-lived bearer token
           - minimum transfer data only
           v
Microsoft Edge side panel
    |
    - select and lock one application
    - detect current SLIM page
    - fill the current page only
    - download photo helper
    - report non-PII progress and errors
    v
SLIM SNG
```

WordPress migration, full SPA rewrite, native messaging, and automatic SLIM login are out of scope.

## Confirmed Courses

| Course ID | Code | Business label | Page type |
|---:|---|---|---|
| 151 | FP | Find Pilates basic standalone | admission_procedure |
| 135 | FP2 | Find Pilates double standalone | admission_procedure |
| 145 | P3 | Pilates basic addon | addition_notification |
| 146 | P3W | Pilates double addon | addition_notification |
| 80 | MA | Master member | admission_procedure |
| 130 | DF | Day free member | admission_procedure |
| 74 | GEH | Night and holiday member | admission_procedure |
| 133 | A34 | Night and holiday under 34 | admission_procedure |
| 140 | FM | Find members | admission_procedure |
| 141 | P1 | Pool 1 addon | addition_notification |
| 144 | S1 | Studio 1 addon | addition_notification |

Additional notification reason: `9999 / その他`.

Payment cycle: monthly payment.

Weekend membership is removed from new public applications. Legacy records containing `weekend` must be shown as old plan / needs review and must not be auto-transferred.

## Operation Model

Operations are generated per application as an ordered queue. Do not implement a fixed global four-step workflow.

### Find Pilates Standalone

| Application plan | Operations |
|---|---|
| basic | admission_procedure: 151 / FP |
| double | admission_procedure: 135 / FP2 |

### Existing Main-Gym Member Addon

| Application plan | Operations |
|---|---|
| basic | addition_notification: 145 / P3 |
| double | addition_notification: 146 / P3W |

If the existing main-gym member number is missing, the operation is not ready.

### Simultaneous Main-Gym and Pilates Admission

| Main-gym key | Operations |
|---|---|
| find_master | admission_procedure: 80 / MA -> addition_notification: 145 or 146 |
| day_free | admission_procedure: 130 / DF -> addition_notification: 145 or 146 |
| night_holiday | admission_procedure: 74 / GEH -> addition_notification: 145 or 146 |
| night_holiday_u34 | admission_procedure: 133 / A34 -> addition_notification: 145 or 146 |
| gym_free | admission_procedure: 140 / FM -> addition_notification: 145 or 146 |
| gym_pool | admission_procedure: 140 / FM -> addition_notification: 141 / P1 -> addition_notification: 145 or 146 |
| gym_studio | admission_procedure: 140 / FM -> addition_notification: 144 / S1 -> addition_notification: 145 or 146 |

The Pilates addon operation uses 145 for basic and 146 for double.

## Fee Rules

The PHP server-side fee service is authoritative. JavaScript may preview fees using server-rendered configuration, but posted prices, discounts, ratios, and visit counts are not trusted.

### Pilates Fees

| Plan | Monthly fee | Monthly visits |
|---|---:|---:|
| standalone basic | 8,800 | 8 |
| standalone double | 12,650 | 16 |
| addon basic | 3,850 | 8 |
| addon double | 7,700 | 16 |

### First-Month Weekly Proration

| Start day | Ratio | 8-visit plan | 16-visit plan |
|---|---:|---:|---:|
| 1-7 | 100% | 8 | 16 |
| 8-14 | 75% | 6 | 12 |
| 15-21 | 50% | 4 | 8 |
| 22-month end | 25% | 2 | 4 |

Round with PHP `PHP_ROUND_HALF_UP`.

Expected examples:

- 8,800 x 75% = 6,600
- 12,650 x 75% = 9,488
- 12,650 x 25% = 3,163
- 3,850 x 75% = 2,888
- 3,850 x 25% = 963
- 7,700 x 75% = 5,775

Main-gym monthly fees are handled as a separate component. Existing main-gym members must not be charged another main-gym first-month fee.

## Data Boundary

Use MySQL as the production persistence layer for applications. `admission/tmp/admissions.json` may exist only as legacy import input or compatibility read source during migration.

Minimum tables planned:

- `admissions`
- `admission_sensitive`
- `admission_preferences`
- `admission_photos`
- `admission_slim_operations`
- `admission_slim_events`
- `extension_pairing_codes`
- `extension_access_tokens`

Sensitive health information stays in `admission_sensitive` and is not returned to Edge extension APIs.

Photos are stored outside the public web root or in a non-directly-served protected area. Public URLs must not expose physical paths. Photo access for the extension uses short-lived signed or authenticated URLs.

Do not log full personal information values in application logs, extension logs, or SLIM operation event rows.

## API Boundary

Base path example: `/api/v1/extension/`.

Planned endpoints:

- `POST pair`
- `GET me`
- `GET admissions?scope=today|unregistered|in_progress|search&q=`
- `GET admissions/{applicationId}/transfer`
- `POST admissions/{applicationId}/lock`
- `POST admissions/{applicationId}/heartbeat`
- `DELETE admissions/{applicationId}/lock`
- `POST operations/{operationId}/fill-result`
- `POST operations/{operationId}/complete`
- `POST admissions/{applicationId}/member-number`
- `POST admissions/{applicationId}/photo-token`
- `GET photos/{signedToken}`

Authentication:

- Admin UI issues a one-use pairing code with a short expiry.
- Edge exchanges it for an expiring bearer token.
- Store token hashes only.
- Store tokens in extension session storage, not persistent extension storage.
- Never embed fixed API keys or SLIM credentials in extension source.

Every API response should include `Cache-Control: no-store`.

## SLIM Page Identification

Expected paths:

| Path fragment | Page type | Initial role |
|---|---|---|
| `/slim/web/m/sng/front/admission_procedure/` | `admission_procedure` | admission form transfer |
| `/slim/web/m/sng/front/view_basic_user/` | `view_basic_user` | post-registration review or correction |
| `/slim/web/m/sng/front/view_image_survey/` | `view_image_survey` | photo/survey/other review only |
| `/slim/web/m/sng/front/addition_notification/` | `addition_notification` | addon notification transfer |

Saved HTML fixtures are not present in this workspace yet, so stable IDs, headings, frame behavior, success DOM, and error DOM remain unverified.

The detector must inspect iframes in the real screen even when saved HTML does not contain an iframe.

## Selector Policy

Stable selectors may use:

- meaningful IDs
- associated labels
- headings and section titles
- exact candidate text from reviewed page profiles
- page path and expected stable fingerprint

Selectors must not depend on:

- generated `data-v-*` attributes
- pure `nth-child` structure
- CSS hashes
- visual order alone
- partial or fuzzy course matching

Course selection must use a reviewed allowlist of exact SLIM option texts. If zero or multiple options match, stop and mark the operation as needs review.

## Fill Rules

- Require a fixed target application and active API lock.
- Require current SLIM page type to match the current operation.
- Fill empty fields only.
- Treat normalized same values as matched and skip.
- Treat different non-empty values as differences and do not overwrite.
- Re-read every changed field and verify the displayed value.
- Highlight filled, difference, missing, and error fields without breaking SLIM layout.
- Do not touch public identity document file inputs as face photo inputs.
- Do not press SLIM registration buttons in the initial version.

## Stop Conditions

The transfer must stop or become unavailable when any condition applies:

- unknown SLIM page
- no fixed target application
- another staff lock exists
- missing actual procedure date
- missing start date
- missing phone type
- existing main-gym member without member number
- course candidate not exactly one allowlisted match
- different existing value
- required field cannot be detected
- registration member number rule is not confirmed and SLIM requires it
- SLIM login expired
- API token expired
- page fingerprint mismatch
- network or version conflict

## Initial Version vs Future Version

Initial version:

- assisted transfer per current SLIM screen
- staff confirms and presses final SLIM registration
- photo download helper only
- no automatic login
- no automatic final submit
- no guessed member number generation

Future version may add automatic navigation and submit only after:

- success and error DOM are saved and reviewed
- member number generation/acquisition is confirmed
- operation idempotency and rollback behavior are verified
- emergency stop feature flag is active
- real pilot results are stable

Default future flags:

- `auto_navigation=false`
- `auto_submit=false`

## Next Required Inputs

To continue beyond Prompt 1 preparation, add the remaining non-sensitive fixtures:

- saved SLIM HTML for the four target pages
- anonymous test application data
- a safe local test database or a documented DB test strategy

Do not add production DB dumps, real customer photos, SLIM credentials, mail credentials, or production logs.
