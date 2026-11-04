<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payroll;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $period = (string) $request->query('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) $period = now()->format('Y-m');
        $payrolls = Payroll::with('employee')->where('period', $period)->where('status', 'approved')->orderBy('employee_id')->get();
        $totals = [
            'net' => $payrolls->sum('net_total'),
            'income' => $payrolls->sum(fn ($p) => $p->calculation_snapshot['income_total']),
            'employer' => $payrolls->sum(fn ($p) => $p->calculation_snapshot['employer_total']),
            'employee_deductions' => $payrolls->sum(fn ($p) => $p->calculation_snapshot['employee_deduction_total']),
        ];
        return view('reports.index', compact('period','payrolls','totals'));
    }

    public function csv(Request $request)
    {
        $period = (string) $request->query('period', now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period), 422);
        $payrolls = Payroll::with('employee')->where('period', $period)->where('status', 'approved')->orderBy('employee_id')->get();
        return response()->streamDownload(function () use ($payrolls) {
            $handle = fopen('php://output','wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Periode','Kode','Nama','Jabatan','Penghasilan Tunai','Iuran Klinik','Potongan Pegawai','Gaji Bersih','Status'], ';');
            foreach ($payrolls as $p) {
                $c=$p->calculation_snapshot;
                $safe = fn ($s) => preg_match('/^[=+@\-\t\r]/u', (string)$s) ? "'".(string)$s : (string)$s;
                fputcsv($handle, [$p->period, $safe($p->employee->employee_code), $safe($c['employee']['name']), $safe($c['employee']['position'] ?? ''), $c['income_total'], $c['employer_total'], $c['employee_deduction_total'], $p->net_total, 'Disetujui'], ';');
            }
            fclose($handle);
        }, 'laporan-gaji-'.$period.'.csv', ['Content-Type'=>'text/csv; charset=UTF-8', 'Cache-Control'=>'private, no-store']);
    }

    public function audit()
    {
        $logs = AuditLog::orderByDesc('created_at')->paginate(30);
        return view('reports.audit', compact('logs'));
    }
}
