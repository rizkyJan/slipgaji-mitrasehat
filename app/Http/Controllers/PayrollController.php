<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\PayrollCalculator;
use App\Services\PayslipPdf;
use App\Services\SlipVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $period = (string) $request->query('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) $period = now()->format('Y-m');

        $search = trim((string) $request->query('search', ''));
        $branch = trim((string) $request->query('branch', ''));
        $status = (string) $request->query('status', '');
        if (!in_array($status, ['', 'draft', 'approved'], true)) $status = '';

        // Unit diambil otomatis dari data karyawan (termasuk karyawan yang diarsipkan),
        // sehingga cabang lama tetap bisa digunakan untuk mencari riwayat slip.
        $branches = Employee::withTrashed()
            ->whereNotNull('branch')->where('branch', '<>', '')
            ->distinct()->orderBy('branch')->pluck('branch');

        $query = Payroll::query()->where('period', $period)
            ->when($search !== '', fn ($q) => $q->whereHas('employee', fn ($e) =>
                $e->where(function ($nameQuery) use ($search) {
                    $nameQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('employee_code', 'like', '%'.$search.'%');
                })
            ))
            ->when($branch !== '', fn ($q) => $q->whereHas('employee', fn ($e) =>
                $e->where('branch', $branch)
            ))
            ->when($status !== '', fn ($q) => $q->where('status', $status));

        // Ringkasan seluruh hasil filter, bukan hanya baris pada halaman pagination.
        $stats = (clone $query)->toBase()->selectRaw(
            'COUNT(*) AS total_slips, '
            .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS draft_count, '
            .'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) AS approved_count, '
            .'COALESCE(SUM(net_total), 0) AS net_total, '
            .'COALESCE(SUM(CASE WHEN status = ? THEN net_total ELSE 0 END), 0) AS draft_net, '
            .'COALESCE(SUM(CASE WHEN status = ? THEN net_total ELSE 0 END), 0) AS approved_net',
            ['draft', 'approved', 'draft', 'approved']
        )->first();

        $payrolls = (clone $query)->with('employee')
            ->latest()->paginate(20)->withQueryString();

        return view('payrolls.index', compact(
            'period', 'search', 'branch', 'status', 'branches', 'stats', 'payrolls'
        ));
    }

    private function initialInput(Employee $employee): array
    {
        return [
            'base_salary' => $employee->base_salary,
            'fixed_allowance' => $employee->fixed_allowance,
            'meal_allowance' => $employee->meal_allowance,
            'reported_tk_wage' => $employee->reported_tk_wage,
            'reported_health_wage' => $employee->reported_health_wage,
            'family_extra' => $employee->family_extra,
            'pph21_override' => null,
            'payment_method' => $employee->payment_method,
            'extras' => [],
        ];
    }

    public function create(Request $request)
    {
        $employees = Employee::where('active', true)->orderBy('name')->get();
        $employee = $request->query('employee_id') ? Employee::where('active', true)->findOrFail($request->query('employee_id')) : null;
        $period = (string) $request->query('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) $period = now()->format('Y-m');
        $last = null;
        $input = $employee ? $this->initialInput($employee) : [];
        if ($employee) {
            $exists = Payroll::where('employee_id', $employee->id)->where('period', $period)->first();
            if ($exists) return redirect()->route('payrolls.show', $exists)->with('success', 'Payroll karyawan untuk periode ini sudah ada.');
            $last = Payroll::where('employee_id', $employee->id)->where('period', '<', $period)->orderByDesc('period')->first();
            if ($last && in_array($request->query('copy'), ['1', 'recurring'], true)) {
                $input = $last->input_snapshot;
                $input['pph21_override'] = null; // PPh 21 dihitung per masa; koreksi lama tidak ikut disalin.
                if ($request->query('copy') === 'recurring') {
                    $input['extras'] = array_values(array_filter($input['extras'] ?? [], fn ($item) => !empty($item['recurring'])));
                }
            }
        }
        return view('payrolls.form', compact('employees', 'employee', 'period', 'input', 'last'));
    }

    public function edit(Payroll $payroll)
    {
        abort_unless($payroll->status === 'draft', 403, 'Slip telah diterbitkan dan terkunci.');
        return view('payrolls.form', [
            'payroll' => $payroll, 'employee' => $payroll->employee,
            'employees' => Employee::where('active', true)->orderBy('name')->get(),
            'period' => $payroll->period, 'input' => $payroll->input_snapshot,
            'last' => null,
        ]);
    }

    private function fields(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required','integer','exists:employees,id'],
            'period' => ['required','date_format:Y-m'],
            'base_salary' => ['required','integer','min:0','max:999999999999'],
            'fixed_allowance' => ['required','integer','min:0','max:999999999999'],
            'meal_allowance' => ['required','integer','min:0','max:999999999999'],
            'reported_tk_wage' => ['nullable','integer','min:0','max:999999999999'],
            'reported_health_wage' => ['nullable','integer','min:0','max:999999999999'],
            'family_extra' => ['required','integer','between:0,15'],
            'pph21_override' => ['nullable','integer','min:0','max:999999999999'],
            'payment_method' => ['required',Rule::in(['transfer','tunai'])],
            'extras' => ['nullable','array','max:30'],
            'extras.*.label' => ['required','string','max:90'],
            'extras.*.type' => ['required',Rule::in(['income','deduction'])],
            'extras.*.amount' => ['required','integer','min:0','max:999999999999'],
            'extras.*.recurring' => ['sometimes','boolean'],
            'notes' => ['nullable','string','max:2000'],
            'paid_at' => ['nullable','date'],
        ]);
    }

    public function preview(Request $request, PayrollCalculator $calculator)
    {
        $data = $this->fields($request);
        $employee = Employee::findOrFail($data['employee_id']);
        $result = $calculator->calculate($employee, $data['period'], $data);
        return response()->json($result);
    }

    public function store(Request $request, PayrollCalculator $calculator)
    {
        $data = $this->fields($request);
        $employee = Employee::where('active', true)->findOrFail($data['employee_id']);
        $snapshot = $calculator->calculate($employee, $data['period'], $data);
        $exists = Payroll::where('employee_id', $employee->id)->where('period', $data['period'])->first();
        if ($exists) return redirect()->route('payrolls.show', $exists)->withErrors(['period' => 'Slip karyawan untuk periode ini sudah ada.']);
        $last = Payroll::where('employee_id', $employee->id)->where('period','<',$data['period'])->orderByDesc('period')->first();
        $payroll = DB::transaction(function () use ($employee, $data, $snapshot, $last) {
            return Payroll::create([
                'employee_id' => $employee->id, 'period' => $data['period'],
                'input_snapshot' => $snapshot['input'], 'calculation_snapshot' => $snapshot,
                'gross_total' => $snapshot['gross_total'], 'deduction_total' => $snapshot['deduction_total'],
                'net_total' => $snapshot['net_total'], 'status' => 'draft',
                'copied_from_id' => $last?->id, 'created_by' => auth()->id(),
                'paid_at' => $data['paid_at'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
        });
        AuditLog::record('payroll.created', $payroll, ['period' => $payroll->period, 'net' => $payroll->net_total]);
        return redirect()->route('payrolls.show', $payroll)->with('success', 'Draft slip gaji berhasil dibuat. Periksa kembali sebelum disetujui.');
    }

    public function update(Request $request, Payroll $payroll, PayrollCalculator $calculator)
    {
        abort_unless($payroll->status === 'draft', 403);
        $data = $this->fields($request);
        abort_unless((int)$data['employee_id'] === (int)$payroll->employee_id && $data['period'] === $payroll->period, 422);
        $snapshot = $calculator->calculate($payroll->employee, $payroll->period, $data);
        $payroll->update([
            'input_snapshot' => $snapshot['input'], 'calculation_snapshot' => $snapshot,
            'gross_total' => $snapshot['gross_total'], 'deduction_total' => $snapshot['deduction_total'],
            'net_total' => $snapshot['net_total'], 'paid_at' => $data['paid_at'] ?? null, 'notes' => $data['notes'] ?? null,
        ]);
        AuditLog::record('payroll.updated', $payroll, ['period' => $payroll->period, 'net' => $payroll->net_total]);
        return redirect()->route('payrolls.show', $payroll)->with('success', 'Draft berhasil diperbarui.');
    }

    public function show(Payroll $payroll, SlipVerification $verifier)
    {
        if ($payroll->status === 'approved') $payroll = $verifier->issue($payroll);
        $payroll->load(['employee','approver','deliveries']);
        return view('payrolls.show', compact('payroll'));
    }

    public function approve(Payroll $payroll, SlipVerification $verifier)
    {
        abort_unless($payroll->status === 'draft', 403);
        $payroll->update(['status' => 'approved','approved_by' => auth()->id(), 'approved_at' => now()]);
        $payroll = $verifier->issue($payroll);
        AuditLog::record('payroll.approved', $payroll, ['period' => $payroll->period, 'net' => $payroll->net_total]);
        return redirect()->route('payrolls.show', $payroll)->with('success', 'Slip sudah disetujui dan terkunci. PDF sekarang bisa dikirim via email.');
    }

    public function destroy(Payroll $payroll)
    {
        abort_unless($payroll->status === 'draft', 403, 'Hanya draft yang boleh dihapus.');
        AuditLog::record('payroll.deleted', $payroll, ['period' => $payroll->period]);
        $payroll->delete();
        return redirect()->route('payrolls.index')->with('success', 'Draft slip telah dihapus.');
    }

    public function pdf(Payroll $payroll, PayslipPdf $pdf)
    {
        $binary = $pdf->render($payroll->load(['employee','approver']));
        $name = 'slip-gaji-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', $payroll->employee->employee_code).'-'.$payroll->period.'.pdf';
        return response($binary, 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
