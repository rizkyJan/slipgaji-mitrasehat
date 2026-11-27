<?php

declare(strict_types=1);
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DoctorAssignment;
use App\Models\DoctorPayroll;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\PayrollWorkPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** R4.29: editable date LABELS; never mutates financial snapshots or attendance. */
final class WorkPeriodController extends Controller
{
    private function ready(): void
    {
        abort_unless(Schema::hasTable(PayrollWorkPeriod::TABLE), 503, 'Tabel rentang periode belum dimigrasikan.');
    }

    /** @return array{0:string,1:string} */
    private function dates(Request $request): array
    {
        $data = $request->validate([
            'work_from' => ['required', 'date_format:Y-m-d'],
            'work_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:work_from'],
        ]);
        $from = Carbon::createFromFormat('!Y-m-d', $data['work_from']);
        $to = Carbon::createFromFormat('!Y-m-d', $data['work_to']);
        if (!$from || !$to || $from->format('Y-m-d') !== $data['work_from'] || $to->format('Y-m-d') !== $data['work_to']) {
            throw ValidationException::withMessages(['work_from' => 'Tanggal periode kerja tidak valid.']);
        }
        if ($from->diffInDays($to) > 92) {
            throw ValidationException::withMessages(['work_to' => 'Rentang kerja maksimal 93 hari.']);
        }
        return [$data['work_from'], $data['work_to']];
    }

    public function index(Request $request)
    {
        $this->ready();
        $period = (string) $request->query('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) $period = now()->format('Y-m');
        $doctors = DoctorAssignment::with('employee')->orderBy('branch')->get();
        $employees = Employee::withTrashed()->orderBy('name')->get();
        $overrides = DB::table(PayrollWorkPeriod::TABLE)->where('period', $period)->get()
            ->keyBy(fn($v) => $v->person_kind.':'.$v->person_id);
        [$from, $to] = PayrollWorkPeriod::defaults($period);
        return view('work_periods.index', compact('period', 'doctors', 'employees', 'overrides', 'from', 'to'));
    }

    private function save(string $kind, int $personId, string $period, string $from, string $to, ?object $entity): void
    {
        $before = DB::table(PayrollWorkPeriod::TABLE)->where('person_kind', $kind)
            ->where('person_id', $personId)->where('period', $period)->first();
        DB::table(PayrollWorkPeriod::TABLE)->updateOrInsert(
            ['person_kind' => $kind, 'person_id' => $personId, 'period' => $period],
            ['work_from' => $from, 'work_to' => $to, 'updated_by' => auth()->id(),
             'updated_at' => now(), 'created_at' => $before?->created_at ?? now()]
        );
        AuditLog::record('work_period.updated', $entity, [
            'kind' => $kind, 'person_id' => $personId, 'period' => $period,
            'old_from' => $before?->work_from, 'old_to' => $before?->work_to,
            'new_from' => $from, 'new_to' => $to,
            'scope' => 'informasi_pdf_tanpa_perubahan_nominal',
        ]);
    }

    public function bulk(Request $request)
    {
        $this->ready();
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'targets' => ['required', 'array', 'min:1', 'max:500'],
            'targets.*' => ['required', 'string', 'regex:/^(doctor|employee):[1-9][0-9]*$/'],
        ]);
        [$from, $to] = $this->dates($request);
        $targets = array_unique($data['targets']);
        $valid = [];
        foreach ($targets as $target) {
            [$kind, $id] = explode(':', $target, 2);
            $id = (int) $id;
            $entity = $kind === 'doctor' ? DoctorAssignment::find($id) : Employee::withTrashed()->find($id);
            if (!$entity) throw ValidationException::withMessages(['targets' => 'Ada dokter/karyawan yang tidak ditemukan.']);
            $valid[] = [$kind, $id, $entity];
        }
        DB::transaction(function () use ($valid, $data, $from, $to) {
            foreach ($valid as [$kind, $id, $entity]) $this->save($kind, $id, $data['period'], $from, $to, $entity);
        });
        return redirect()->route('work-periods.index', ['period' => $data['period']])
            ->with('success', count($valid).' dokter/karyawan berhasil diatur. Nominal gaji/honor tidak diubah.');
    }

    public function doctor(Request $request, DoctorPayroll $doctorPayroll)
    {
        $this->ready();
        [$from, $to] = $this->dates($request);
        DB::transaction(fn() => $this->save('doctor', (int) $doctorPayroll->doctor_assignment_id,
            (string) $doctorPayroll->period, $from, $to, $doctorPayroll));
        return redirect()->route('doctors.show', $doctorPayroll)
            ->with('success', 'Periode kerja dokter diperbarui hanya untuk tampilan slip.');
    }

    public function employee(Request $request, Payroll $payroll)
    {
        $this->ready();
        [$from, $to] = $this->dates($request);
        DB::transaction(fn() => $this->save('employee', (int) $payroll->employee_id,
            (string) $payroll->period, $from, $to, $payroll));
        return redirect()->route('payrolls.show', $payroll)
            ->with('success', 'Periode kerja karyawan diperbarui hanya untuk tampilan slip.');
    }
}
