<?php
namespace App\Services;

use App\Models\Payroll;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Identitas penerbitan dan integritas data yang disetujui; rahasia verifikasi terenkripsi di DB. */
class SlipVerification
{
    public function issue(Payroll $payroll): Payroll
    {
        if ($payroll->status !== 'approved') throw new RuntimeException('Hanya slip yang disetujui dapat diverifikasi.');
        return DB::transaction(function () use ($payroll) {
            $locked = Payroll::whereKey($payroll->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved') throw new RuntimeException('Slip belum disetujui.');
            if ($locked->slip_number && $locked->verification_token && $locked->verification_signature) {
                $this->assertIntact($locked);
                return $locked;
            }
            // Slip R2 yang sudah approved sebelum patch diterbitkan pada akses pertama,
            // tanpa mengubah nilai historisnya. Snapshot diikat sejak penerbitan R3.
            $id = str_pad((string) $locked->id, 7, '0', STR_PAD_LEFT);
            $period = str_replace('-', '/', $locked->period);
            $locked->slip_number = 'MSMS/SG/'.$period.'/'.$id;
            $locked->verification_token = bin2hex(random_bytes(24));
            $locked->verification_signature = $this->signature($locked);
            $locked->verified_issued_at = now();
            $locked->save();
            AuditLog::record('payslip.verification_issued', $locked, [
                'slip_number' => $locked->slip_number,
                'approved_at' => $locked->approved_at?->toIso8601String(),
            ]);
            return $locked;
        });
    }

    public function assertIntact(Payroll $payroll): void
    {
        if (!$this->isIntact($payroll)) {
            throw new RuntimeException('Integritas slip tidak cocok dengan data saat penerbitan. Hubungi administrator; jangan gunakan dokumen ini.');
        }
    }

    public function isIntact(Payroll $payroll): bool
    {
        return $payroll->status === 'approved'
            && is_string($payroll->verification_signature)
            && strlen($payroll->verification_signature) === 64
            && hash_equals($payroll->verification_signature, $this->signature($payroll));
    }

    public function validToken(Payroll $payroll, string $token): bool
    {
        return strlen($token) === 48 && ctype_xdigit($token)
            && is_string($payroll->verification_token)
            && hash_equals($payroll->verification_token, strtolower($token));
    }

    public function publicUrl(Payroll $payroll): string
    {
        if (!$payroll->verification_token) throw new RuntimeException('Slip belum memiliki identitas verifikasi.');
        return route('slips.verify', ['payroll' => $payroll->id, 'token' => $payroll->verification_token]);
    }

    private function signature(Payroll $payroll): string
    {
        $secret = (string) config('app.key');
        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7), true) ?: '';
        }
        if (strlen($secret) < 32) throw new RuntimeException('APP_KEY Laravel belum valid.');
        $payload = [
            'v' => 1,
            'id' => (int) $payroll->id,
            'employee_id' => (int) $payroll->employee_id,
            'period' => $payroll->period,
            'slip_number' => $payroll->slip_number,
            'approved_at' => $payroll->approved_at?->toIso8601String(),
            'input' => $payroll->input_snapshot,
            'calculation' => $payroll->calculation_snapshot,
            'gross' => (int) $payroll->gross_total,
            'deduction' => (int) $payroll->deduction_total,
            'net' => (int) $payroll->net_total,
        ];
        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $secret);
    }
}
