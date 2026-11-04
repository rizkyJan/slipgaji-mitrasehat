@echo off
cd /d "%~dp0"
echo ==================================================
echo   MITRA SEHAT PAYROLL - SERVER LOKAL
 echo   URL: http://127.0.0.1:8000
 echo   Tekan Ctrl+C untuk menghentikan server.
echo ==================================================
php artisan serve --host=127.0.0.1 --port=8000
pause
