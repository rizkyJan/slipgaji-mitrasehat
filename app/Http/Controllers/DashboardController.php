<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayslipDelivery;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $period = $request->query('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)$period)) $period = now()->format('Y-m');
        $payrolls = Payroll::query()->with('employee')->where('period', $period)->latest()->get();
        $stats = [
            'employees' => Employee::query()->where('active', true)->count(),
            'processed' => $payrolls->count(),
            'approved' => $payrolls->where('status', 'approved')->count(),
            'total' => $payrolls->where('status', 'approved')->sum('net_total'),
            'sent' => PayslipDelivery::query()->whereIn('payroll_id', $payrolls->pluck('id'))->where('status', 'sent')->distinct('payroll_id')->count('payroll_id'),
        ];
        return view('dashboard', compact('period', 'payrolls', 'stats'));
    }
}
