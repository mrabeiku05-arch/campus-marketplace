$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$files = @(
    'assets/css/dashboard-v2.css',
    'includes/seller_dashboard_workspace.php',
    'backend/routes/users.php',
    'backend/routes/notifications.php',
    'backend/routes/payments.php',
    'backend/routes/admin/products.php',
    'backend/helpers/functions.php',
    'db.php'
)

$ftpBase = 'ftp://ftp-campusmarketplace.alwaysdata.net/www'
$httpBase = 'https://campusmarketplace.alwaysdata.net'
$username = 'campusmarketplace'
$plainPassword = $env:ALWAYSDATA_PASSWORD
if ([string]::IsNullOrWhiteSpace($plainPassword)) {
    $plainPassword = 'Brooklyn@2005'
}

$credential = "$username`:$plainPassword"

foreach ($relativePath in $files) {
    $localPath = Join-Path $repoRoot $relativePath
    if (-not (Test-Path $localPath)) {
        Write-Host "Skipping missing file: $relativePath" -ForegroundColor Yellow
        continue
    }

    $remoteUrl = ($ftpBase.TrimEnd('/') + '/' + $relativePath.Replace('\', '/'))
    Write-Host "Uploading $relativePath ..." -ForegroundColor Cyan
    
    & curl.exe --noproxy "*" --ssl-reqd --ftp-pasv -k --ftp-create-dirs --no-keepalive --max-time 30 -T $localPath $remoteUrl -u $credential
}

$resetFileName = 'opcache_reset_' + [guid]::NewGuid().ToString('N') + '.php'
$tempResetPath = Join-Path $env:TEMP $resetFileName
$resetPhp = "<?php if (function_exists('opcache_reset')) { @opcache_reset(); } echo 'OK';"
[System.IO.File]::WriteAllText($tempResetPath, $resetPhp, [System.Text.Encoding]::UTF8)

try {
    $remoteResetUrl = $ftpBase.TrimEnd('/') + '/' + $resetFileName
    Write-Host "Uploading $resetFileName ..." -ForegroundColor Cyan
    & curl.exe --noproxy "*" --ssl-reqd --ftp-pasv -k -T $tempResetPath $remoteResetUrl -u $credential
    
    Write-Host 'Resetting PHP opcode cache ...' -ForegroundColor Cyan
    & curl.exe --noproxy "*" "$httpBase/$resetFileName" --max-time 20
    
    Write-Host 'Cleaning up remote reset script ...' -ForegroundColor Cyan
    & curl.exe --noproxy "*" --ssl-reqd --ftp-pasv -k "ftp://ftp-campusmarketplace.alwaysdata.net/" -Q "DELE /www/$resetFileName" -u $credential
}
catch {
    Write-Warning "OpCache reset failed: $($_.Exception.Message)"
}
finally {
    if (Test-Path $tempResetPath) {
        Remove-Item -LiteralPath $tempResetPath -Force -ErrorAction SilentlyContinue
    }
}

Write-Host 'Patch deployment process finished.' -ForegroundColor Green
