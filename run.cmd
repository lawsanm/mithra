@echo off
rem Start the dev server (public/router.php) and open the app in the browser.
setlocal
cd /d "%~dp0"

rem Locate PHP: PATH first, then the XAMPP default install.
set "PHP=C:\xampp\php\php.exe"
where php >nul 2>nul && set "PHP=php"
if not exist "%PHP%" if /i not "%PHP%"=="php" (
    echo Could not find PHP. Install XAMPP or add php to your PATH.
    exit /b 1
)

start "" /b cmd /c "timeout /t 2 >nul & start http://localhost:8123/"
echo Serving http://localhost:8123/  (Ctrl+C to stop)
"%PHP%" -S localhost:8123 -t public public/router.php
