# Find Pilates SLIM Assistant

Microsoft Edge Manifest V3 extension for the Find Pilates to SLIM SNG assisted-transfer workflow.

Prompt 4 status: pairing, admission selection, API lock, SLIM page detection, inspection JSON, and dry-run only. It does not write values into SLIM and does not click registration buttons.

## Load In Edge

1. Open `edge://extensions`.
2. Enable developer mode.
3. Choose "Load unpacked".
4. Select this `edge-extension/` directory.
5. Open the side panel from the extension action.

## Permissions

The manifest uses only:

- `sidePanel`
- `scripting`
- `storage`
- `tabs`

Host permissions:

- `https://www.slim-sng.jp/*`
- `https://findpilates.jp/*`

`<all_urls>` is not used. The `downloads` permission is not used in Prompt 4; inspection JSON is saved with a panel-created Blob link.

## Pairing

1. In the admin site, open `/admin/extension.php`.
2. Press `Edgeを接続`.
3. Enter the one-time code in the side panel.

The access token is stored in `chrome.storage.session` only. `installation_id` and the API base URL are the only values stored in local storage.

Default API base URL:

```text
https://findpilates.jp/api/v1/extension
```

## Safe Workflow

The side panel states are:

- not paired
- paired and no admission selected
- selected admission with API lock
- SLIM login detected
- API token expired

Selecting an admission fetches transfer details, then acquires an API lock using the returned version. The lock is kept alive by heartbeat. Changing the target releases the lock.

## Inspection

`現在のSLIM画面を解析` injects `content/inspect-page.js` into the active SLIM tab and returns sanitized JSON:

- timestamp
- extension version
- page URL/path
- title
- h1/h2/h3 text
- frame summaries
- input/select/textarea/button metadata
- file input context
- registration button candidates

It excludes:

- input values
- password fields
- hidden token fields
- cookies
- `data-v-*` and build-hash classes
- page HTML

The panel can copy or save JSON as:

```text
slim-inspection-{page_type}-{timestamp}.json
```

## Dry Run

Dry-run compares the selected operation with the current detected SLIM page and sanitized controls. It reports `ready`, `warning`, or `blocked`, but does not write to the DOM.

Typical blockers:

- unknown SLIM page
- page type mismatch
- readiness errors from the API
- missing stable target fields

Typical warnings:

- unresolved registration member-number rule
- unverified custom select/date adapters
- photo or other API readiness warnings

## Tests

```powershell
node .\edge-extension\tests\run-tests.mjs
```

The repository check script runs this test automatically when Node is available.
