<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DoctorAssignment;
use App\Models\DoctorImportBatch;
use App\Models\DoctorPayroll;
use App\Models\DoctorProcedure;
use App\Services\DoctorExcelReader;
use App\Services\DoctorPayrollPeriod;
use App\Services\DoctorPayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DoctorExcelImportController extends Controller
{
    public function index()
    {
        $batches = DoctorImportBatch::query()
            ->where('created_by', auth()->id())->latest()->limit(15)->get();
        return view('doctors.import_upload', compact('batches'));
    }

    public function upload(Request $request, DoctorExcelReader $reader)
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx']]);
        $upload = $request->file('file');
        try {
            $parsed = $reader->parse($upload->getRealPath());
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['file' => 'Gagal membaca Excel: '.$e->getMessage()]);
        }
        $batch = DoctorImportBatch::create([
            'created_by' => auth()->id(),
            'original_filename' => mb_substr(basename($upload->getClientOriginalName()), 0, 190),
            'file_sha256' => hash_file('sha256', $upload->getRealPath()),
            'row_count' => $parsed['total'],
            'rows' => $parsed['rows'],
            'summary' => ['sheets' => $parsed['sheets'], 'periods' => $parsed['periods']],
        ]);
        AuditLog::record('doctor.import.upload', $batch, ['rows' => $parsed['total']]);
        return redirect()->route('doctors.import.review', $batch)
            ->with('success', 'Excel terbaca. Data belum masuk payroll. Periksa dan pilih dokter sebelum membuat draft.');
    }

    private function owned(DoctorImportBatch $batch): void
    {
        abort_unless((int)$batch->created_by === (int)auth()->id() || auth()->user()->can('manage-settings'), 403);
    }

    /**
     * Recalculate period from actual dates, including batches uploaded with R4.6.
     * No database migration or reupload is needed; original Excel data is preserved.
     */
    private function payrollRows(DoctorImportBatch $batch): array
    {
        return array_map(static function (array $row): array {
            $row['period'] = DoctorPayrollPeriod::fromWorkDate((string)$row['date']);
            return $row;
        }, $batch->rows ?: []);
    }

    public function review(Request $request, DoctorImportBatch $batch)
    {
        $this->owned($batch);
        $rows = $this->payrollRows($batch);
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['period'].'|'.$row['doctor'];
            if (!isset($groups[$key])) {
                [$from, $to] = DoctorPayrollPeriod::bounds($row['period']);
                $groups[$key] = [
                    'period' => $row['period'], 'doctor' => $row['doctor'],
                    'range_label' => date('d/m', strtotime($from)).'–'.date('d/m', strtotime($to)),
                    'count' => 0, 'issues' => 0,
                ];
            }
            $groups[$key]['count']++;
            $groups[$key]['issues'] += count($row['issues'] ?? []) ? 1 : 0;
        }
        ksort($groups);
        $group = (string)$request->query('group', '');
        if ($group === '' || !isset($groups[$group])) $group = array_key_first($groups);
        abort_if($group === null, 404);
        $filtered = array_values(array_filter($rows, fn ($row) => $row['period'].'|'.$row['doctor'] === $group));
        $assignments = DoctorAssignment::with('employee')->where('active', true)->orderBy('branch')->get();
        $period = $groups[$group]['period'];
        [$periodFrom, $periodTo] = DoctorPayrollPeriod::bounds($period);
        // Workbook-wide coverage, NOT the selected doctor's individual practice days.
        // The presence of all dates is only a signal, not proof every shift is present.
        $periodRows = array_values(array_filter($rows, fn ($row) => $row['period'] === $period));
        $dateFrom = min(array_column($periodRows, 'date'));
        $dateTo = max(array_column($periodRows, 'date'));
        $datesSeen = array_fill_keys(array_column($periodRows, 'date'), true);
        $missingDays = [];
        for ($day = new \DateTimeImmutable($periodFrom); $day->format('Y-m-d') <= $periodTo; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            if (!isset($datesSeen[$key])) $missingDays[] = $key;
        }
        $incomplete = count($missingDays) > 0;
        $missingDaysLabel = implode(', ', array_map(static fn ($date) => date('d/m', strtotime($date)), array_slice($missingDays, 0, 7)));
        $sheetCounts = [];
        foreach ($filtered as $r) {
            $name = (string)$r['sheet'];
            $sheetCounts[$name] = ($sheetCounts[$name] ?? 0) + 1;
        }
        // Show a monthly recap first. Daily records remain editable only under a collapsible audit section.
        $monthly = [];
        $totals = ['shifts' => 0, 'bpjs' => 0, 'umum' => 0];
        foreach ($filtered as $r) {
            $kind = $r['shift_type'] ?? 'unmapped';
            if (!isset($monthly[$kind])) $monthly[$kind] = ['type' => $kind, 'shifts' => 0, 'bpjs' => 0, 'umum' => 0];
            $monthly[$kind]['shifts']++;
            $monthly[$kind]['bpjs'] += max(0, (int)$r['bpjs']);
            $monthly[$kind]['umum'] += max(0, (int)$r['umum']);
            $totals['shifts']++;
            $totals['bpjs'] += max(0, (int)$r['bpjs']);
            $totals['umum'] += max(0, (int)$r['umum']);
        }
        $issueCount = count(array_filter($filtered, static fn ($r) => !empty($r['issues'])));
        $procedures = DoctorProcedure::where('active', true)->orderBy('label')->get();
        return view('doctors.import_review', compact('batch', 'groups', 'group', 'filtered', 'assignments', 'period', 'periodFrom', 'periodTo', 'dateFrom', 'dateTo', 'missingDays', 'missingDaysLabel', 'incomplete', 'sheetCounts', 'monthly', 'totals', 'issueCount', 'procedures'));
    }

    public function generate(Request $request, DoctorImportBatch $batch, DoctorPayrollCalculator $calculator)
    {
        $this->owned($batch);
        $data = $request->validate([
            'group' => ['required', 'string', 'max:190'],
            'doctor_assignment_id' => ['required', 'integer', 'exists:doctor_assignments,id'],
            'confirm_mapping' => ['accepted'],
            'confirm_review' => ['accepted'],
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.include' => ['nullable', 'in:1'],
            'rows.*.date' => ['nullable', 'date_format:Y-m-d'],
            'rows.*.shift_type' => ['nullable', Rule::in(array_keys(config('doctor_payroll.shift_labels')))],
            'rows.*.start_time' => ['nullable', 'date_format:H:i'],
            'rows.*.end_time' => ['nullable', 'date_format:H:i'],
            'rows.*.bpjs_count' => ['nullable', 'integer', 'between:0,1000'],
            'rows.*.general_count' => ['nullable', 'integer', 'between:0,1000'],
            'rows.*.bpjs_excess' => ['nullable', 'integer', 'between:0,1000'],
            'rows.*.general_excess' => ['nullable', 'integer', 'between:0,1000'],
            'services' => ['nullable', 'array'],
            'services.usg' => ['nullable', 'integer', 'between:0,100000'],
            'services.homecare_clinic' => ['nullable', 'integer', 'between:0,100000'],
            'services.homecare_own' => ['nullable', 'integer', 'between:0,100000'],
            'actions' => ['nullable', 'array', 'max:80'],
            'actions.*.procedure_id' => ['required', 'integer', 'exists:doctor_procedures,id'],
            'actions.*.qty' => ['required', 'integer', 'between:1,1000'],
            'actions.*.fixed_fee' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'actions.*.percentage' => ['nullable', 'numeric', 'between:0,100'],
            'actions.*.price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'actions.*.material' => ['required', 'integer', 'min:0', 'max:999999999'],
            'actions.*.claim' => ['required', 'integer', 'min:0', 'max:999999999'],
            'actions.*.patient' => ['required', 'integer', 'min:0', 'max:999999999'],
        ]);
        $byId = [];
        foreach ($this->payrollRows($batch) as $row) $byId[(string)$row['id']] = $row;
        $assign = DoctorAssignment::with('employee')->where('active', true)->findOrFail($data['doctor_assignment_id']);
        $firstGroup = explode('|', $data['group'], 2);
        if (count($firstGroup) !== 2) throw ValidationException::withMessages(['group' => 'Kelompok dokter tidak valid.']);
        [$period, $doctorName] = $firstGroup;
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) throw ValidationException::withMessages(['group' => 'Periode tidak valid.']);
        $shifts = [];
        foreach ($data['rows'] as $id => $edited) {
            $original = $byId[(string)$id] ?? null;
            if (!$original || $original['period'] !== $period || $original['doctor'] !== $doctorName) {
                throw ValidationException::withMessages(['rows' => 'Ada baris import yang bukan milik dokter/periode ini.']);
            }
            if (($edited['include'] ?? '') !== '1') continue;
            if (empty($edited['date']) || empty($edited['shift_type']) || ($edited['bpjs_count'] ?? '') === '' || ($edited['general_count'] ?? '') === '') {
                throw ValidationException::withMessages(['rows.'.$id => 'Tanggal, jenis shift, BPJS, dan umum wajib untuk jadwal yang dipilih.']);
            }
            if (!DoctorPayrollPeriod::includes($period, $edited['date'])) {
                [$periodFrom, $periodTo] = DoctorPayrollPeriod::bounds($period);
                throw ValidationException::withMessages(['rows.'.$id.'.date' => 'Tanggal harus berada dalam periode gaji '.date('d/m/Y', strtotime($periodFrom)).' s.d. '.date('d/m/Y', strtotime($periodTo)).'.']);
            }
            $start = $edited['start_time'] ?? null;
            $end = $edited['end_time'] ?? null;
            if (($start === null || $start === '') !== ($end === null || $end === '')) {
                throw ValidationException::withMessages(['rows.'.$id.'.end_time' => 'Isi kedua jam atau kosongkan keduanya.']);
            }
            if ($start && $end) {
                [$h1, $m1] = array_map('intval', explode(':', $start));
                [$h2, $m2] = array_map('intval', explode(':', $end));
                $duration = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
                if ($duration <= 0) $duration += 1440;
                if ($duration > 16 * 60 || $duration < 15) {
                    throw ValidationException::withMessages(['rows.'.$id.'.end_time' => 'Durasi kerja tidak wajar. Koreksi jam.']);
                }
            }
            $bpjs = (int)$edited['bpjs_count'];
            $umum = (int)$edited['general_count'];
            $bpjsExcess = null;
            $generalExcess = null;
            if ($edited['shift_type'] === 'poli2' && (($assign->rules['patient_mode'] ?? 'all') !== 'none')) {
                $quota = max(0, (int)($assign->rules['poli2_included'] ?? 20));
                $excess = max(0, $bpjs + $umum - $quota);
                if ($excess > 0 && $bpjs > 0 && $umum > 0) {
                    // Default sesuai rekap klinik: alokasikan 20 pasien termasuk transport
                    // dari kategori BPJS terlebih dulu, kemudian dari kategori umum.
                    // Contoh 25 BPJS + 3 umum => 5 BPJS dan 3 umum berfee.
                    // Karena Excel tidak menyimpan urutan pasien, admin dapat mengoreksi
                    // kedua angka ini apabila pembagian sebenarnya berbeda.
                    $bpjsInput = $edited['bpjs_excess'] ?? null;
                    $umumInput = $edited['general_excess'] ?? null;
                    $bpjsBlank = $bpjsInput === null || $bpjsInput === '';
                    $umumBlank = $umumInput === null || $umumInput === '';
                    if ($bpjsBlank && $umumBlank) {
                        $bpjsIncluded = min($bpjs, $quota);
                        $bpjsExcess = $bpjs - $bpjsIncluded;
                        $generalExcess = max(0, $umum - max(0, $quota - $bpjsIncluded));
                    } elseif ($bpjsBlank || $umumBlank) {
                        throw ValidationException::withMessages(['rows.'.$id.'.bpjs_excess' => 'Jika mengoreksi pembagian Poli 2, isi kedua kolom BPJS dan umum setelah kuota. Atau kosongkan keduanya untuk hitung otomatis.']);
                    } else {
                        $bpjsExcess = (int)$bpjsInput;
                        $generalExcess = (int)$umumInput;
                    }
                    if ($bpjsExcess + $generalExcess !== $excess || $bpjsExcess > $bpjs || $generalExcess > $umum) {
                        throw ValidationException::withMessages(['rows.'.$id.'.bpjs_excess' => 'Pembagian BPJS + umum setelah kuota harus sama dengan '.$excess.' pasien dan tidak melebihi jumlah masing-masing.']);
                    }
                }
            }
            $shifts[] = [
                'work_date' => $edited['date'], 'shift_type' => $edited['shift_type'],
                'start_time' => $start ?: null, 'end_time' => $end ?: null,
                'standard_hours' => null, 'transport_override' => null,
                'bpjs_count' => $bpjs, 'general_count' => $umum,
                'bpjs_excess' => $bpjsExcess, 'general_excess' => $generalExcess,
                'usg_count' => 0, 'homecare_clinic' => 0, 'homecare_own' => 0,
            ];
        }
        if (!$shifts) throw ValidationException::withMessages(['rows' => 'Pilih setidaknya satu jadwal untuk diimport.']);
        if (count($shifts) > 150) throw ValidationException::withMessages(['rows' => 'Maksimal 150 jadwal per draft.']);
        if (count($data['rows']) !== count(array_filter($this->payrollRows($batch), fn($r) => $r['period'] === $period && $r['doctor'] === $doctorName))) {
            throw ValidationException::withMessages(['rows' => 'Jumlah baris tidak cocok dengan Excel. Perbarui halaman.']);
        }
        // Merge *verified daily inputs* into the true monthly recap fields used by R4.5.
        // This preserves per-shift Poli 2 quota and exact proportional transport before aggregating.
        $recapTransport = [];
        $recapCounts = [
            'bpjs_regular' => 0, 'umum_regular' => 0,
            'poli2_bpjs_paid' => 0, 'poli2_umum_paid' => 0,
            'usg' => (int)($data['services']['usg'] ?? 0),
            'homecare_clinic' => (int)($data['services']['homecare_clinic'] ?? 0),
            'homecare_own' => (int)($data['services']['homecare_own'] ?? 0),
        ];
        $rules = $assign->rules ?: [];
        $quota = max(0, (int)($rules['poli2_included'] ?? 20));
        foreach ($shifts as $shift) {
            $type = $shift['shift_type'];
            $standard = max(0.25, (float)($rules['shift_hours'][$type] ?? $rules['full_hours'] ?? 5));
            $minutes = (int)round($standard * 60);
            if ($shift['start_time'] && $shift['end_time']) {
                [$sh, $sm] = array_map('intval', explode(':', $shift['start_time']));
                [$eh, $em] = array_map('intval', explode(':', $shift['end_time']));
                $minutes = ($eh * 60 + $em) - ($sh * 60 + $sm);
                if ($minutes <= 0) $minutes += 1440;
            }
            $key = $type.'|'.$minutes;
            if (!isset($recapTransport[$key])) {
                $recapTransport[$key] = [
                    'shift_type' => $type, 'qty' => 0,
                    'hours_per_visit' => round($minutes / 60, 8),
                    'start_time' => null, 'end_time' => null,
                ];
            }
            $recapTransport[$key]['qty']++;
            if ($type === 'poli2') {
                $excess = max(0, $shift['bpjs_count'] + $shift['general_count'] - $quota);
                if ($excess > 0) {
                    if ($shift['general_count'] === 0) $recapCounts['poli2_bpjs_paid'] += $excess;
                    elseif ($shift['bpjs_count'] === 0) $recapCounts['poli2_umum_paid'] += $excess;
                    else {
                        $recapCounts['poli2_bpjs_paid'] += (int)$shift['bpjs_excess'];
                        $recapCounts['poli2_umum_paid'] += (int)$shift['general_excess'];
                    }
                }
            } else {
                $recapCounts['bpjs_regular'] += $shift['bpjs_count'];
                $recapCounts['umum_regular'] += $shift['general_count'];
            }
        }
        if (count($recapTransport) > 80) {
            throw ValidationException::withMessages(['rows' => 'Terlalu banyak variasi shift parsial untuk rekap (maksimal 80).']);
        }
        // Only explicit admin-entered procedures, never infer actions from patient headcounts.
        $actions = [];
        foreach (($data['actions'] ?? []) as $item) {
            $proc = DoctorProcedure::where('active', true)->findOrFail((int)$item['procedure_id']);
            if ($proc->branch && strcasecmp($proc->branch, $assign->branch) !== 0) {
                throw ValidationException::withMessages(['actions' => 'Tindakan '.$proc->label.' tidak berlaku di cabang dokter yang dipilih.']);
            }
            $actions[] = [
                'procedure_id' => (int)$proc->id, 'work_date' => null,
                'qty' => (int)$item['qty'], 'fixed_fee' => $item['fixed_fee'] ?? null,
                'percentage' => $item['percentage'] ?? null,
                'price' => (int)$item['price'], 'material' => (int)$item['material'],
                'claim' => (int)$item['claim'], 'patient' => (int)$item['patient'],
            ];
        }
        [$periodFrom, $periodTo] = DoctorPayrollPeriod::bounds($period);
        $input = [
            'doctor_assignment_id' => $assign->id, 'period' => $period, 'input_mode' => 'recap',
            'base_salary' => (int)$assign->base_salary, 'sip_fee' => (int)$assign->sip_fee,
            'pj_fee' => (int)$assign->pj_fee, 'absence_days' => 0,
            'recap_transport' => array_values($recapTransport), 'recap_counts' => $recapCounts, 'shifts' => [],
            'actions' => $actions, 'extras' => [], 'cash' => [],
            'notes' => 'Sumber Excel: '.$batch->original_filename.' | '.$doctorName.' | periode gaji '.$period.' ('.$periodFrom.' s.d. '.$periodTo.'). Sumber rincian jadwal tersedia pada arsip import ID '.$batch->id.'. SIP/PJ, cash, tindakan, dan potongan mohon diperiksa.',
        ];
        $calculation = $calculator->calculate($assign, $period, $input);
        if ($calculation['net_total'] < 0 || $calculation['transfer_due'] < 0) {
            throw ValidationException::withMessages(['rows' => 'Hasil nominal negatif; periksa tarif dan potongan dokter.']);
        }
        $payroll = DB::transaction(function () use ($assign, $period, $input, $calculation, $batch, $doctorName) {
            if (DoctorPayroll::where('doctor_assignment_id', $assign->id)->where('period', $period)->exists()) {
                throw ValidationException::withMessages(['doctor_assignment_id' => 'Sudah ada slip untuk dokter/unit/periode ini. Impor tidak akan menimpa draft maupun slip disetujui.']);
            }
            return DoctorPayroll::create([
                'doctor_assignment_id' => $assign->id, 'period' => $period, 'status' => 'draft',
                'input_snapshot' => $calculation['input'], 'calculation_snapshot' => $calculation,
                'gross_total' => $calculation['gross_total'], 'deduction_total' => $calculation['deduction_total'],
                'net_total' => $calculation['net_total'], 'cash_total' => $calculation['cash_total'],
                'transfer_due' => $calculation['transfer_due'], 'notes' => $input['notes'],
                'created_by' => auth()->id(),
            ]);
        });
        AuditLog::record('doctor.import.draft_created', $payroll, ['batch_id' => $batch->id, 'source_doctor' => $doctorName, 'rows' => count($shifts), 'mode' => 'recap']);
        return redirect()->route('doctors.edit', $payroll)->with('success', 'Draft rekap bulanan berhasil dibuat (26–25). Periksa tindakan, tarif, SIP/PJ, cash dan potongan sebelum menyetujui.');
    }
}
