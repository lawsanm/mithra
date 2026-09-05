param(
    [string]$XamppRoot = 'C:\xampp',
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$php = Join-Path $XamppRoot 'php\php.exe'
$apache = Join-Path $XamppRoot 'apache\bin\httpd.exe'
$mysql = Join-Path $XamppRoot 'mysql\bin\mysqld.exe'
$vhosts = Join-Path $XamppRoot 'apache\conf\extra\httpd-vhosts.conf'

try {
    foreach ($file in @($php, $apache, $mysql, $vhosts)) {
        if (-not (Test-Path -LiteralPath $file)) {
            throw "Missing $file. Install XAMPP, or pass -XamppRoot with its location."
        }
    }

    # Create local credentials only once. Existing settings and data are kept.
    $config = Join-Path $projectRoot 'config\config.php'
    if (-not (Test-Path -LiteralPath $config)) {
        Copy-Item -LiteralPath (Join-Path $projectRoot 'config\config.example.php') -Destination $config
    }

    # Apache serves /mithra from this project's public folder.
    $publicPath = Join-Path $projectRoot 'public'
    $webLink = Join-Path $XamppRoot 'htdocs\mithra'
    if (Test-Path -LiteralPath $webLink) {
        $link = Get-Item -LiteralPath $webLink -Force
        if ($link.LinkType -ne 'Junction' -or $link.Target[0] -ine $publicPath) {
            throw "$webLink already exists and points elsewhere. Keep it safe; choose a different location before setup."
        }
    } else {
        New-Item -ItemType Junction -Path $webLink -Target $publicPath | Out-Null
    }

    # Undo only the root-site override made by the earlier launcher.
    $original = [IO.File]::ReadAllText($vhosts)
    $pattern = '(?ms)^# BEGIN MITHRA LOCALHOST\r?\n.*?^# END MITHRA LOCALHOST\r?\n?'
    $updated = [regex]::Replace($original, $pattern, '')

    $changed = $updated -ne $original
    if ($changed) {
        $backup = "$vhosts.mithra-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss-fff')"
        Copy-Item -LiteralPath $vhosts -Destination $backup
        [IO.File]::WriteAllText($vhosts, $updated, [Text.UTF8Encoding]::new($false))
        & $apache -t
        if ($LASTEXITCODE -ne 0) {
            Copy-Item -LiteralPath $backup -Destination $vhosts -Force
            throw 'Apache rejected the configuration. The previous configuration was restored.'
        }
        Write-Host "Restored the normal localhost site. Previous Apache configuration: $backup"
    }

    if (-not (Get-Process mysqld -ErrorAction SilentlyContinue)) {
        $mysqlConfig = Join-Path $XamppRoot 'mysql\bin\my.ini'
        Start-Process -FilePath $mysql -ArgumentList "--defaults-file=`"$mysqlConfig`"" -WorkingDirectory $XamppRoot -WindowStyle Hidden
    }

    # Wait for MySQL, then initialize only an empty database.
    $ready = $false
    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        & $php (Join-Path $PSScriptRoot 'check-database.php') 2>$null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) {
        throw 'MySQL is unavailable. Check XAMPP and config/config.php.'
    }
    & $php (Join-Path $PSScriptRoot 'migrate.php')
    if ($LASTEXITCODE -ne 0) { throw 'Database setup failed. See the message above.' }

    $runningApache = Get-Process httpd -ErrorAction SilentlyContinue
    if ($runningApache -and $changed) {
        throw 'Configuration saved. Restart Apache in XAMPP, then run run.cmd again.'
    }
    if (-not $runningApache) {
        Start-Process -FilePath $apache -WorkingDirectory (Join-Path $XamppRoot 'apache\bin') -WindowStyle Hidden
    }

    $ready = $false
    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        try {
            $response = Invoke-WebRequest 'http://localhost/mithra/' -UseBasicParsing -TimeoutSec 3
            if ($response.StatusCode -eq 200 -and $response.Content -match '<title>[^<]*Mithra') {
                $ready = $true
                break
            }
        } catch { }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) {
        throw 'Mithra did not respond at localhost/mithra. Check port 80 and the XAMPP Apache error log.'
    }

    Write-Host 'Mithra is ready. Open http://localhost/mithra/ in your browser.'
    Write-Host 'Apache and MySQL keep running after this window closes. Stop them through XAMPP.'
    if (-not $NoBrowser) { Start-Process 'http://localhost/mithra/' }
} catch {
    Write-Host "Could not start Mithra: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
