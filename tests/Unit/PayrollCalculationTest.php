<?php

use App\Models\Employee;
use App\Models\MinimumWage;
use App\Models\PayrollRule;
use App\Services\PayrollCalculator;
use App\Services\Terbilang;

function sampleRule(): PayrollRule
{
    return (new PayrollRule())->forceFill([
        'health_employee_pct' => 1, 'health_employer_pct' => 4,
        'health_cap' => 12000000, 'jht_employee_pct' => 2, 'jht_employer_pct' => 3.7,
        'jkk_employer_pct' => 0.54, 'jkm_employer_pct' => 0.3,
        'jp_employee_pct' => 1, 'jp_employer_pct' => 2,
        'jp_cap' => null, 'tk_use_umk_floor' => false,
    ]);
}

function sampleEmployee(int $salary): Employee
{
    return (new Employee())->forceFill([
        'employee_code' => 'TEST-01', 'name' => 'Karyawan Contoh', 'base_salary' => $salary,
        'fixed_allowance' => 0, 'meal_allowance' => 300000, 'meal_is_fixed' => false,
        'bpjs_health' => true, 'bpjs_jht' => true, 'bpjs_jkk' => true, 'bpjs_jkm' => true,
        'bpjs_jp' => false, 'family_extra' => 0, 'payment_method' => 'transfer',
    ]);
}

test('slip referensi pertama menghasilkan dua juta lima puluh tiga ribu', function () {
    $output = (new PayrollCalculator())->calculateUsingRules(
        sampleEmployee(1870975), '2025-01',
        ['base_salary' => 1870975,'fixed_allowance'=>0,'meal_allowance'=>300000,'reported_tk_wage'=>2359500,'family_extra'=>2,'payment_method'=>'transfer'],
        (new MinimumWage())->forceFill(['year'=>2025,'region'=>'Sukoharjo','amount'=>2359500]),
        sampleRule()
    );
    expect($output['employer_contributions']['BPJS Kesehatan Perusahaan'])->toBe(94380)
        ->and(array_sum(array_diff_key($output['employer_contributions'],['BPJS Kesehatan Perusahaan'=>1])))->toBe(107121)
        ->and($output['gross_total'])->toBe(2372476)
        ->and($output['deduction_total'])->toBe(319476)
        ->and($output['net_total'])->toBe(2053000);
});

test('slip referensi kedua menghasilkan dua juta delapan ratus lima puluh ribu', function () {
    $output = (new PayrollCalculator())->calculateUsingRules(
        sampleEmployee(2628870), '2025-01',
        ['base_salary'=>2628870,'fixed_allowance'=>0,'meal_allowance'=>300000,'reported_tk_wage'=>2629000,'reported_health_wage'=>2629000,'family_extra'=>0,'payment_method'=>'transfer'],
        (new MinimumWage())->forceFill(['year'=>2025,'region'=>'Sukoharjo','amount'=>2359500]),
        sampleRule()
    );
    expect($output['gross_total'])->toBe(3153387)
        ->and($output['deduction_total'])->toBe(303387)
        ->and($output['net_total'])->toBe(2850000);
});

test('terbilang rupiah benar', function () {
    expect(Terbilang::rupiah(2053000))->toBe('Dua Juta Lima Puluh Tiga Ribu Rupiah');
});
