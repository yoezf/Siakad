@echo off
rem Server lokal untuk diakses dari HP di jaringan Wi-Fi yang sama (port 8080) + pekerja antrean AI.
rem PERINGATAN: siapa pun di jaringan yang sama bisa membuka aplikasi ini. Pakai hanya di jaringan tepercaya (rumah atau kantor sendiri)
rem dan jangan jalankan dengan data asli. Hentikan setelah selesai dengan menutup kedua jendela.
cd /d "%~dp0"
set PHP_INI_SCAN_DIR=%~dp0.php

for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "(Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1).IPv4Address.IPAddress"`) do set IP=%%i

echo.
echo  Buka di HP (harus di Wi-Fi yang sama dengan komputer ini):
echo.
echo      http://%IP%:8080/sekolah/login
echo.
echo  Jangan tutup dua jendela ini selama dipakai.
echo.
start "Antrean AI (jangan ditutup)" cmd /k php artisan queue:work --sleep=1 --tries=3
php artisan serve --host=0.0.0.0 --port=8080
