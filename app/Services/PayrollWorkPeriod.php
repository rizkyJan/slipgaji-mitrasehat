<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\DoctorPayroll;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Date labels only. NEVER changes payroll period, calculations, or Excel matching. */
final class PayrollWorkPeriod
{
    public const TABLE = 'payroll_work_period_overrides';

    /** @return array{0:string,1:string} */
    public static function defaults(string $period): array
    {
        return DoctorPayrollPeriod::bounds($period);
    }

    /** @return array{0:string,1:string} */
    public static function forDoctor(DoctorPayroll $payroll): array
    {
        return self::find('doctor', (int) $payroll->doctor_assignment_id, (string) $payroll->period);
    }

    /** @return array{0:string,1:string} */
    public static function forEmployee(Payroll $payroll): array
    {
        return self::find('employee', (int) $payroll->employee_id, (string) $payroll->period);
    }

    /** @return array{0:string,1:string} */
    public static function find(string $kind, int $personId, string $period): array
    {
        $fallback = self::defaults($period);
        if (!in_array($kind, ['doctor', 'employee'], true) || $personId < 1) return $fallback;
        if (!Schema::hasTable(self::TABLE)) return $fallback;

        $row = DB::table(self::TABLE)->where('person_kind', $kind)
            ->where('person_id', $personId)->where('period', $period)->first();
        if (!$row) return $fallback;
        return [substr((string) $row->work_from, 0, 10), substr((string) $row->work_to, 0, 10)];
    }

    public static function nice(string $date): string
    {
        return Carbon::parse($date)->locale('id')->translatedFormat('d F Y');
    }
}
