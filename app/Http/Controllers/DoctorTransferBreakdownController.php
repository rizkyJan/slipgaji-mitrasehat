<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DoctorPayroll;
use App\Services\DoctorPayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R4.27: data informasional tanggal praktik, tidak mengubah honor/snapshot. */
class DoctorTransferBreakdownController extends Controller
{
    public function save(Request $request, DoctorPayroll $doctorPayroll)
    {
        abort_unless(auth()->user()?->can('manage-settings'), 403);
        $data = $request->validate([
            'rows' => ['required', 'array', 'max:250'],
            'rows.*.id' => ['nullable', 'integer', 'min:1'],
            'rows.*.date' => ['nullable', 'date_format:Y-m-d'],
            'rows.*.session' => ['nullable', Rule::in(['PAGI', 'SIANG', 'MALAM'])],
            'rows.*.bpjs' => ['nullable', 'integer', 'between:0,100000'],
            'rows.*.umum' => ['nullable', 'integer', 'between:0,100000'],
            'rows.*.remove' => ['nullable', 'boolean'],
        ]);
        $rows = [];
        $unique = [];
        foreach ($data['rows'] as $index => $input) {
            $id = (int)($input['id'] ?? 0);
            $date = (string)($input['date'] ?? '');
            $session = (string)($input['session'] ?? '');
            $bpjs = (int)($input['bpjs'] ?? 0);
            $umum = (int)($input['umum'] ?? 0);
            $remove = (bool)($input['remove'] ?? false);
            if ($id === 0 && $date === '' && $bpjs === 0 && $umum === 0) continue;
            if (!$remove) {
                if ($date === '' || $session === '') {
                    throw ValidationException::withMessages(['rows' => 'Setiap baris harus memiliki tanggal dan sesi praktik.']);
                }
                if (!DoctorPayrollPeriod::includes((string)$doctorPayroll->period, $date)) {
                    throw ValidationException::withMessages(['rows' => 'Ada tanggal di luar periode kerja 26 sampai 25.']);
                }
                $key = $date.'|'.$session;
                if (isset($unique[$key])) {
                    throw ValidationException::withMessages(['rows' => 'Tanggal dan sesi yang sama dimasukkan lebih dari sekali.']);
                }
                $unique[$key] = true;
            }
            $rows[] = compact('id', 'date', 'session', 'bpjs', 'umum', 'remove');
        }
        DB::transaction(function () use ($doctorPayroll, $rows) {
            // Lock parent untuk mencegah dua penyimpanan mengubah lampiran bersamaan.
            $payroll = DoctorPayroll::query()->whereKey($doctorPayroll->id)->lockForUpdate()->firstOrFail();
            $table = DB::table('doctor_transfer_breakdowns');
            $existing = $table->where('doctor_payroll_id', $payroll->id)->lockForUpdate()->get()->keyBy('id');
            $seen = [];
            foreach ($rows as $row) {
                $id = $row['id'];
                if ($id > 0) {
                    if (!$existing->has($id) || isset($seen[$id])) {
                        throw ValidationException::withMessages(['rows' => 'ID baris tidak cocok dengan slip atau diduplikasi.']);
                    }
                    $seen[$id] = true;
                }
                if ($row['remove']) {
                    if ($id > 0) $table->where('doctor_payroll_id', $payroll->id)->where('id', $id)->delete();
                    continue;
                }
                $values = [
                    'work_date' => $row['date'], 'session' => $row['session'],
                    'bpjs_count' => $row['bpjs'], 'umum_count' => $row['umum'],
                    'updated_by' => auth()->id(), 'updated_at' => now(),
                ];
                if ($id > 0) {
                    $table->where('doctor_payroll_id', $payroll->id)->where('id', $id)->update($values);
                } else {
                    $table->insert($values + ['doctor_payroll_id' => $payroll->id, 'created_at' => now()]);
                }
            }
            if ($rows) {
                AuditLog::record('doctor.transfer_breakdown.saved', $payroll, [
                    'rows_submitted' => count($rows),
                    'note' => 'Rincian informasional. Nominal dan tanda tangan slip tidak diubah.',
                ]);
            }
        });
        return redirect()->route('doctors.show', $doctorPayroll)
            ->with('success', 'Fee Tidak Langsung tersimpan. Nominal cash dan transfer tetap.');
    }
}
