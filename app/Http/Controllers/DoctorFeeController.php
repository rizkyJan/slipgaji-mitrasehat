<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DoctorFeeDay;
use App\Models\DoctorFeePayment;
use App\Models\DoctorPayroll;
use App\Services\DoctorFeeReconciliation;
use App\Services\DoctorFeeReportPdf;
use App\Services\DoctorPayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Separate dated ledger: never mutates DoctorPayroll totals or signed snapshots. */
class DoctorFeeController extends Controller
{
    public function index(Request $request)
    {
        $period = (string)$request->query('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) $period = now()->format('Y-m');
        $payrolls = DoctorPayroll::with('assignment.employee')
            ->where('period', $period)->orderBy('doctor_assignment_id')->paginate(30)->withQueryString();
        return view('doctors.fee_index', compact('payrolls', 'period'));
    }

    private function days(DoctorPayroll $payroll)
    {
        return DoctorFeeDay::query()->where('doctor_payroll_id', $payroll->id)
            ->with(['activePayments' => fn($q) => $q->orderBy('payment_date')->orderBy('id')])
            ->orderBy('work_date')->get();
    }

    public function show(DoctorPayroll $doctorPayroll, DoctorFeeReconciliation $reconciler)
    {
        $doctorPayroll->load('assignment.employee');
        $days = $this->days($doctorPayroll);
        $totals = $reconciler->totals($days);
        $existingDates = $days->map(fn($d) => $d->work_date->format('Y-m-d'))->all();
        $sourceDates = array_values(array_diff($reconciler->sourceDates($doctorPayroll), $existingDates));
        [$periodStart, $periodEnd] = DoctorPayrollPeriod::bounds((string)$doctorPayroll->period);
        return view('doctors.fee_show', compact('doctorPayroll', 'days', 'totals', 'sourceDates', 'periodStart', 'periodEnd'));
    }

    private function allowWrite(): void
    {
        abort_unless(auth()->user()?->can('manage-settings'), 403,
            'Hanya admin yang dapat mengubah rekonsiliasi pembayaran.');
    }

    private function resolveDay(DoctorPayroll $payroll, int $day): DoctorFeeDay
    {
        return DoctorFeeDay::query()->where('doctor_payroll_id', $payroll->id)->findOrFail($day);
    }

    public function addDay(Request $request, DoctorPayroll $doctorPayroll)
    {
        $this->allowWrite();
        $data = $request->validate(['work_date' => ['required','date_format:Y-m-d']]);
        if (!DoctorPayrollPeriod::includes((string)$doctorPayroll->period, $data['work_date']))
            throw ValidationException::withMessages(['work_date' => 'Tanggal harus dalam periode kerja slip (26-25).']);
        $day = DoctorFeeDay::firstOrCreate(
            ['doctor_payroll_id' => $doctorPayroll->id, 'work_date' => $data['work_date']],
            ['fee_amount' => null, 'confirmed' => false, 'payment_checked' => false, 'source' => 'manual', 'updated_by' => auth()->id()]
        );
        if ($day->wasRecentlyCreated) AuditLog::record('doctor.fee.day_created', $day, ['work_date' => $data['work_date']]);
        return redirect()->route('doctors.fees.show', $doctorPayroll)->with('success', 'Tanggal berhasil ditambahkan. Isi fee dan konfirmasi statusnya.');
    }

    public function importDates(Request $request, DoctorPayroll $doctorPayroll, DoctorFeeReconciliation $reconciler)
    {
        $this->allowWrite();
        $data = $request->validate(['dates' => ['required','array','min:1','max:40'], 'dates.*' => ['required','date_format:Y-m-d']]);
        $allowed = array_fill_keys($reconciler->sourceDates($doctorPayroll), true);
        foreach ($data['dates'] as $date) {
            if (!isset($allowed[$date]) || !DoctorPayrollPeriod::includes((string)$doctorPayroll->period, $date))
                throw ValidationException::withMessages(['dates' => 'Ada tanggal yang tidak cocok dengan arsip import dokter/periode ini.']);
        }
        DB::transaction(function () use ($data, $doctorPayroll) {
            foreach (array_unique($data['dates']) as $date) {
                $day = DoctorFeeDay::firstOrCreate(
                    ['doctor_payroll_id' => $doctorPayroll->id, 'work_date' => $date],
                    ['fee_amount' => null, 'confirmed' => false, 'payment_checked' => false, 'source' => 'import', 'updated_by' => auth()->id()]
                );
                if ($day->wasRecentlyCreated) AuditLog::record('doctor.fee.day_from_import', $day, ['work_date' => $date]);
            }
        });
        return redirect()->route('doctors.fees.show', $doctorPayroll)->with('success', 'Tanggal import ditambahkan sebagai PERLU KONFIRMASI. Belum ada status bayar atau nominal otomatis.');
    }

    public function saveDay(Request $request, DoctorPayroll $doctorPayroll, int $day)
    {
        $this->allowWrite();
        $data = $request->validate([
            'fee_amount' => ['nullable','integer','min:1','max:9999999999'],
            'confirmed' => ['required','boolean'],
            'payment_checked' => ['required','boolean'],
            'note' => ['nullable','string','max:500'],
        ]);
        $isConfirmed = (bool)$data['confirmed'];
        $paymentChecked = (bool)$data['payment_checked'];
        $amount = isset($data['fee_amount']) && $data['fee_amount'] !== '' ? (int)$data['fee_amount'] : null;
        if ($isConfirmed && $amount === null) throw ValidationException::withMessages(['fee_amount' => 'Isi nominal fee sebelum menyatakan sudah diverifikasi.']);
        if ($paymentChecked && !$isConfirmed) throw ValidationException::withMessages(['payment_checked' => 'Nominal fee perlu diverifikasi lebih dahulu.']);
        DB::transaction(function () use ($doctorPayroll, $day, $amount, $isConfirmed, $paymentChecked, $data) {
            // Lock the parent and child to serialize confirmation across all dates.
            $lockedPayroll = DoctorPayroll::query()->whereKey($doctorPayroll->id)->lockForUpdate()->firstOrFail();
            $row = DoctorFeeDay::query()->where('doctor_payroll_id', $lockedPayroll->id)->lockForUpdate()->findOrFail($day);
            $paid = (int)$row->activePayments()->sum('amount');
            if ($paid > 0 && (!$isConfirmed || $amount === null || $amount < $paid))
                throw ValidationException::withMessages(['fee_amount' => 'Fee tidak boleh dikurangi di bawah pembayaran yang sudah tercatat. Koreksi transaksi dahulu dengan alasan.']);
            $other = (int)DoctorFeeDay::query()->where('doctor_payroll_id', $lockedPayroll->id)
                ->where('id', '!=', $row->id)->where('confirmed', true)->sum('fee_amount');
            if ($isConfirmed && $other + $amount > (int)$lockedPayroll->gross_total)
                throw ValidationException::withMessages(['fee_amount' => 'Total fee harian terverifikasi melebihi penerimaan bruto di slip. Periksa komponen dan kemungkinan duplikasi.']);
            $old = ['amount' => $row->fee_amount, 'confirmed' => $row->confirmed, 'payment_checked' => $row->payment_checked, 'note' => $row->note];
            $row->update(['fee_amount' => $amount, 'confirmed' => $isConfirmed, 'payment_checked' => $paymentChecked,
                'note' => $data['note'] ?? null, 'updated_by' => auth()->id()]);
            AuditLog::record('doctor.fee.day_verified', $row, ['old' => $old,
                'new' => ['amount' => $amount, 'confirmed' => $isConfirmed, 'payment_checked' => $paymentChecked, 'note' => $data['note'] ?? null]]);
        });
        return redirect()->route('doctors.fees.show', $doctorPayroll)->with('success', 'Fee tanggal tersebut berhasil diperbarui tanpa mengubah nominal slip.');
    }

    public function payment(Request $request, DoctorPayroll $doctorPayroll, int $day)
    {
        $this->allowWrite();
        $data = $request->validate([
            'payment_date' => ['required','date_format:Y-m-d'],
            'method' => ['required',Rule::in(['cash','transfer'])],
            'amount' => ['required','integer','min:1','max:9999999999'],
            'note' => ['nullable','string','max:500'],
            'request_key' => ['required','uuid'],
        ]);
        $amount = (int)$data['amount'];
        $isDuplicate = DB::transaction(function () use ($doctorPayroll, $day, $data, $amount) {
            $row = DoctorFeeDay::query()->where('doctor_payroll_id', $doctorPayroll->id)
                ->lockForUpdate()->findOrFail($day);
            if ($row->payments()->where('request_key', $data['request_key'])->exists()) return true;
            if (!$row->confirmed || $row->fee_amount === null)
                throw ValidationException::withMessages(['amount' => 'Verifikasi nominal fee dahulu sebelum mencatat pembayaran.']);
            $workDate = $row->work_date->format('Y-m-d');
            if ($data['payment_date'] < $workDate || $data['payment_date'] > now()->format('Y-m-d'))
                throw ValidationException::withMessages(['payment_date' => 'Tanggal pembayaran harus setelah tanggal praktik dan tidak boleh di masa depan.']);
            $paid = (int)$row->activePayments()->sum('amount');
            if ($paid + $amount > (int)$row->fee_amount)
                throw ValidationException::withMessages(['amount' => 'Pembayaran melebihi sisa fee tanggal praktik.']);
            $payment = DoctorFeePayment::create([
                'doctor_fee_day_id' => $row->id, 'payment_date' => $data['payment_date'],
                'method' => $data['method'], 'amount' => $amount,
                'request_key' => $data['request_key'], 'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);
            AuditLog::record('doctor.fee.payment_recorded', $payment, [
                'day_id' => $row->id, 'method' => $data['method'], 'amount' => $amount,
                'payment_date' => $data['payment_date'],
            ]);
            return false;
        });
        return redirect()->route('doctors.fees.show', $doctorPayroll)
            ->with('success', $isDuplicate ? 'Transaksi sudah pernah dicatat; tidak ditambahkan dua kali.' : 'Pembayaran tercatat dengan riwayat audit.');
    }

    public function voidPayment(Request $request, DoctorPayroll $doctorPayroll, int $day, int $payment)
    {
        $this->allowWrite();
        $data = $request->validate(['reason' => ['required','string','min:8','max:500']]);
        DB::transaction(function () use ($doctorPayroll, $day, $payment, $data) {
            $row = DoctorFeeDay::query()->where('doctor_payroll_id', $doctorPayroll->id)->lockForUpdate()->findOrFail($day);
            $record = DoctorFeePayment::query()->where('doctor_fee_day_id', $row->id)->lockForUpdate()->findOrFail($payment);
            if ($record->voided_at !== null)
                throw ValidationException::withMessages(['reason' => 'Transaksi ini sudah dibatalkan.']);
            $record->update(['voided_at' => now(), 'voided_by' => auth()->id(), 'void_reason' => $data['reason']]);
            AuditLog::record('doctor.fee.payment_voided', $record, [
                'day_id' => $row->id, 'method' => $record->method,
                'amount' => $record->amount, 'reason' => $data['reason'],
            ]);
        });
        return redirect()->route('doctors.fees.show', $doctorPayroll)->with('success', 'Transaksi dibatalkan dengan catatan audit; datanya tidak dihapus.');
    }

    public function pdf(DoctorPayroll $doctorPayroll, DoctorFeeReconciliation $reconciler, DoctorFeeReportPdf $printer)
    {
        $doctorPayroll->load('assignment.employee');
        $days = $this->days($doctorPayroll);
        $totals = $reconciler->totals($days);
        $bytes = $printer->render($doctorPayroll, $days, $totals);
        $filename = 'lampiran-fee-harian-'.$doctorPayroll->id.'-'.$doctorPayroll->period.'.pdf';
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
