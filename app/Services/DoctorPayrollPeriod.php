<?php

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Periode gaji dokter: 26 bulan sebelumnya sampai 25 bulan berjalan (inklusif).
 * Kode periode 2026-10 berarti tanggal 26/09/2026 s.d. 25/10/2026.
 */
final class DoctorPayrollPeriod
{
    public static function fromWorkDate(string $workDate): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $workDate);
        if (!$date || $date->format('Y-m-d') !== $workDate) {
            throw new InvalidArgumentException('Tanggal praktik tidak valid: '.$workDate);
        }

        return (int)$date->format('d') >= 26
            ? $date->modify('first day of next month')->format('Y-m')
            : $date->format('Y-m');
    }

    /** @return array{0:string,1:string} */
    public static function bounds(string $period): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $period.'-01');
        if (!$date || $date->format('Y-m') !== $period) {
            throw new InvalidArgumentException('Kode periode tidak valid: '.$period);
        }

        return [$date->modify('first day of previous month')->format('Y-m').'-26', $date->format('Y-m').'-25'];
    }

    public static function includes(string $period, string $workDate): bool
    {
        [$start, $end] = self::bounds($period);
        return self::fromWorkDate($workDate) === $period && $workDate >= $start && $workDate <= $end;
    }
}
