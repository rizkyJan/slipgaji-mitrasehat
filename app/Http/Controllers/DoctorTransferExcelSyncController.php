<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DoctorImportBatch;
use App\Models\DoctorPayroll;
use App\Services\DoctorIndirectFeeArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** R4.28: copy verified date/session headcounts ONLY into the appendix table. */
final class DoctorTransferExcelSyncController extends Controller
{
    public function preview(Request $request, DoctorPayroll $doctorPayroll, DoctorIndirectFeeArchiveService $service)
    {
        abort_unless(auth()->user()?->can('manage-settings'), 403);
        abort_unless(Schema::hasTable('doctor_transfer_breakdowns'), 503, 'Tabel lampiran R4.27 belum tersedia.');
        $doctorPayroll->load('assignment.employee');
        $choices = $service->candidates($doctorPayroll);
        $selectedBatch = null;
        $selectedDoctor = null;
        $result = null;
        // Read the original source marker for an imported slip, if available.
        if (!$request->filled('batch_id') && !$request->filled('doctor')) {
            $note = (string) ($doctorPayroll->input_snapshot['notes'] ?? $doctorPayroll->notes ?? '');
            if (preg_match('/arsip import ID\s+(\d+)\b/iu', $note, $source)
                && preg_match('/\|\s*([^|]+?)\s*\|\s*periode gaji\s+\d{4}-\d{2}/iu', $note, $name)) {
                $batchId = (int)$source[1];
                $nameInArchive = trim($name[1]);
                foreach ($choices as $choice) {
                    if ($choice['batch_id'] === $batchId && $choice['doctor'] === $nameInArchive) {
                        $request->merge(['batch_id'=>$batchId,'doctor'=>$nameInArchive]);
                        break;
                    }
                }
            }
        }
        if ($request->filled('batch_id') || $request->filled('doctor')) {
            $query = $request->validate(['batch_id'=>['required','integer','min:1'], 'doctor'=>['required','string','max:190']]);
            $selectedBatch = DoctorImportBatch::findOrFail((int)$query['batch_id']);
            $selectedDoctor = trim($query['doctor']);
            abort_unless(collect($choices)->contains(fn ($c) => $c['batch_id'] === (int)$selectedBatch->id && $c['doctor'] === $selectedDoctor), 404);
            $result = $service->preview($doctorPayroll, $selectedBatch, $selectedDoctor);
        }
        $existingCount = DB::table('doctor_transfer_breakdowns')->where('doctor_payroll_id', $doctorPayroll->id)->count();
        return view('doctors.transfer_excel_preview', compact('doctorPayroll','choices','selectedBatch','selectedDoctor','result','existingCount'));
    }

    public function apply(Request $request, DoctorPayroll $doctorPayroll, DoctorIndirectFeeArchiveService $service)
    {
        abort_unless(auth()->user()?->can('manage-settings'), 403);
        $data = $request->validate([
            'batch_id'=>['required','integer','min:1'],
            'doctor'=>['required','string','max:190'],
            'fingerprint'=>['required','string','regex:/^[a-f0-9]{64}$/'],
            'confirm_mapping'=>['accepted'],
            'confirm_counts'=>['accepted'],
        ]);
        $batch = DoctorImportBatch::findOrFail((int)$data['batch_id']);
        $doctor = trim($data['doctor']);
        $preview = $service->preview($doctorPayroll, $batch, $doctor);
        if ($preview['errors'] || !$preview['rows'] || !hash_equals($preview['fingerprint'], $data['fingerprint'])) {
            throw ValidationException::withMessages(['archive'=>'Rincian Excel belum valid atau berubah. Periksa pratinjau lagi.']);
        }
        DB::transaction(function () use ($doctorPayroll, $preview, $batch, $doctor) {
            $locked = DoctorPayroll::query()->whereKey($doctorPayroll->id)->lockForUpdate()->firstOrFail();
            if (DB::table('doctor_transfer_breakdowns')->where('doctor_payroll_id',$locked->id)->exists()) {
                throw ValidationException::withMessages(['archive'=>'Rincian sudah ada. Impor otomatis tidak akan menimpa data yang tersimpan.']);
            }
            $now = now();
            $records = [];
            foreach ($preview['rows'] as $row) {
                $records[] = [
                    'doctor_payroll_id'=>$locked->id,
                    'work_date'=>$row['date'],
                    'session'=>$row['session'],
                    'bpjs_count'=>$row['bpjs'],
                    'umum_count'=>$row['umum'],
                    'updated_by'=>auth()->id(),
                    'created_at'=>$now,
                    'updated_at'=>$now,
                ];
            }
            DB::table('doctor_transfer_breakdowns')->insert($records);
            AuditLog::record('doctor.transfer_breakdown.excel_imported', $locked, [
                'source_batch_id'=>(int)$batch->id, 'source_doctor'=>$doctor,
                'rows'=>count($records), 'note'=>'Hanya lampiran. Nominal honor dan snapshot tidak diubah.',
            ]);
        });
        return redirect()->route('doctors.show',$doctorPayroll)
            ->with('success','Rincian Fee Tidak Langsung disalin dari arsip Excel. Honor, PPh 21, cash, dan transfer tidak berubah.');
    }
}
