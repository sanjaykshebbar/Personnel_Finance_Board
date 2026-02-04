@echo off
setlocal
echo Starting Expense Tracker...
echo.

:: Ensure DB Directory exists
if not exist "db" mkdir "db"

:: Check if PHP is in PATH
where php >nul 2>nul
if %errorlevel% equ 0 (
    set PHP_CMD=php
    goto :FOUND_PHP
)

:: Check Common Paths
if exist "C:\xampp\php\php.exe" (
    set PHP_CMD="C:\xampp\php\php.exe"
    goto :FOUND_PHP
)

if exist "C:\php\php.exe" (
    set PHP_CMD="C:\php\php.exe"
    goto :FOUND_PHP
)

echo Error: PHP not found in PATH or common locations (C:\xampp\php, C:\php).
echo Please install PHP or add it to your PATH.
echo.
pause
exit /b 1

:FOUND_PHP
echo Using PHP at: %PHP_CMD%
echo Access the site at http://localhost:8000
echo.
echo Press Ctrl+C to stop the server
echo.

%PHP_CMD% -S 0.0.0.0:8000
