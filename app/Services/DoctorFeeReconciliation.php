<?php
namespace App\Services;

use App\Models\DoctorFeeDay;
use App\Models\DoctorImportBatch;
use App\Models\DoctorPayroll;

/** Reporting only. Never changes payroll's signed calculation snapshot. */
final class DoctorFeeReconciliation
{
    public function sourceDates(DoctorPayroll $payroll): array
    {
        $note = (string)($payroll->notes ?: (($payroll->input_snapshot ?? [])['notes'] ?? ''));
        if (!preg_match('/arsip import ID\s+(\d+)\b/iu', $note, $batchMatch) ||
            !preg_match('/\|\s*([^|]+?)\s*\|\s*periode gaji\s+(\d{4}-\d{2})\b/iu', $note, $doctorMatch) ||
            $doctorMatch[2] !== (string)$payroll->period) return [];
        $batch = DoctorImportBatch::query()->find((int)$batchMatch[1]);
        if (!$batch) return [];
        try { $rows = $batch->rows; } catch (\Throwable $e) { report($e); return []; }
        if (!is_array($rows)) return [];
        $expectedName = mb_strtoupper(trim($doctorMatch[1]), 'UTF-8');
        $result = [];
        foreach ($rows as $entry) {
            if (!is_array($entry) || mb_strtoupper(trim((string)($entry['doctor'] ?? '')), 'UTF-8') !== $expectedName) continue;
            $date = (string)($entry['date'] ?? '');
            try { if (!DoctorPayrollPeriod::includes((string)$payroll->period, $date)) continue; }
            catch (\Throwable $e) { continue; }
            $result[$date] = true;
        }
        ksort($result);
        return array_keys($result);
    }

    /** @param \Illuminate\Support\Collection<int, DoctorFeeDay> $days */
    public function totals($days): array
    {
        $summary = ['dates' => 0, 'pending' => 0, 'confirmed_fee' => 0,
            'cash' => 0, 'transfer' => 0, 'paid' => 0, 'outstanding' => 0,
            'unpaid_dates' => 0, 'partial_dates' => 0];
        foreach ($days as $day) {
            $summary['dates']++;
            foreach ($day->activePayments as $payment) {
                $summary[$payment->method] += (int)$payment->amount;
            }
            $summary['paid'] += $day->paid_amount;
            if ($day->confirmed && $day->fee_amount !== null) $summary['confirmed_fee'] += (int)$day->fee_amount;
            if (!$day->confirmed || !$day->payment_checked || $day->fee_amount === null) { $summary['pending']++; continue; }
            $summary['outstanding'] += $day->remaining;
            if ($day->reconciliation_status === 'unpaid') $summary['unpaid_dates']++;
            if ($day->reconciliation_status === 'partial') $summary['partial_dates']++;
        }
        return $summary;
    }
}
