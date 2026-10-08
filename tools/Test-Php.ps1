[CmdletBinding()]
param(
    [string]$PhpPath
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
if (-not $PhpPath) {
    $PhpPath = Join-Path $root '.tools/php/php.exe'
}
if (-not (Test-Path $PhpPath)) {
    throw 'PHP was not found. Run tools/Install-Php.ps1 or supply -PhpPath with a PHP 8.5 executable.'
}
$PhpPath = (Resolve-Path $PhpPath).Path
$phpArguments = @('-c', (Join-Path $PSScriptRoot 'php.ini'))
$localRuntime = Join-Path $root '.tools/php/php.exe'
if ($PhpPath -eq $localRuntime) {
    $phpArguments += @('-d', ('extension_dir=' + (Join-Path $root '.tools/php/ext')))
}

$reportDirectory = Join-Path $root '.tools/reports'
New-Item -ItemType Directory -Path $reportDirectory -Force | Out-Null
$report = Join-Path $reportDirectory 'php-validation.txt'
"PHP validation (UTC): $([DateTime]::UtcNow.ToString('o'))" | Set-Content $report
$failures = 0
Push-Location $root
try {
    $output = & $PhpPath @phpArguments (Join-Path $PSScriptRoot 'tests/runtime.php') 2>&1
    $runtimeExitCode = $LASTEXITCODE
    $output | Tee-Object -FilePath $report -Append | Write-Host
    if ($runtimeExitCode -ne 0) {
        throw "Runtime smoke tests failed. See $report."
    }

    $files = @(Get-ChildItem $root -Recurse -File -Filter '*.php' | Where-Object {
        $_.FullName -notmatch '[\\/](\.git|\.vs|\.tools|\.migration-backups|vendor)[\\/]'
    } | Sort-Object FullName)
    foreach ($file in $files) {
        $output = & $PhpPath @phpArguments -l $file.FullName 2>&1
        if ($LASTEXITCODE -ne 0) {
            $failures++
        }
        $output | Tee-Object -FilePath $report -Append | Write-Host
    }
    $summary = "Syntax checks: $($files.Count) files, $failures failures."
    $summary | Tee-Object -FilePath $report -Append | Write-Host
    if ($failures -eq 0) {
        foreach ($suite in @('xmlrpc.php', 'webaccess.php')) {
            $output = & $PhpPath @phpArguments (Join-Path $PSScriptRoot "tests/$suite") 2>&1
            if ($LASTEXITCODE -ne 0) {
                $failures++
            }
            $output | Tee-Object -FilePath $report -Append | Write-Host
        }
    }
    Write-Host "Report: $report"
} finally {
    Pop-Location
}
if ($failures -gt 0) {
    exit 1
}
