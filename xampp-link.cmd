@echo off
rem Publish the app at http://localhost/mithra by pointing XAMPP's htdocs at
rem /public. Run once per machine; after that Apache serves the working copy
rem directly, so edits show up on refresh with nothing to copy.
setlocal
cd /d "%~dp0"

set "HTDOCS=C:\xampp\htdocs"
if not exist "%HTDOCS%" (
    echo Could not find %HTDOCS% - edit this script if XAMPP lives elsewhere.
    exit /b 1
)

if exist "%HTDOCS%\mithra" (
    echo %HTDOCS%\mithra already exists.
    echo Rename or remove it first, then run this script again.
    exit /b 1
)

mklink /J "%HTDOCS%\mithra" "%CD%\public"
if errorlevel 1 (
    echo Could not create the link.
    exit /b 1
)

echo.
echo Done. Start Apache and MySQL from the XAMPP Control Panel, then open
echo    http://localhost/mithra/
