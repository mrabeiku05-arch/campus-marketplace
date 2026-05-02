$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$filesToUpload = @(
    'assets/css/style.css',
    'admin/header.php',
    'admin/users.php',
    'includes/header.php',
    'includes/footer.php',
    'assets/dist/app.css'
)
$ftpBase = 'ftp://ftp-campusmarketplace.alwaysdata.net/www'
$httpBase = 'https://campusmarketplace.alwaysdata.net'
$username = 'campusmarketplace'

foreach ($relativePath in $filesToUpload) {
    $localPath = Join-Path $repoRoot $relativePath
    if (-not (Test-Path $localPath)) {
        throw "Missing file: $relativePath"
    }
}

$plainPassword = $env:ALWAYSDATA_PASSWORD
if ([string]::IsNullOrWhiteSpace($plainPassword)) {
    $securePassword = Read-Host 'Enter your AlwaysData password' -AsSecureString
    $plainPassword = [System.Net.NetworkCredential]::new('', $securePassword).Password
}

if ([string]::IsNullOrWhiteSpace($plainPassword)) {
    throw 'AlwaysData password was not provided.'
}

$credential = "$username`:$plainPassword"

foreach ($relativePath in $filesToUpload) {
    $localPath = Join-Path $repoRoot $relativePath
    $remoteUrl = ($ftpBase.TrimEnd('/') + '/' + $relativePath)

    Write-Host "Uploading $relativePath ..." -ForegroundColor Cyan
    & curl.exe --noproxy "*" --ssl-reqd -T $localPath $remoteUrl -u $credential
    if ($LASTEXITCODE -ne 0) {
        throw "Upload failed for $relativePath"
    }
}

$resetFileName = 'opcache_reset_' + [guid]::NewGuid().ToString('N') + '.php'
$tempResetPath = Join-Path $env:TEMP $resetFileName
$resetPhp = "<?php if (function_exists('opcache_reset')) { @opcache_reset(); } echo 'OK';"
[System.IO.File]::WriteAllText($tempResetPath, $resetPhp, [System.Text.Encoding]::UTF8)

try {
    $remoteResetUrl = $ftpBase.TrimEnd('/') + '/' + $resetFileName
    Write-Host "Uploading $resetFileName ..." -ForegroundColor Cyan
    & curl.exe --noproxy "*" --ssl-reqd -T $tempResetPath $remoteResetUrl -u $credential
    if ($LASTEXITCODE -ne 0) {
        throw "Upload failed for $resetFileName"
    }

    Write-Host 'Resetting PHP opcode cache ...' -ForegroundColor Cyan
    & curl.exe --noproxy "*" "$httpBase/$resetFileName" --max-time 20
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not call remote opcache reset endpoint.'
    }

    Write-Host 'Cleaning up remote reset script ...' -ForegroundColor Cyan
    & curl.exe --noproxy "*" --ssl-reqd "ftp://ftp-campusmarketplace.alwaysdata.net/" -Q "DELE /www/$resetFileName" -u $credential
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Could not delete remote reset script $resetFileName. Please remove it manually if it remains."
    }
}
finally {
    if (Test-Path $tempResetPath) {
        Remove-Item -LiteralPath $tempResetPath -Force -ErrorAction SilentlyContinue
    }
}

Write-Host 'Flat UI cleanup deployment completed.' -ForegroundColor Green
