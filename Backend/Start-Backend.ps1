param([switch]$SeedDemo, [switch]$NoOpen)
$ErrorActionPreference = 'Stop'
$backendRoot = $PSScriptRoot
$runtimeRoot = Join-Path $backendRoot '.runtime'
$coreRoot = Join-Path $runtimeRoot 'wordpress'
$coreVersion = '7.1.4'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
function Write-Utf8File([string]$Path, [string]$Text) {
    [System.IO.File]::WriteAllText($Path, $Text, $utf8NoBom)
}
function New-LocalSecret {
    $bytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($bytes) } finally { $generator.Dispose() }
    return [BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
}
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Install Docker Desktop for Windows, open it, and run this script again.'
}
& docker info --format '{{.ServerVersion}}' *> $null
if ($LASTEXITCODE -ne 0) { throw 'Open Docker Desktop and wait until its engine is running.' }
[System.IO.Directory]::CreateDirectory($runtimeRoot) | Out-Null
$envPath = Join-Path $backendRoot '.env'
if (-not (Test-Path $envPath)) {
    Write-Utf8File $envPath ("DB_PASSWORD=$(New-LocalSecret)`nDB_ROOT_PASSWORD=$(New-LocalSecret)`nDEV_PORT=8090`nLOCAL_UID=1000`nLOCAL_GID=1000`n")
}
if (-not (Test-Path (Join-Path $coreRoot 'wp-load.php'))) {
    $archive = Join-Path $runtimeRoot 'core.zip'
    $unpack = Join-Path $runtimeRoot 'unpack'
    Write-Host 'Downloading WordPress from its official GitHub repository...'
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-WebRequest -UseBasicParsing -Uri "https://codeload.github.com/WordPress/WordPress/zip/refs/tags/$coreVersion" -OutFile $archive
    Expand-Archive -Path $archive -DestinationPath $unpack -Force
    Move-Item (Join-Path $unpack "WordPress-$coreVersion") $coreRoot
    Remove-Item $unpack -Recurse -Force
}
$configPath = Join-Path $coreRoot 'wp-config.php'
if (-not (Test-Path $configPath)) {
    $saltNames = 'AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'
    $saltLines = ($saltNames | ForEach-Object { "define('$_', '$(New-LocalSecret)');" }) -join "`n"
    $configText = @'
<?php
foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) define($key, getenv($key));
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
__IG_SALTS__
$table_prefix = 'ig_';
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('DISALLOW_FILE_EDIT', true);
define('WP_AUTO_UPDATE_CORE', false);
define('DISABLE_WP_CRON', true);
require_once __DIR__ . '/wp-settings.php';
'@
    Write-Utf8File $configPath $configText.Replace('__IG_SALTS__', $saltLines)
}
Push-Location $backendRoot
try {
    & docker compose up -d --wait
    if ($LASTEXITCODE -ne 0) { throw 'The local containers could not start.' }
    & docker compose exec -T web wp core is-installed *> $null
    if ($LASTEXITCODE -ne 0) {
        $manager = @{ username = 'ig_manager'; password = (New-LocalSecret); url = 'http://localhost:8090/wp-admin/' }
        Write-Utf8File (Join-Path $runtimeRoot 'manager.json') ($manager | ConvertTo-Json)
        $installCommand = "wp core install --url=http://localhost:8090 --title='Iran Grains' --admin_user=ig_manager --admin_email=manager@example.invalid --skip-email --prompt=admin_password"
        $installOutput = $manager.password | & docker compose exec -T web sh -c $installCommand 2>&1
        if ($LASTEXITCODE -ne 0) { throw 'WordPress installation failed. Its private output has not been printed.' }
        Remove-Variable installOutput
    }
    foreach ($wpArgs in @(
        @('plugin','activate','iran-grains-core'),
        @('theme','activate','iran-grains'),
        @('option','update','timezone_string','Asia/Tehran'),
        @('option','update','blog_public','0'),
        @('rewrite','structure','/%postname%/')
    )) {
        & docker compose exec -T web wp @wpArgs
        if ($LASTEXITCODE -ne 0) { throw 'A local WordPress setup step failed.' }
    }
    if ($SeedDemo) {
        Copy-Item (Join-Path $backendRoot 'dev/fixtures.json') (Join-Path $backendRoot 'dev/seed.json') -Force
        & docker compose exec -T web wp eval-file /opt/ig/seed.php --user=ig_manager
        if ($LASTEXITCODE -ne 0) { throw 'The local demo import failed.' }
    }
} finally { Pop-Location }
Write-Host 'Website: http://localhost:8090/'
Write-Host 'Admin: http://localhost:8090/wp-admin/admin.php?page=iran-grains'
Write-Host 'Local login details: Backend/.runtime/manager.json (open this private file with Notepad).'
if (-not $NoOpen) { Start-Process 'http://localhost:8090/wp-admin/admin.php?page=iran-grains' }
