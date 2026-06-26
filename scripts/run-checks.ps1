param(
    [switch]$Strict
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$required = @(
    'docs/slim-integration-spec.md',
    'docs/slim-integration-progress.md',
    'docs/slim-field-inventory.md',
    'config/findpilates.example.php'
)

Write-Host 'Checking foundation files...'
foreach ($relative in $required) {
    $path = Join-Path $root $relative
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Missing required file: $relative"
    }
    Write-Host "  OK $relative"
}

$php = Get-Command php -ErrorAction SilentlyContinue
if ($null -eq $php) {
    $message = 'PHP is not available on PATH; skipping PHP syntax checks.'
    if ($Strict) {
        throw $message
    }
    Write-Host $message
    exit 0
}

$excludedParts = @(
    '\backups\',
    '\vendor\',
    '\storage\',
    '\private\'
)

$phpFiles = @(Get-ChildItem -LiteralPath $root -Recurse -File -Filter '*.php' |
    Where-Object {
        $full = $_.FullName
        foreach ($part in $excludedParts) {
            if ($full.Contains($part)) {
                return $false
            }
        }
        return $true
    })

if ($phpFiles.Count -eq 0) {
    Write-Host 'No PHP files found to syntax-check.'
    exit 0
}

Write-Host 'Running PHP syntax checks...'
foreach ($file in $phpFiles) {
    & php -l $file.FullName
    if ($LASTEXITCODE -ne 0) {
        throw "PHP syntax check failed: $($file.FullName)"
    }
}

$unitTests = @(
    'tests/trial_schedule_unit.php',
    'tests/admission_fee_unit.php',
    'tests/slim_operations_unit.php',
    'tests/extension_api_unit.php'
)

Write-Host 'Running PHP unit smoke tests...'
foreach ($relative in $unitTests) {
    $testPath = Join-Path $root $relative
    if (-not (Test-Path -LiteralPath $testPath)) {
        if ($Strict) {
            throw "Missing test file: $relative"
        }
        Write-Host "  SKIP $relative"
        continue
    }

    & php $testPath
    if ($LASTEXITCODE -ne 0) {
        throw "PHP test failed: $relative"
    }
}

$importScript = Join-Path $root 'scripts/import-admissions-json.php'
$legacyFixture = Join-Path $root 'tests/fixtures/admission_legacy_sample.json'
if ((Test-Path -LiteralPath $importScript) -and (Test-Path -LiteralPath $legacyFixture)) {
    Write-Host 'Running legacy admission JSON import dry-run...'
    & php $importScript "--source=$legacyFixture"
    if ($LASTEXITCODE -ne 0) {
        throw 'Legacy admission JSON import dry-run failed.'
    }
}

$node = Get-Command node -ErrorAction SilentlyContinue
$edgeTestScript = Join-Path $root 'edge-extension/tests/run-tests.mjs'
if ((Test-Path -LiteralPath $edgeTestScript) -and $null -ne $node) {
    Write-Host 'Running Edge extension JavaScript syntax checks...'
    $edgeRoot = Join-Path $root 'edge-extension'
    $edgeJsFiles = @(Get-ChildItem -LiteralPath $edgeRoot -Recurse -File -Filter '*.js')
    foreach ($file in $edgeJsFiles) {
        & node --check $file.FullName
        if ($LASTEXITCODE -ne 0) {
            throw "Edge extension JavaScript syntax check failed: $($file.FullName)"
        }
    }

    Write-Host 'Running Edge extension unit tests...'
    & node $edgeTestScript
    if ($LASTEXITCODE -ne 0) {
        throw 'Edge extension unit tests failed.'
    }
} elseif ((Test-Path -LiteralPath $edgeTestScript) -and $Strict) {
    throw 'Node is not available on PATH; cannot run Edge extension unit tests.'
}

Write-Host 'All checks passed.'
