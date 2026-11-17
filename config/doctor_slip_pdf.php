<?php
// Pengaturan khusus TAMPILAN lampiran honor dokter.
// Periode kerja tetap 26 bulan sebelumnya s.d. 25 bulan ini (aturan kalkulator).
// Jadwal gajian hanya rencana, bukan bukti tanggal uang ditransfer.
return [
    // Contoh periode Oktober 2026: 26/09-25/10; jadwal gajian 26/10/2026.
    'payday_day' => (int) env('DOCTOR_SLIP_PAYDAY_DAY', 26),

    // Pengecualian tanggal gajian per periode tanpa mengubah source Git.
    // Contoh di .env: DOCTOR_SLIP_PAYDATES_JSON='{"2026-10":"2026-11-01"}'
    'paydate_by_period' => json_decode((string) env('DOCTOR_SLIP_PAYDATES_JSON', '{}'), true) ?: [],
];
