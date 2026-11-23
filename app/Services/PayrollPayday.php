<?php
namespace App\Services;

use App\Models\AppSetting;
use Carbon\Carbon;
use InvalidArgumentException;

/** Jadwal gajian bersama (karyawan dan dokter), tanpa menyatakan telah dibayar. */
final class PayrollPayday
{
    public const KEY_PREFIX = 'payroll_payday_';

    public static function validPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $period);
    }

    public static function forPeriod(string $period): string
    {
        if (!self::validPeriod($period)) {
            throw new InvalidArgumentException('Periode penggajian tidak valid.');
        }

        $global = AppSetting::setting(self::KEY_PREFIX . $period);
        if (self::validDate($global)) return $global;

        // Kompatibilitas konfigurasi dokter R4.14 jika belum ada jadwal global.
        $overrides = (array) config('doctor_slip_pdf.paydate_by_period', []);
        $legacy = $overrides[$period] ?? null;
        if (is_string($legacy) && self::validDate($legacy)) return $legacy;

        $month = Carbon::createFromFormat('!Y-m', $period);
        $day = max(1, min(31, (int) config('doctor_slip_pdf.payday_day', 26)));
        return $month->day(min($day, $month->daysInMonth))->toDateString();
    }

    public static function validDate(?string $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
        [$y,$m,$d] = array_map('intval',explode('-', $value));
        return checkdate($m,$d,$y);
    }

    public static function workDates(string $period): array
    {
        if (!self::validPeriod($period)) throw new InvalidArgumentException('Periode penggajian tidak valid.');
        $date = Carbon::createFromFormat('!Y-m', $period);
        return [$date->copy()->subMonthNoOverflow()->day(26)->toDateString(), $date->day(25)->toDateString()];
    }
}
