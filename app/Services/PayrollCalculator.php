<?php
namespace App\Services;

use App\Models\Employee;
use App\Models\MinimumWage;
use App\Models\PayrollRule;
use Illuminate\Validation\ValidationException;

class PayrollCalculator
{
    public function calculate(Employee $employee, string $period, array $input): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => 'Periode harus YYYY-MM.']);
        }
        $wage = MinimumWage::query()->where('year', (int) substr($period, 0, 4))->first();
        if (!$wage) {
            throw ValidationException::withMessages(['period' => 'UMK tahun '.substr($period, 0, 4).' belum diatur. Buka Pengaturan → UMK terlebih dahulu.']);
        }
        $rule = PayrollRule::query()->whereDate('effective_from', '<=', $period.'-01')->orderByDesc('effective_from')->first();
        if (!$rule) {
            throw ValidationException::withMessages(['period' => 'Aturan BPJS belum tersedia untuk periode ini.']);
        }

        return $this->calculateUsingRules($employee, $period, $input, $wage, $rule);
    }

    /** Memungkinkan unit test rumus tanpa query database. */
    public function calculateUsingRules(Employee $employee, string $period, array $input, MinimumWage $wage, PayrollRule $rule): array
    {
        $salary = (int) ($input['base_salary'] ?? $employee->base_salary);
        $fixed = (int) ($input['fixed_allowance'] ?? $employee->fixed_allowance);
        $meal = (int) ($input['meal_allowance'] ?? $employee->meal_allowance);
        $family = (int) ($input['family_extra'] ?? $employee->family_extra);
        $paymentMethod = $input['payment_method'] ?? $employee->payment_method;
        $extras = [];
        foreach (($input['extras'] ?? []) as $extra) {
            if (trim((string) ($extra['label'] ?? '')) === '') continue;
            if (($extra['type'] ?? '') === 'deduction' && preg_match('/^pph\s*21$/i', trim((string) $extra['label']))) {
                throw ValidationException::withMessages(['extras' => 'PPh 21 sudah tersedia pada kolom khusus PPh 21. Jangan tambahkan ulang di potongan lain agar tidak terhitung dua kali.']);
            }
            $extras[] = [
                'label' => trim((string) $extra['label']),
                'type' => $extra['type'],
                'amount' => (int) $extra['amount'],
                'recurring' => (bool) ($extra['recurring'] ?? false),
            ];
        }
        $grossFixed = $salary + $fixed + ($employee->meal_is_fixed ? $meal : 0);
        $hasTkOverride = isset($input['reported_tk_wage']) && $input['reported_tk_wage'] !== '' && $input['reported_tk_wage'] !== null;
        $tkDeclared = $hasTkOverride ? (int) $input['reported_tk_wage'] : ($employee->reported_tk_wage ?? $grossFixed);
        $tkBasis = $rule->tk_use_umk_floor ? max($tkDeclared, (int) $wage->amount) : $tkDeclared;
        $hasHealthOverride = isset($input['reported_health_wage']) && $input['reported_health_wage'] !== '' && $input['reported_health_wage'] !== null;
        $healthReported = $hasHealthOverride ? (int) $input['reported_health_wage'] : $employee->reported_health_wage;
        $healthBasis = min((int) $rule->health_cap, max($grossFixed, (int) $wage->amount, (int) ($healthReported ?? 0)));
        if ($tkBasis <= 0 && ($employee->bpjs_jht || $employee->bpjs_jkk || $employee->bpjs_jkm || $employee->bpjs_jp)) {
            throw ValidationException::withMessages(['reported_tk_wage' => 'Dasar iuran BPJS Ketenagakerjaan harus lebih besar dari nol.']);
        }
        if ($employee->bpjs_jp && (!$rule->jp_cap || $rule->jp_cap <= 0)) {
            throw ValidationException::withMessages(['period' => 'Karyawan mengikuti JP, tetapi batas atas upah JP periode ini belum diverifikasi/diatur. Isi di Pengaturan BPJS.']);
        }
        $jpBasis = $employee->bpjs_jp ? min($tkBasis, (int) $rule->jp_cap) : 0;
        $pct = fn (int $basis, $rate): int => (int) round($basis * (float) $rate / 100, 0, PHP_ROUND_HALF_UP);

        $employer = [
            'BPJS Kesehatan Perusahaan' => $employee->bpjs_health ? $pct($healthBasis, $rule->health_employer_pct) : 0,
            'JHT Perusahaan' => $employee->bpjs_jht ? $pct($tkBasis, $rule->jht_employer_pct) : 0,
            'JKK Perusahaan' => $employee->bpjs_jkk ? $pct($tkBasis, $rule->jkk_employer_pct) : 0,
            'JKM Perusahaan' => $employee->bpjs_jkm ? $pct($tkBasis, $rule->jkm_employer_pct) : 0,
            'JP Perusahaan' => $employee->bpjs_jp ? $pct($jpBasis, $rule->jp_employer_pct) : 0,
        ];
        // Slip lama klinik membulatkan gabungan iuran TK perusahaan sekali,
        // bukan menjumlahkan hasil pembulatan JHT + JKK + JKM terpisah.
        // Rekonsiliasi selisih 1 rupiah ke program TK terakhir yang aktif.
        $tkEmployerPct = ($employee->bpjs_jht ? (float)$rule->jht_employer_pct : 0)
            + ($employee->bpjs_jkk ? (float)$rule->jkk_employer_pct : 0)
            + ($employee->bpjs_jkm ? (float)$rule->jkm_employer_pct : 0);
        $tkEmployerRounded = $pct($tkBasis, $tkEmployerPct);
        $tkSum = $employer['JHT Perusahaan'] + $employer['JKK Perusahaan'] + $employer['JKM Perusahaan'];
        foreach (['JKM Perusahaan', 'JKK Perusahaan', 'JHT Perusahaan'] as $key) {
            if ($employer[$key] > 0) { $employer[$key] += $tkEmployerRounded - $tkSum; break; }
        }
        $employeeDeductions = [
            'BPJS Kesehatan Pegawai' => $employee->bpjs_health ? $pct($healthBasis, $rule->health_employee_pct) : 0,
            'JHT Pegawai' => $employee->bpjs_jht ? $pct($tkBasis, $rule->jht_employee_pct) : 0,
            'JP Pegawai' => $employee->bpjs_jp ? $pct($jpBasis, $rule->jp_employee_pct) : 0,
            'BPJS Kesehatan Keluarga Tambahan' => $employee->bpjs_health ? $family * $pct($healthBasis, $rule->health_employee_pct) : 0,
        ];
        $income = ['Gaji Pokok' => $salary, 'Tunjangan Tetap' => $fixed, 'Uang Makan' => $meal];
        foreach ($extras as $index => $extra) {
            $key = $extra['label'].' (#'.($index + 1).')';
            if ($extra['type'] === 'income') {
                $income[$key] = $extra['amount'];
            } else {
                $employeeDeductions[$key] = $extra['amount'];
            }
        }
        $income = array_filter($income, fn ($amount) => $amount !== 0);
        $employer = array_filter($employer, fn ($amount) => $amount !== 0);
        $employeeDeductions = array_filter($employeeDeductions, fn ($amount) => $amount !== 0);
        // PPh 21 tidak memakai bruto slip klasik (yang mengandung JHT/JP perusahaan).
        // Dasar TER: penghasilan tunai + BPJS Kesehatan, JKK, JKM yang ditanggung pemberi kerja.
        // Lihat PMK 168/2023 Pasal 5 ayat (3).
        $taxableGross = (int) array_sum($income)
            + (int) ($employer['BPJS Kesehatan Perusahaan'] ?? 0)
            + (int) ($employer['JKK Perusahaan'] ?? 0)
            + (int) ($employer['JKM Perusahaan'] ?? 0);
        $taxMode = $employee->pph21_mode ?: 'auto';
        $ptkp = $employee->pph21_ptkp ?: 'TK/0';
        $taxResult = Pph21Ter::calculate($taxableGross, $ptkp);
        $hasTaxOverride = isset($input['pph21_override']) && $input['pph21_override'] !== '' && $input['pph21_override'] !== null;
        $taxAmount = 0;
        $taxMethod = 'off';
        if ($taxMode !== 'off') {
            if ($hasTaxOverride) {
                $taxAmount = (int) $input['pph21_override'];
                $taxMethod = 'manual';
            } elseif ($taxMode === 'manual' || substr($period, 5, 2) === '12') {
                throw ValidationException::withMessages(['pph21_override' => 'Isi nominal PPh 21 hasil perhitungan petugas (boleh 0). Untuk Desember/masa pajak terakhir wajib rekonsiliasi PPh setahun berdasarkan Pasal 17, bukan TER bulanan.']);
            } else {
                $taxAmount = $taxResult['amount'];
                $taxMethod = 'TER '.$taxResult['category'];
            }
        }
        // Selalu tampilkan PPh 21 di slip, termasuk saat nilainya Rp0.
        $employeeDeductions['PPh 21'] = $taxAmount;
        $incomeTotal = array_sum($income);
        $employerTotal = array_sum($employer);
        $employeeDeductionTotal = array_sum($employeeDeductions);
        $net = $incomeTotal - $employeeDeductionTotal;
        if ($net < 0) {
            throw ValidationException::withMessages(['extras' => 'Total potongan melebihi penghasilan. Periksa kembali nominal.']);
        }
        $warnings = [];
        if ($taxMode === 'off') $warnings[] = 'PPh 21 dinonaktifkan untuk pegawai ini; pastikan status pengecualian telah diverifikasi.';
        if ($taxMethod === 'manual') $warnings[] = 'PPh 21 diisi manual. Periksa nominal dan bukti perhitungannya sebelum menyetujui slip.';
        if ($taxMode === 'auto' && $taxMethod === 'TER A' && $taxResult['amount'] === 0) $warnings[] = 'PPh 21 = Rp0 berdasarkan TER kategori A (ambang bruto Rp5,4 juta); bukan otomatis dikenakan mulai Rp4,5 juta.';
        if ($grossFixed < (int) $wage->amount) {
            $warnings[] = 'Upah pokok + tunjangan tetap lebih rendah dari UMK '.substr($period, 0, 4).'. Periksa ketentuan pengupahan dan pengecualian yang berlaku.';
        }
        if ($healthReported !== null && (int)$healthReported !== $grossFixed) {
            $warnings[] = 'Dasar BPJS Kesehatan menggunakan nilai terlapor yang diverifikasi. Periksa kesesuaian dengan upah tetap dan UMK.';
        }
        if ($tkDeclared !== $grossFixed) {
            $warnings[] = 'Dasar BPJS TK berbeda dari upah pokok + tunjangan tetap. Pastikan sama dengan upah yang benar-benar dilaporkan ke BPJS TK.';
        }
        if ($rule->tk_use_umk_floor) {
            $warnings[] = 'Aturan dasar minimum UMK untuk BPJS TK aktif. Pastikan sesuai dengan pelaporan BPJS TK klinik.';
        }
        return [
            'period' => $period,
            'employee' => ['id' => $employee->id, 'code' => $employee->employee_code, 'name' => $employee->name, 'position' => $employee->position, 'email' => $employee->email, 'branch' => $employee->branch],
            'payment_method' => $paymentMethod,
            'input' => [
                'base_salary' => $salary, 'fixed_allowance' => $fixed, 'meal_allowance' => $meal,
                'reported_tk_wage' => $hasTkOverride ? $tkDeclared : null,
                'reported_health_wage' => $hasHealthOverride ? (int) $healthReported : null,
                'family_extra' => $family, 'payment_method' => $paymentMethod, 'extras' => $extras,
                'pph21_override' => $hasTaxOverride ? $taxAmount : null,
            ],
            'rates' => $rule->only(['effective_from','health_employee_pct','health_employer_pct','health_cap','jht_employee_pct','jht_employer_pct','jkk_employer_pct','jkm_employer_pct','jp_employee_pct','jp_employer_pct','jp_cap','tk_use_umk_floor']),
            'minimum_wage' => ['year' => $wage->year, 'amount' => (int) $wage->amount, 'region' => $wage->region, 'reference' => $wage->reference],
            'bases' => ['regular_wage' => $grossFixed, 'health_wage' => $healthBasis, 'reported_health_wage' => $healthReported, 'reported_tk_wage' => $tkDeclared, 'tk_wage' => $tkBasis, 'jp_wage' => $jpBasis],
            'income' => $income, 'employer_contributions' => $employer,
            'employee_deductions' => $employeeDeductions,
            'income_total' => $incomeTotal, 'employer_total' => $employerTotal,
            'employee_deduction_total' => $employeeDeductionTotal,
            'gross_total' => $incomeTotal + $employerTotal,
            'deduction_total' => $employerTotal + $employeeDeductionTotal,
            'net_total' => $net,
            'tax' => ['method' => $taxMethod, 'ptkp' => $ptkp, 'category' => $taxResult['category'], 'rate' => $taxResult['rate'], 'taxable_gross' => $taxableGross, 'amount' => $taxAmount],
            'warnings' => $warnings,
        ];
    }
}
