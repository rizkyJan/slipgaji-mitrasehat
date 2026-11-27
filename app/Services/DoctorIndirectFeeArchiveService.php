<?php

namespace App\Services;

use App\Models\DoctorImportBatch;
use App\Models\DoctorPayroll;
use Illuminate\Validation\ValidationException;

/** R4.28: ONLY prepares appendix records from an existing encrypted Excel archive. */
final class DoctorIndirectFeeArchiveService
{
    /** Read-only; source archives remain unchanged. */
    public function candidates(DoctorPayroll $payroll): array
    {
        $options = [];
        foreach (DoctorImportBatch::query()->latest('id')->limit(60)->get() as $batch) {
            $groups = [];
            foreach ((array) $batch->rows as $row) {
                if (!is_array($row) || empty($row['date']) || empty($row['doctor'])) continue;
                try {
                    if (!DoctorPayrollPeriod::includes((string) $payroll->period, (string) $row['date'])) continue;
                } catch (\Throwable $e) { continue; }
                $name = trim((string) $row['doctor']);
                if ($name === '') continue;
                $groups[$name] = ($groups[$name] ?? 0) + 1;
            }
            foreach ($groups as $doctor => $count) {
                $options[] = [
                    'batch_id' => (int) $batch->id,
                    'doctor' => $doctor,
                    'count' => $count,
                    'filename' => (string) $batch->original_filename,
                ];
            }
        }
        return $options;
    }

    /**
     * Read-only preview: never modifies payroll, input/calculation snapshot or source archive.
     * A record without a reliable practice session blocks importing rather than inventing it.
     */
    public function preview(DoctorPayroll $payroll, DoctorImportBatch $batch, string $sourceDoctor): array
    {
        $grouped = [];
        $errors = [];
        $sourceCount = 0;
        $sourceBpjs = 0;
        $sourceUmum = 0;
        foreach ((array) $batch->rows as $row) {
            if (!is_array($row) || trim((string) ($row['doctor'] ?? '')) !== $sourceDoctor) continue;
            $date = (string) ($row['date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
            try {
                if (!DoctorPayrollPeriod::includes((string) $payroll->period, $date)) continue;
            } catch (\Throwable $e) { continue; }
            $sourceCount++;
            foreach ((array) ($row['issues'] ?? []) as $issue) {
                if (!is_string($issue)) continue;
                if (stripos($issue, 'Jumlah pasien kosong') !== false
                    || stripos($issue, 'Jumlah pasien tidak valid') !== false
                    || stripos($issue, 'Total pasien berbeda') !== false) {
                    $errors[] = "Masalah jumlah pasien pada $date: $issue";
                }
            }
            $bpjs = $row['bpjs'] ?? null;
            $umum = $row['umum'] ?? null;
            if (!is_int($bpjs) || !is_int($umum) || $bpjs < 0 || $umum < 0 || $bpjs > 1000 || $umum > 1000) {
                $errors[] = "Data pasien tidak valid pada $date.";
                continue;
            }
            // Never silently use a label such as 'biasa' or 'khusus' as a morning/afternoon session.
            $session = $this->session($row);
            if ($session === null) {
                $errors[] = "Sesi praktik tidak pasti pada $date (baris Excel ".(string)($row['source_row'] ?? '?').").";
                continue;
            }
            $key = $date.'|'.$session;
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['date'=>$date, 'session'=>$session, 'bpjs'=>0, 'umum'=>0];
            }
            $grouped[$key]['bpjs'] += $bpjs;
            $grouped[$key]['umum'] += $umum;
            $sourceBpjs += $bpjs;
            $sourceUmum += $umum;
        }
        if ($sourceCount === 0) $errors[] = 'Tidak ditemukan jadwal dokter dan periode ini pada arsip yang dipilih.';
        if (count($grouped) > 250) $errors[] = 'Rincian terlalu banyak (maksimal 250 tanggal/sesi).';
        ksort($grouped);
        $rows = array_values($grouped);
        $snapshot = (array) $payroll->calculation_snapshot;
        $counts = (array) ($snapshot['recap']['counts'] ?? []);
        $expectedBpjs = (int) ($counts['bpjs_regular'] ?? 0) + (int) ($counts['poli2_bpjs_paid'] ?? 0);
        $expectedUmum = (int) ($counts['umum_regular'] ?? 0) + (int) ($counts['poli2_umum_paid'] ?? 0);
        $hasPoli2 = (int) ($counts['poli2_bpjs_paid'] ?? 0) > 0 || (int) ($counts['poli2_umum_paid'] ?? 0) > 0;
        $totalsMatch = $sourceBpjs === $expectedBpjs && $sourceUmum === $expectedUmum;
        $fingerprint = hash('sha256', json_encode([$payroll->id,$payroll->period,(int)$batch->id,$sourceDoctor,$rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return compact('rows','errors','sourceCount','sourceBpjs','sourceUmum','expectedBpjs','expectedUmum','totalsMatch','hasPoli2','fingerprint');
    }

    private function session(array $row): ?string
    {
        $label = mb_strtoupper((string) ($row['shift_label'] ?? ''), 'UTF-8');
        $matches = [];
        foreach (['PAGI','SIANG','MALAM'] as $name) {
            if (preg_match('/(?<![\pL])'. $name .'(?![\pL])/u', $label)) $matches[] = $name;
        }
        if (count($matches) > 1) return null;
        $fromLabel = $matches[0] ?? null;
        $start = (string) ($row['start_time'] ?? '');
        $fromTime = null;
        if (preg_match('/^(\d{2}):(\d{2})$/', $start, $parts)) {
            $hour = (int) $parts[1];
            $minute = (int) $parts[2];
            if ($hour > 23 || $minute > 59) return null;
            $fromTime = $hour >= 5 && $hour < 12 ? 'PAGI' : ($hour >= 12 && $hour < 18 ? 'SIANG' : 'MALAM');
        }
        if ($fromLabel && $fromTime && $fromLabel !== $fromTime) return null;
        return $fromLabel ?? $fromTime;
    }
}
