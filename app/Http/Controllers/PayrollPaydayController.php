<?php
namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Services\PayrollPayday;
use Illuminate\Http\Request;

class PayrollPaydayController extends Controller
{
    public function index(Request $request)
    {
        $period = (string) $request->query('period', now()->format('Y-m'));
        if (!PayrollPayday::validPeriod($period)) $period = now()->format('Y-m');
        $payDate = PayrollPayday::forPeriod($period);
        $workDates = PayrollPayday::workDates($period);
        $saved = AppSetting::query()
            ->where('key','like',PayrollPayday::KEY_PREFIX.'%')
            ->orderByDesc('key')->limit(24)->get();
        return view('payrolls.payday_settings', compact('period','payDate','workDates','saved'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'period' => ['required','date_format:Y-m'],
            'pay_date' => ['required','date_format:Y-m-d'],
        ]);
        if (!PayrollPayday::validPeriod($data['period']) || !PayrollPayday::validDate($data['pay_date'])) {
            return back()->withErrors(['pay_date'=>'Tanggal atau periode tidak valid.'])->withInput();
        }
        AppSetting::updateOrCreate(
            ['key'=>PayrollPayday::KEY_PREFIX.$data['period']],
            ['value'=>$data['pay_date']]
        );
        AuditLog::record('payroll.payday.updated', null, ['period'=>$data['period'],'date'=>$data['pay_date']]);
        return redirect()->route('payday.index',['period'=>$data['period']])
            ->with('success','Jadwal gajian bersama disimpan untuk periode '.$data['period'].'.');
    }
}
