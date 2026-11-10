@echo off
rem Server lokal cepat (OPcache aktif lewat .php/opcache.ini). Analisis AI diproses langsung (QUEUE_CONNECTION=sync di .env),
rem jadi tidak ada pekerja antrean terpisah yang bisa mati atau memakai kode lama.
rem Hentikan server lama di port 8000 dulu. Tutup jendela ini untuk berhenti.
cd /d "%~dp0"
set PHP_INI_SCAN_DIR=%~dp0.php
php artisan serve
