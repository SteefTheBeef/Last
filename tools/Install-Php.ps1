[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$version = '8.5.11'
$archiveName = "php-$version-nts-Win32-vs17-x64.zip"
$expectedHash = '0ea96e0d2b9b737a6036f05cf4e95c49313faa6d0f27bd97edb2742503f0c043'
$toolsDirectory = Join-Path $root '.tools'
$runtimeDirectory = Join-Path $toolsDirectory 'php'
$executable = Join-Path $runtimeDirectory 'php.exe'

if ($env:OS -ne 'Windows_NT' -or -not [Environment]::Is64BitOperatingSystem) {
    throw 'This installer requires 64-bit Windows. Install PHP 8.5 separately on other platforms.'
}

if (-not (Test-Path $executable)) {
    if (Test-Path $runtimeDirectory) {
        throw 'The PHP runtime directory already exists but is incomplete. Inspect it before retrying.'
    }
    New-Item -ItemType Directory -Path $toolsDirectory -Force | Out-Null
    $archive = Join-Path $toolsDirectory $archiveName
    Invoke-WebRequest -Uri "https://windows.php.net/downloads/releases/$archiveName" -OutFile $archive
    if ((Get-FileHash $archive -Algorithm SHA256).Hash -ne $expectedHash) {
        Remove-Item $archive
        throw 'PHP archive SHA-256 verification failed.'
    }
    Expand-Archive -Path $archive -DestinationPath $runtimeDirectory
    Remove-Item $archive
}

$actualVersion = & $executable -n -r 'echo PHP_VERSION;'
if ($LASTEXITCODE -ne 0 -or $actualVersion -ne $version) {
    throw "Expected PHP $version. If PHP cannot start, check the Microsoft Visual C++ 2015-2022 x64 runtime."
}

& $executable -c (Join-Path $PSScriptRoot 'php.ini') -d "extension_dir=$runtimeDirectory/ext" -v
if ($LASTEXITCODE -ne 0) {
    throw 'PHP configuration validation failed.'
}
Write-Host "Local PHP installed at $executable. System PATH and system PHP were not changed."
