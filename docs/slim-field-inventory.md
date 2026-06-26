# SLIM Field Inventory

Status: no saved SLIM HTML fixtures are present in this workspace. This inventory records confirmed target pages from the provided design and leaves field-level selectors unresolved until HTML is available.

Do not fill unresolved fields by guessing. Do not use generated `data-v-*` attributes as stable selectors.

## Target Pages

| Page type | Path fragment | HTML fixture | Notes |
|---|---|---|---|
| `admission_procedure` | `/slim/web/m/sng/front/admission_procedure/` | Missing | Expected to contain admission items, basic items, survey, and other image sections. |
| `view_basic_user` | `/slim/web/m/sng/front/view_basic_user/` | Missing | Review/correction page, not required for the initial new-admission flow unless explicitly queued. |
| `view_image_survey` | `/slim/web/m/sng/front/view_image_survey/` | Missing | Saved design says visible file inputs were public identity document fields, not face photo fields. |
| `addition_notification` | `/slim/web/m/sng/front/addition_notification/` | Missing | Used for Pilates addon and main-gym option addon operations. |

## Admission Procedure: Planned Fields

These mappings are expected by the prompt set but still require fixture verification.

| Source key | Expected SLIM target | Stable selector status | Input type | Notes |
|---|---|---|---|---|
| `surname` | surname / sei | Unverified | text | Prompt mentions `#sei_name`; verify in HTML. |
| `given_name` | given name / mei | Unverified | text | Prompt mentions `#mei_name`; verify in HTML. |
| `surname_kana` | kana sei | Unverified | text | Prompt mentions `#kana_sei`; verify in HTML. |
| `given_name_kana` | kana mei | Unverified | text | Prompt mentions `#kana_mei`; verify in HTML. |
| `gender` | sex | Unverified | custom select | Exact candidates required. |
| `actual_procedure_date` | admission date | Unverified | date picker | Do not bypass readonly fields without verified profile support. |
| `course` | course | Unverified | custom select | Exact allowlist only. |
| `payment_cycle` | payment cycle | Unverified | custom select | Monthly payment candidate text must be captured from real screen. |
| `start_date` | usage start date | Unverified | date picker | Verify display format and picker behavior. |
| `entry_member_no` | registration member number | Unverified | unknown | Do not auto-fill until rule is confirmed. |
| `birth` | birthday | Unverified | date/text | Prompt mentions `#birthday`; verify format. |
| `email` | PC email address | Unverified | text | Initial target, configurable if real rule differs. |
| `phone` + `phone_type=home` | home TEL parts | Unverified | split text | Do not also fill mobile TEL. |
| `phone` + `phone_type=mobile` | mobile TEL parts | Unverified | split text | Do not also fill home TEL. |
| `postal_code` | postal code | Unverified | split/text | Verify hyphen behavior. |
| `prefecture` | prefecture | Unverified | custom select | Exact candidate required. |
| `city_area` | address 1 | Unverified | text | Max length must be enforced server-side. |
| `street_address` | address 2 | Unverified | text | Max length must be enforced server-side. |
| `building` | address 3 | Unverified | text | Max length must be enforced server-side. |
| `emergency_phone` | emergency TEL 1 | Unverified | split text | Verify field labels. |
| `emergency_name` | emergency contact name 1 | Unverified | text | Verify field labels. |
| `guardian_name` | guardian name | Unverified | text | Conditional for minors. |

Fields not to touch in initial transfer:

- survey answers
- health and disease information
- DM preferences
- public identity document file inputs
- workplace fields
- discounts
- risk category
- final registration buttons

## Addition Notification: Planned Fields

| Source key | Expected SLIM target | Stable selector status | Input type | Notes |
|---|---|---|---|---|
| operation mode | new notification | Unverified | radio/select/button | Verify real UI. |
| `actual_procedure_date` | application date | Unverified | date picker | Uses confirmed admin procedure date, not public preferred date. |
| reason | `9999 / その他` | Unverified | custom select | Exact displayed text required. |
| `start_date` | usage start date | Unverified | date picker | Verify picker behavior. |
| operation course | course | Unverified | custom select | Exact allowlist only. |
| payment cycle | monthly payment | Unverified | custom select | Exact displayed text required. |
| member number | target member display | Unverified | readonly/search context | Do not input arbitrary member number without real rule. |

## Photo and Identity Document Fields

Confirmed from the design document:

- Saved HTML file inputs observed so far were public identity document front/back fields.
- They must not be treated as member face photo fields.
- Initial implementation is download-only: `Downloads/FIND-SLIM/<application_id>.jpg`.

Unresolved:

- actual member face photo page
- selector and upload behavior for face photo, if any

## Unresolved Items for Real SLIM Analysis

- dynamic select candidate text for all target courses
- exact monthly payment candidate text
- exact `9999 / その他` reason display
- date picker DOM and supported input format
- registration member number rule and requiredness
- success URL, heading, and message
- representative required-field and duplicate-error DOM
- logout and timeout indicators
- iframe behavior in real screens
- stable profile fingerprints
