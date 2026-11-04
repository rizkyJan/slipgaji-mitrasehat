$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
Write-Host "SETUP MITRA SEHAT PAYROLL (LARAVEL)" -ForegroundColor Cyan
if (Test-Path -LiteralPath '.env') {
    throw '.env sudah ada. Setup pertama kali dihentikan agar konfigurasi/data lama tidak tertimpa. Gunakan perintah Laravel secara manual bila ingin melanjutkan.'
}
if (!(Test-Path -LiteralPath 'vendor/autoload.php')) {
    throw 'Folder vendor belum ada. Jalankan composer install terlebih dahulu.'
}
if (!(Test-Path -LiteralPath 'database/database.sqlite')) {
    New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null
}
Copy-Item -LiteralPath '.env.example' -Destination '.env'
php artisan key:generate
if ($LASTEXITCODE -ne 0) { throw 'Gagal membuat APP_KEY.' }
php artisan migrate --seed
if ($LASTEXITCODE -ne 0) { throw 'Migrasi/seed gagal. Periksa PDO SQLite atau konfigurasi database.' }
php artisan payroll:make-admin
if ($LASTEXITCODE -ne 0) { throw 'Pembuatan admin gagal.' }
Write-Host "Selesai. Jalankan .\JALANKAN_LOKAL.bat lalu buka http://127.0.0.1:8000" -ForegroundColor Green
