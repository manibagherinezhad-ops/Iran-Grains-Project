param([switch]$SeedDemo, [switch]$NoOpen, [switch]$ResetLocalPassword)
$ErrorActionPreference = 'Stop'
$backendRoot = $PSScriptRoot
$runtimeRoot = Join-Path $backendRoot '.runtime'
$coreRoot = Join-Path $runtimeRoot 'wordpress'
$coreVersion = '7.1.4'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
function Write-Utf8File([string]$Path, [string]$Text) {
    if ([string]::IsNullOrWhiteSpace($Text)) { throw 'Refusing to write an empty setup file.' }
    [System.IO.File]::WriteAllText($Path, $Text, $utf8NoBom)
}
function Invoke-LocalDocker {
    param([string[]]$DockerArgs, [string]$InputText = '', [switch]$AllowFailure)
    # Windows PowerShell 5 treats native stderr as a terminating PowerShell
    # error. Capture it through Process so expected exit code 1 can be checked.
    $info = New-Object System.Diagnostics.ProcessStartInfo
    $info.FileName = (Get-Command docker -ErrorAction Stop).Source
    foreach ($arg in $DockerArgs) {
        if ($arg -match '[\s"]') { throw 'Unexpected Docker setup argument.' }
    }
    $info.Arguments = $DockerArgs -join ' '
    $info.WorkingDirectory = $backendRoot
    $info.UseShellExecute = $false
    $info.CreateNoWindow = $true
    $info.RedirectStandardInput = $true
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $info
    try {
        [void]$process.Start()
        $stdoutTask = $process.StandardOutput.ReadToEndAsync()
        $stderrTask = $process.StandardError.ReadToEndAsync()
        if ($InputText) { $process.StandardInput.WriteLine($InputText) }
        $process.StandardInput.Close()
        $process.WaitForExit()
        $result = [pscustomobject]@{ ExitCode = $process.ExitCode; Output = $stdoutTask.GetAwaiter().GetResult(); Error = $stderrTask.GetAwaiter().GetResult() }
    } finally { $process.Dispose() }
    if ($result.ExitCode -ne 0 -and -not $AllowFailure) { throw ($result.Error + $result.Output) }
    return $result
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
$engine = Invoke-LocalDocker -DockerArgs @('info','--format={{.ServerVersion}}') -AllowFailure
if ($engine.ExitCode -ne 0) { throw 'Open Docker Desktop and wait until its engine is running.' }
[System.IO.Directory]::CreateDirectory($runtimeRoot) | Out-Null
$envPath = Join-Path $backendRoot '.env'
if (-not (Test-Path $envPath)) {
    Write-Utf8File $envPath ("DB_PASSWORD=$(New-LocalSecret)`nDB_ROOT_PASSWORD=$(New-LocalSecret)`nDEV_PORT=8090`nLOCAL_UID=1000`nLOCAL_GID=1000`n")
}
$devPort = 8090
foreach ($line in (Get-Content $envPath)) { if ($line -match '^DEV_PORT=(\d+)$') { $devPort = [int]$Matches[1] } }
$siteUrl = "http://localhost:$devPort"
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
    Write-Host 'Starting the local database and WordPress containers. The first run may download images...'
    $started = Invoke-LocalDocker -DockerArgs @('compose','up','-d','--wait')
    $installed = Invoke-LocalDocker -DockerArgs @('compose','exec','-T','web','wp','core','is-installed') -AllowFailure
    if ($installed.ExitCode -ne 0 -or $ResetLocalPassword) {
        $manager = @{ username = 'ig_manager'; password = (New-LocalSecret); url = "$siteUrl/wp-admin/" }
        $payload = @{ username = $manager.username; password = $manager.password; site_url = $siteUrl; reset = [bool]$ResetLocalPassword } | ConvertTo-Json -Compress
        Write-Host 'Completing the local WordPress installation...'
        $installation = Invoke-LocalDocker -DockerArgs @('compose','exec','-T','web','wp','eval-file','/opt/ig/install.php','--skip-wordpress') -InputText $payload
        $verified = Invoke-LocalDocker -DockerArgs @('compose','exec','-T','web','wp','core','is-installed') -AllowFailure
        if ($verified.ExitCode -ne 0) { throw 'WordPress installation was not completed.' }
        $managerPath = Join-Path $runtimeRoot 'manager.json'
        $temporaryPath = Join-Path $runtimeRoot 'manager.pending.json'
        Write-Utf8File $temporaryPath ($manager | ConvertTo-Json)
        $saved = Get-Content -Raw $temporaryPath | ConvertFrom-Json
        if (-not $saved.username -or -not $saved.password) { throw 'The local login file could not be verified.' }
        Move-Item $temporaryPath $managerPath -Force
    } else {
        try {
            $saved = Get-Content -Raw (Join-Path $runtimeRoot 'manager.json') | ConvertFrom-Json
            if (-not $saved.username -or -not $saved.password) { throw 'Missing credentials' }
        } catch {
            throw 'The local login file is missing or empty. Run this script with -ResetLocalPassword to rebuild it.'
        }
    }
    foreach ($wpArgs in @(
        @('plugin','activate','iran-grains-core'),
        @('theme','activate','iran-grains'),
        @('option','update','timezone_string','Asia/Tehran'),
        @('option','update','blog_public','0'),
        @('rewrite','structure','/%postname%/')
    )) {
        $step = Invoke-LocalDocker -DockerArgs (@('compose','exec','-T','web','wp') + $wpArgs)
        Write-Host $step.Output.Trim()
    }
    if ($SeedDemo) {
        Copy-Item (Join-Path $backendRoot 'dev/fixtures.json') (Join-Path $backendRoot 'dev/seed.json') -Force
        $demo = Invoke-LocalDocker -DockerArgs @('compose','exec','-T','web','wp','eval-file','/opt/ig/seed.php','--user=ig_manager')
        Write-Host $demo.Output.Trim()
    }
} finally { Pop-Location }
Write-Host "Website: $siteUrl/"
Write-Host "Admin: $siteUrl/wp-admin/admin.php?page=iran-grains"
Write-Host 'Local login details: Backend/.runtime/manager.json (open this private file with Notepad).'
if (-not $NoOpen) { Start-Process "$siteUrl/wp-admin/admin.php?page=iran-grains" }
