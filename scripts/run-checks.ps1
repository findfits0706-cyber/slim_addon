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

Write-Host 'All checks passed.'
