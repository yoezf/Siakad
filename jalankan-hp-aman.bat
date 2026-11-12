@echo off
rem Server lokal dengan HTTPS untuk dibuka dari HP di jaringan Wi-Fi yang sama. Dipakai untuk fitur kamera (absensi wajah),
rem karena peramban HP hanya mengizinkan kamera lewat HTTPS. Sertifikat dibuat sendiri (self-signed), jadi peramban akan memberi peringatan:
rem pilih "Lanjutkan" / "Advanced > Proceed". Server Laravel hanya terbuka di komputer ini; yang terbuka ke jaringan hanya proksi HTTPS (port 8443).
rem PERINGATAN: pakai hanya di jaringan tepercaya dan dengan data uji. Hentikan dengan menutup jendela ini.
cd /d "%~dp0"
set PHP_INI_SCAN_DIR=%~dp0.php
set TRUST_PROXY_LOKAL=true

for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "(Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } | Select-Object -First 1).IPv4Address.IPAddress"`) do set IP=%%i

if not exist .https mkdir .https
rem Sertifikat dibuat ulang tiap dijalankan agar selalu cocok dengan alamat IP saat ini (berlaku 30 hari, tersimpan hanya di folder .https).
powershell -NoProfile -Command "$c = New-SelfSignedCertificate -Subject 'CN=localhost' -TextExtension @('2.5.29.17={text}DNS=localhost&IPAddress=%IP%&IPAddress=127.0.0.1') -CertStoreLocation Cert:\CurrentUser\My -NotAfter (Get-Date).AddDays(30); $p = ConvertTo-SecureString 'lokal' -AsPlainText -Force; Export-PfxCertificate -Cert $c -FilePath '.https\lokal.pfx' -Password $p | Out-Null; Remove-Item ('Cert:\CurrentUser\My\' + $c.Thumbprint)"

start "Proksi HTTPS" /min cmd /c node tools\proxy-https.cjs 8443 8080 .https\lokal.pfx lokal
echo.
echo  Buka di HP (Wi-Fi yang sama):
echo.
echo      https://%IP%:8443/sekolah/login
echo.
echo  Peramban akan memperingatkan sertifikat: pilih Lanjutkan. Jangan tutup jendela ini selama dipakai.
echo.
php artisan serve --host=127.0.0.1 --port=8080
