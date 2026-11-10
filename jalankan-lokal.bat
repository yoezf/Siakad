@echo off
rem Server lokal cepat (OPcache aktif lewat .php/opcache.ini) + pekerja antrean untuk Analisis AI.
rem Hentikan server lama di port 8000 dulu. Tutup jendela ini untuk berhenti; tutup juga jendela "Antrean AI".
cd /d "%~dp0"
set PHP_INI_SCAN_DIR=%~dp0.php
start "Antrean AI (jangan ditutup)" cmd /k php artisan queue:listen --sleep=1 --tries=3 --timeout=150
php artisan serve
