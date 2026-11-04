<?php
namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\MinimumWage;
use App\Models\PayrollRule;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $wages = MinimumWage::orderByDesc('year')->get();
        $rules = PayrollRule::orderByDesc('effective_from')->get();
        $connection = auth()->user()->googleConnection;
        return view('settings.index', compact('wages','rules','connection'));
    }

    public function clinic(Request $request)
    {
        $data = $request->validate([
            'clinic_name' => ['required','string','max:150'],
            'clinic_address' => ['nullable','string','max:200'],
            'slip_signer' => ['nullable','string','max:150'],
            'slip_style' => ['required','in:classic,modern'],
        ]);
        foreach ($data as $key => $value) AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        AuditLog::record('settings.clinic.updated');
        return back()->with('success', 'Identitas klinik dan format slip berhasil disimpan.');
    }

    public function wage(Request $request)
    {
        $data = $request->validate([
            'year' => ['required','integer','between:2020,2100'],
            'region' => ['required','string','max:120'],
            'amount' => ['required','integer','min:1','max:999999999999'],
            'reference' => ['nullable','string','max:250'],
        ]);
        $wage = MinimumWage::updateOrCreate(['year' => $data['year']], $data);
        AuditLog::record('settings.wage.updated', $wage, ['year' => $wage->year, 'amount' => $wage->amount]);
        return back()->with('success', 'UMK berhasil disimpan. Slip yang sudah terbit tidak berubah.');
    }

    public function rule(Request $request)
    {
        $rules = [
            'effective_from' => ['required','date'], 'health_cap' => ['required','integer','min:1','max:999999999999'],
            'jp_cap' => ['nullable','integer','min:1','max:999999999999'],
            'notes' => ['nullable','string','max:1000'],
        ];
        foreach (['health_employee_pct','health_employer_pct','jht_employee_pct','jht_employer_pct','jkk_employer_pct','jkm_employer_pct','jp_employee_pct','jp_employer_pct'] as $field) {
            $rules[$field] = ['required','numeric','between:0,25','decimal:0,4'];
        }
        $data = $request->validate($rules);
        $data['tk_use_umk_floor'] = $request->boolean('tk_use_umk_floor');
        $rule = PayrollRule::updateOrCreate(['effective_from' => $data['effective_from']], $data);
        AuditLog::record('settings.bpjs.updated', $rule, ['effective_from' => $data['effective_from']]);
        return back()->with('success', 'Aturan BPJS periode berlaku berhasil disimpan.');
    }
}
