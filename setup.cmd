@echo off
rem One-time local setup: config file + database + demo data.
rem Needs PHP and a running MySQL (XAMPP Control Panel - Start MySQL).
setlocal
cd /d "%~dp0"

rem Locate PHP: PATH first, then the XAMPP default install.
set "PHP=C:\xampp\php\php.exe"
where php >nul 2>nul && set "PHP=php"
if /i not "%PHP%"=="php" if not exist "%PHP%" (
    echo Could not find PHP. Install XAMPP or add php to your PATH.
    exit /b 1
)

if not exist config\config.php (
    copy config\config.example.php config\config.php >nul
    echo Created config\config.php from the example. Edit it if your MySQL settings differ.
)

"%PHP%" scripts\migrate.php
if errorlevel 1 (
    echo.
    echo Setup failed - see the message above.
    exit /b 1
)

echo.
echo Setup complete. Start the app with run.cmd
