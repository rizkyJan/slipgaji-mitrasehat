<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payroll;
use App\Models\PayslipDelivery;
use App\Services\GmailService;
use App\Services\PayslipPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $period = (string) $request->query('period', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) $period = now()->format('Y-m');
        $payrolls = Payroll::with(['employee', 'deliveries' => fn ($q) => $q->latest()])
            ->where('period', $period)->where('status', 'approved')->orderBy('employee_id')->get();
        $connection = $request->user()->googleConnection;
        return view('payrolls.deliveries', compact('period', 'payrolls','connection'));
    }

    private function send(Payroll $payroll, GmailService $gmail, PayslipPdf $pdf): array
    {
        $connection = auth()->user()->googleConnection;
        if (!$connection) return ['ok'=>false, 'message'=>'Hubungkan akun Google di Pengaturan terlebih dahulu.'];
        if ($payroll->status !== 'approved') return ['ok'=>false, 'message'=>'Slip belum disetujui.'];
        if (PayslipDelivery::where('payroll_id', $payroll->id)->where('status', 'sent')->exists()) {
            return ['ok'=>false, 'message'=>'Slip sudah pernah berhasil dikirim. Pengiriman ganda dicegah.'];
        }
        $recipient = $payroll->employee?->email ?? '';
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) return ['ok'=>false, 'message'=>'Alamat email karyawan belum valid.'];
        try {
            $binary = $pdf->render($payroll);
            $id = $gmail->sendPayslip($connection, $payroll, $binary);
            PayslipDelivery::create([
                'payroll_id' => $payroll->id, 'user_id' => auth()->id(),
                'recipient_email' => $recipient, 'status' => 'sent', 'gmail_message_id' => $id,
                'sent_at' => now(),
            ]);
            AuditLog::record('payslip.sent', $payroll, ['recipient' => $recipient, 'gmail_message_id' => $id]);
            return ['ok'=>true, 'message'=>'Terkirim ke '.$recipient];
        } catch (\Throwable $e) {
            report($e);
            PayslipDelivery::create([
                'payroll_id' => $payroll->id, 'user_id' => auth()->id(),
                'recipient_email' => $recipient, 'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 250),
            ]);
            AuditLog::record('payslip.failed', $payroll, ['recipient' => $recipient]);
            return ['ok'=>false, 'message'=>'Gagal: '.$e->getMessage()];
        }
    }

    public function single(Payroll $payroll, GmailService $gmail, PayslipPdf $pdf)
    {
        $result = $this->send($payroll, $gmail, $pdf);
        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function bulk(Request $request, GmailService $gmail, PayslipPdf $pdf)
    {
        $data = $request->validate(['payroll_ids' => ['required','array','min:1','max:20'], 'payroll_ids.*' => ['integer','distinct','exists:payrolls,id']]);
        if (!$request->user()->googleConnection) return back()->with('error', 'Hubungkan akun Google terlebih dahulu.');
        $ok = 0; $fail = 0;
        foreach ($data['payroll_ids'] as $id) {
            $result = $this->send(Payroll::with('employee')->findOrFail($id), $gmail, $pdf);
            $result['ok'] ? $ok++ : $fail++;
        }
        return back()->with($fail ? 'error' : 'success', "Pengiriman selesai: {$ok} berhasil, {$fail} dilewati/gagal. Periksa riwayat per slip.");
    }
}
