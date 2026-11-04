<?php
namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\MinimumWage;
use App\Models\PayrollRule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        MinimumWage::firstOrCreate(['year' => 2025], [
            'region' => 'Kabupaten Sukoharjo', 'amount' => 2359488,
            'reference' => 'Kepgub Jawa Tengah Nomor 561/45 Tahun 2024',
        ]);
        MinimumWage::firstOrCreate(['year' => 2026], [
            'region' => 'Kabupaten Sukoharjo', 'amount' => 2500000,
            'reference' => 'Kepgub Jawa Tengah 100.3.3.1/505 Tahun 2025',
        ]);
        PayrollRule::firstOrCreate(['effective_from' => '2025-01-01'], [
            'health_employee_pct' => 1, 'health_employer_pct' => 4, 'health_cap' => 12000000,
            'jht_employee_pct' => 2, 'jht_employer_pct' => 3.7,
            'jkk_employer_pct' => 0.54, 'jkm_employer_pct' => 0.3,
            'jp_employee_pct' => 1, 'jp_employer_pct' => 2,
            'jp_cap' => null, 'tk_use_umk_floor' => false,
            'notes' => 'Tarif acuan. JKK 0,54% hanya contoh risiko rendah. Periksa kelas risiko, batas JP terbaru dan data upah yang dilaporkan sebelum dipakai di produksi.',
        ]);
        foreach ([
            'clinic_name' => 'PT. Mitra Sehat Maju Sentos',
            'clinic_address' => 'Sukoharjo, Jawa Tengah',
            'slip_signer' => 'Petugas Payroll',
            'slip_style' => 'classic',
        ] as $key => $value) {
            AppSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
