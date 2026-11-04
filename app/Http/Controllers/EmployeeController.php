<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $employees = Employee::query()
            ->when($search, fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$search.'%')->orWhere('employee_code', 'like', '%'.$search.'%')->orWhere('position', 'like', '%'.$search.'%')->orWhere('branch', 'like', '%'.$search.'%')))
            ->orderBy('name')->paginate(15)->withQueryString();
        return view('employees.index', compact('employees', 'search'));
    }

    private function branches()
    {
        return Employee::query()->whereNotNull('branch')->where('branch', '<>', '')->distinct()->orderBy('branch')->pluck('branch');
    }

    public function create() { return view('employees.form', ['employee' => new Employee(), 'branches' => $this->branches()]); }
    public function edit(Employee $employee) { return view('employees.form', ['employee' => $employee, 'branches' => $this->branches()]); }

    private function fields(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'employee_code' => ['required','string','max:30', Rule::unique('employees', 'employee_code')->ignore($employee?->id)],
            'name' => ['required','string','max:150'], 'position' => ['nullable','string','max:120'],
            'branch' => ['nullable','string','max:120'],
            'pph21_ptkp' => ['required', Rule::in(['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3'])],
            'pph21_mode' => ['required', Rule::in(['auto','manual','off'])],
            'email' => ['nullable','email','max:255'], 'employment_status' => ['required',Rule::in(['tetap','kontrak','paruh_waktu','harian'])],
            'joined_at' => ['nullable','date'], 'payment_method' => ['required',Rule::in(['transfer','tunai'])],
            'bank_name' => ['nullable','string','max:70'], 'bank_account' => ['nullable','string','max:70'],
            'base_salary' => ['required','integer','min:0','max:999999999999'],
            'fixed_allowance' => ['required','integer','min:0','max:999999999999'],
            'meal_allowance' => ['required','integer','min:0','max:999999999999'],
            'reported_tk_wage' => ['nullable','integer','min:0','max:999999999999'],
            'reported_health_wage' => ['nullable','integer','min:0','max:999999999999'],
            'family_extra' => ['required','integer','between:0,15'], 'notes' => ['nullable','string','max:1000'],
        ]);
        foreach (['active','meal_is_fixed','bpjs_health','bpjs_jht','bpjs_jkk','bpjs_jkm','bpjs_jp'] as $field) {
            $data[$field] = $request->boolean($field);
        }
        return $data;
    }

    public function store(Request $request)
    {
        $employee = Employee::create($this->fields($request));
        AuditLog::record('employee.created', $employee, ['name' => $employee->name]);
        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->fields($request, $employee));
        AuditLog::record('employee.updated', $employee, ['name' => $employee->name]);
        return redirect()->route('employees.index')->with('success', 'Data karyawan diperbarui. Slip yang sudah disetujui tetap menggunakan snapshot lamanya.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        AuditLog::record('employee.archived', $employee, ['name' => $employee->name]);
        return redirect()->route('employees.index')->with('success', 'Karyawan diarsipkan. Riwayat payroll tetap tersimpan.');
    }
}
