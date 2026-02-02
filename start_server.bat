@echo off
echo Starting Expense Tracker...
echo Server will be accessible on local network
echo.
echo Access the site at:
echo   - Local: http://localhost:8000
echo   - Network: http://YOUR_IP:8000 (find your IP with 'ipconfig')
echo.
c:\xampp\php\php.exe -S 0.0.0.0:8000
pause
