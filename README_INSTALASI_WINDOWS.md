# MITRA SEHAT PAYROLL — Laravel 13

Aplikasi penggajian internal Klinik Pratama Mitra Sehat. Basis proyek berasal dari `slipgaji.rar` yang diberikan pemilik, lalu ditambahkan modul payroll.

## 1. Persyaratan

- **PHP 8.3+**; aktifkan ekstensi `pdo_sqlite` (SQLite) atau `pdo_mysql` (MySQL), `openssl`, `mbstring`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`. Cek: `php -m`.
- Composer untuk menginstal dependency kalau folder `vendor` hilang. Paket ini **sudah menyertakan `vendor`** dari arsip awal, sehingga umumnya tidak perlu `composer install` untuk percobaan pertama.
- Browser modern. Tidak perlu npm / Vite untuk UI payroll: CSS statis ada di `public/payroll.css`.
- SQLite default menggunakan `database/database.sqlite`. Alternatif: MySQL terpisah khusus payroll, **jangan menggunakan database Khanza**.

## 2. Cara menjalankan di Windows (PowerShell)

Ekstrak ZIP ke folder sendiri, misalnya `C:\webapps\slipgaji`.

```powershell
cd C:\webapps\slipgaji
php -v
php -m
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan payroll:make-admin
php artisan serve --host=127.0.0.1 --port=8000
```

Saat `payroll:make-admin`, masukkan nama, email, dan password **minimal 12 karakter** (password tidak tampil di terminal).
Buka: **http://127.0.0.1:8000** dan login menggunakan akun admin yang baru dibuat.

Jika `php artisan` memberi pesan `vendor/autoload.php` tidak ditemukan, jalankan `composer install` lebih dulu. Jika ada pesan `could not find driver`, aktifkan ekstensi `pdo_sqlite`/`pdo_mysql` pada `php.ini` yang digunakan oleh perintah `php`, restart terminal, lalu cek kembali `php -m`.

**Penting:** jangan menjalankan `php artisan migrate:fresh` pada database berisi data karyawan. Jangan bagikan file `.env` atau database yang sudah berisi data penggajian.

**Cara alternatif (otomatis setup pertama kali):** buka PowerShell pada folder proyek, jalankan `powershell -ExecutionPolicy Bypass -File .\SETUP_PERTAMA_KALI.ps1`, lalu `.\JALANKAN_LOKAL.bat`. Script akan **berhenti** jika `.env` sudah ada agar tidak mengubah konfigurasi sebelumnya.

## 3. Cara memakai payroll

1. Buka **Pengaturan → UMK**; data awal 2025 Rp2.359.488 dan 2026 Rp2.500.000 untuk Kabupaten Sukoharjo. **Tahun lain harus diisi secara resmi** sebelum penggajian tahun itu.
2. Buka **Pengaturan → BPJS** dan verifikasi kelas risiko JKK, kepesertaan JP, plafon JP yang berlaku, serta apakah pelaporan BPJS TK sesuai dasar upah. Angka awal: Kesehatan 1% pegawai/4% klinik; JHT 2%/3,7%; JKK 0,54% klinik (contoh risiko rendah); JKM 0,3% klinik; JP 1%/2% bila karyawan terdaftar.
3. **Data Karyawan → Tambah Karyawan**: isi nama, kode, gaji pokok, tunjangan, uang makan, email dan kepesertaan BPJS.
4. **Penggajian → Proses Gaji Baru**: pilih karyawan dan periode. Klik **Gunakan Gaji Sebelumnya** bila ada. Semua komponen penghasilan dan potongan lama tersalin sebagai *draft*, tetapi BPJS/UMK periode baru dihitung ulang.
5. Edit angka, bonus, potongan, dan jumlah anggota keluarga tambahan. Pratinjau dihitung oleh server setiap ada perubahan.
6. **Simpan Draft → Periksa → Setujui & Terbitkan**. Draft boleh diedit/dihapus, slip yang disetujui dikunci. Gunakan **Lihat / Cetak PDF**.
7. **Pengiriman Email**: kirim satu orang di detail slip atau pilih hingga 20 slip untuk pengiriman massal. Sistem menolak pengiriman ulang slip yang sudah tercatat berhasil.

### Cara perhitungan

- **Penghasilan tunai** = gaji pokok + tunjangan tetap + uang makan + bonus/penerimaan tambahan.
- **BPJS Kesehatan**: dasar = minimal UMK tahun tersebut, maksimal plafon Kesehatan, menggunakan upah pokok + tunjangan tetap (termasuk uang makan hanya jika ditandai tunjangan tetap); potongan pekerja = 1%; iuran perusahaan = 4% (tarif bisa berubah sesuai aturan tanggal berlaku). Nilai dasar yang benar-benar dilaporkan dapat diisi sebagai penyesuaian (misalnya pembulatan), namun sistem tetap menghormati upah tetap aktual dan batas UMK.
- **BPJS Ketenagakerjaan**: dasar = upah dilaporkan (`reported_tk_wage`) bila diisi, atau gaji pokok + tunjangan tetap. **Tidak otomatis menggunakan UMK** kecuali toggle khusus di Pengaturan BPJS diaktifkan setelah diverifikasi cocok dengan pelaporan yang sah. Potongan JHT pegawai 2%; iuran JHT perusahaan 3,7%; JKK sesuai kelas risiko; JKM 0,3%; JP jika terdaftar, hanya bila plafon JP sudah dimasukkan. Jumlah iuran TK perusahaan pada format slip klasik dibulatkan gabungan agar konsisten dengan slip contoh.
- Anggota keluarga tambahan BPJS Kesehatan = 1% dari dasar Kesehatan per tambahan anggota yang terdaftar.
- **Gaji bersih** = penghasilan tunai – potongan pegawai. Bagian BPJS yang ditanggung klinik tidak mengurangi gaji bersih.
- **Slip klasik** menampilkan iuran klinik pada penerimaan dan pengurangan untuk meniru format historis. **Slip modern** menampilkan biaya klinik secara terpisah.
- **PPh 21 belum otomatis.** Bila diperlukan, tambahkan sebagai potongan manual, atau perluas modul pajak berdasarkan ketentuan berlaku.

**Validasi sebelum produksi:** angka dasar iuran dan klasifikasi kategori kepesertaan BPJS harus disesuaikan dengan dokumen/penagihan BPJS yang benar-benar dimiliki klinik. Periode yang sudah disetujui tetap menggunakan *snapshot* aturan dan angka saat persetujuan, tidak berubah ketika tarif/UMK diubah.

## 4. Hubungkan Gmail sekali saja (Google OAuth)

**Satu proyek OAuth Google Cloud Console** untuk aplikasi. Setelah konfigurasi awal, admin cukup menghubungkan akun Google sendiri lewat tombol **Hubungkan dengan Google**. Refresh token terenkripsi tersimpan hingga admin memilih **Putuskan**, atau akses dicabut/kedaluwarsa oleh Google.

1. Di Google Cloud Console, buat/pilih proyek, aktifkan **Gmail API**.
2. Konfigurasi **OAuth consent screen** beserta audience/test users (jika masih Testing); lengkapi persyaratan publikasi/verifikasi sesuai kategori dan scope `gmail.send`.
3. Buat **OAuth Client ID** tipe **Web application**.
4. Tambahkan *Authorized redirect URI*, untuk lokal: `http://127.0.0.1:8000/settings/email/callback`. Untuk situs produksi: `https://DOMAIN-ANDA/settings/email/callback`.
5. Masukkan ke `.env`:

```dotenv
GOOGLE_CLIENT_ID=ISI_CLIENT_ID_ANDA
GOOGLE_CLIENT_SECRET=ISI_SECRET_ANDA
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/settings/email/callback
```

6. Jalankan `php artisan config:clear`, refresh aplikasi, buka **Pengaturan → Hubungkan dengan Google**, pilih akun dan izinkan akses. Email dikirim melalui **Gmail API**, bukan SMTP atau App Password. Attachment berformat PDF.

**OAuth tidak benar-benar bebas dari sesi:** login lokal Laravel tetap memakai session untuk melindungi aplikasi, dan OAuth memakai state sementara untuk mencegah CSRF. Tetapi **Google tidak perlu login ulang setiap mengirim email** selama refresh token masih valid. Logout dari website tidak memutus koneksi Google. Pilihan memutus Google tersedia terpisah di Pengaturan.

Jika Google OAuth masih dalam mode **Testing**, refresh token untuk scope Gmail dapat kedaluwarsa cepat (biasanya tujuh hari); aplikasi harus dipublikasikan/diverifikasi jika diperlukan agar beroperasi jangka panjang.

## 5. Deploy produksi dengan aman

- Jalankan lewat Apache/Nginx document root **hanya folder `public/`**, jangan expose folder proyek `.env`, database SQLite, atau `storage/`. Gunakan HTTPS.
- Set `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://DOMAIN-ANDA`, `SESSION_SECURE_COOKIE=true`.
- Jalankan `php artisan config:clear`, `php artisan config:cache`, `php artisan view:cache` hanya setelah semua ekstensi PHP yang dibutuhkan terpasang, lalu uji kembali.
- Backup database dan `.env` secara **aman dan terenkripsi**, lindungi password dan token OAuth; kehilangan `APP_KEY` membuat token terenkripsi tak bisa dibaca. Jangan menjalankan `key:generate` ulang pada produksi yang sedang digunakan.
- Hanya pengguna yang login yang dapat melihat PDF. Jangan menyimpan slip gaji pada `public/storage` dan jangan membuat direct link file publik. Saat ini PDF dibuat saat diminta dan dikirim langsung oleh backend.
- Tes pengiriman dengan akun internal dulu sebelum mengirim slip asli massal. Pengiriman email dianggap selesai setelah Gmail mengembalikan ID pesan; jika terjadi timeout tak pasti, cek Sent di Gmail sebelum mengulangi.
- Hindari menjalankan server dev `php artisan serve` sebagai server publik produksi.

## 6. Perintah Laravel yang berguna

```powershell
php artisan about
php artisan route:list
php artisan migrate:status
php artisan migrate --seed
php artisan payroll:make-admin
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan test
php artisan serve --host=127.0.0.1 --port=8000
```

## 7. Struktur inti yang ditambahkan

- `app/Services/PayrollCalculator.php` — mesin hitung UMK/BPJS/bonus/potongan, snapshot.
- `app/Services/PayslipPdf.php` dan `PdfCanvas.php` — PDF A4 tanpa dependensi PDF tambahan.
- `app/Services/GmailService.php` — OAuth, refresh token, Gmail MIME attachment PDF.
- `app/Http/Controllers` — karyawan, penggajian, laporan, pengaturan, pengiriman.
- `database/migrations/2026_11_03_000100_create_payroll_tables.php` — tabel baru tanpa menyentuh tabel data klinik/Khanza.
- `database/seeders/DatabaseSeeder.php` — UMK 2025–2026, tarif referensi dan identitas klinik awal.
- `public/payroll.css` — seluruh antarmuka responsif, tidak perlu build front-end.
- `tests/Unit/PayrollCalculationTest.php` — regresi hasil slip contoh Rp2.053.000 dan Rp2.850.000.

**Batasan versi awal:** tidak ada integrasi absensi otomatis, THR otomatis, PPh 21 otomatis, perbaikan slip yang sudah diterbitkan (draft boleh diedit), pengiriman ulang paksa setelah status berhasil, e-signature tersertifikasi, atau manajemen multi-role melalui UI. Hal-hal ini membutuhkan desain lanjutan agar tidak merusak audit penggajian.
