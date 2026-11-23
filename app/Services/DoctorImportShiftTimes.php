<?php
namespace App\Services;

use App\Models\DoctorImportBatch;
use App\Models\DoctorPayroll;

/**
 * Read-only prefill for OLD imported recap drafts.
 * The original Excel time-range is recovered only for unambiguous matches of
 * source batch, doctor, 26-25 payroll period, shift type, duration and quantity.
 * Never touches the database or approved slips.
 */
class DoctorImportShiftTimes
{
    public function suggest(DoctorPayroll $payroll, array $input): array
    {
        $unchanged = ['input' => $input, 'recovered' => 0];
        if ($payroll->status !== 'draft' || ($input['input_mode'] ?? null) !== 'recap'
            || empty($input['recap_transport']) || !is_array($input['recap_transport'])) return $unchanged;

        $note = (string)($input['notes'] ?? $payroll->notes ?? '');
        if (!preg_match('/arsip import ID\s+(\d+)\b/iu', $note, $batchMatch)
            || !preg_match('/\|\s*([^|]+?)\s*\|\s*periode gaji\s+(\d{4}-\d{2})\b/iu', $note, $nameMatch)
            || $nameMatch[2] !== (string)$payroll->period) return $unchanged;
        $batch = DoctorImportBatch::query()->find((int)$batchMatch[1]);
        if (!$batch || !is_array($batch->rows)) return $unchanged;
        return $this->matchRows(
            $input, $batch->rows, trim($nameMatch[1]), (string)$payroll->period,
            (array)($payroll->calculation_snapshot['rules'] ?? [])
        );
    }

    /** Pure matching logic, separately testable without a database connection. */
    public function matchRows(array $input, array $source, string $doctor, string $period, array $rules = []): array
    {
        $none = ['input' => $input, 'recovered' => 0];
        $recap = (array)($input['recap_transport'] ?? []);
        if (!$recap) return $none;
        $normalDoctor = static fn(string $s): string => mb_strtoupper(trim($s), 'UTF-8');
        $sourceGroups = [];
        foreach ($source as $record) {
            if (!is_array($record) || $normalDoctor((string)($record['doctor'] ?? '')) !== $normalDoctor($doctor)) continue;
            $date = (string)($record['date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
            try {
                if (DoctorPayrollPeriod::fromWorkDate($date) !== $period) continue;
            } catch (\Throwable $e) { continue; }
            $start = (string)($record['start_time'] ?? '');
            $end = (string)($record['end_time'] ?? '');
            $mins = self::elapsedMinutes($start, $end);
            if ($mins === null || $mins < 15 || $mins > 960) continue;
            $type = (string)($record['shift_type'] ?? '');
            if ($type === '') continue;
            $sig = $type.'|'.$mins;
            $slot = $start.'|'.$end;
            $sourceGroups[$sig][$slot] = ($sourceGroups[$sig][$slot] ?? 0) + 1;
        }

        // Only recover groups with a single recap row for their type/duration.
        // Multiple manually adjusted rows of the same signature are ambiguous.
        $targets = [];
        foreach ($recap as $idx => $row) {
            if (!is_array($row) || !empty($row['start_time']) || !empty($row['end_time'])) continue;
            if (!is_numeric($row['hours_per_visit'] ?? null) || (int)($row['qty'] ?? 0) < 1) continue;
            $type = (string)($row['shift_type'] ?? '');
            $minutes = (int)round((float)$row['hours_per_visit'] * 60);
            if ($minutes < 15 || $minutes > 960) continue;
            $full = (float)($rules['shift_hours'][$type] ?? $rules['full_hours'] ?? 5);
            // R4.12: only partial transport below the 60-minute grace threshold.
            if ($minutes + 60 >= (int)round($full * 60)) continue;
            $targets[$type.'|'.$minutes][] = $idx;
        }

        $proposed = [];
        $restored = 0;
        foreach ($targets as $sig => $indexes) {
            if (count($indexes) !== 1 || empty($sourceGroups[$sig])) continue;
            $idx = $indexes[0];
            $qty = (int)$recap[$idx]['qty'];
            if (array_sum($sourceGroups[$sig]) !== $qty) continue;
            $proposed[$idx] = [];
            foreach ($sourceGroups[$sig] as $times => $count) {
                [$start, $end] = explode('|', $times, 2);
                $filled = $recap[$idx];
                $filled['qty'] = $count;
                $filled['start_time'] = $start;
                $filled['end_time'] = $end;
                $proposed[$idx][] = $filled;
            }
            $restored += $qty;
        }
        if (!$restored) return $none;
        $updated = [];
        foreach ($recap as $idx => $row) {
            foreach (($proposed[$idx] ?? [$row]) as $filled) $updated[] = $filled;
        }
        if (count($updated) > 80) return $none;
        $input['recap_transport'] = $updated;
        return ['input' => $input, 'recovered' => $restored];
    }

    private static function elapsedMinutes(string $start, string $end): ?int
    {
        $convert = static function (string $time): ?int {
            if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $parts)) return null;
            $hours = (int)$parts[1]; $minutes = (int)$parts[2];
            if ($hours > 23 || $minutes > 59) return null;
            return $hours * 60 + $minutes;
        };
        $a = $convert($start); $b = $convert($end);
        if ($a === null || $b === null) return null;
        $length = $b - $a;
        return $length <= 0 ? $length + 1440 : $length;
    }
}
