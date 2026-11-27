<?php
namespace App\Services;

use App\Models\DoctorAssignment;

/** Terbatas pada dokter gigi cabang Darasan, karena preset Darasan masih 'general'. */
final class DoctorMouthOpening
{
    public static function applies(?DoctorAssignment $assignment): bool
    {
        if (!$assignment || stripos((string)$assignment->branch, 'Darasan') === false) {
            return false;
        }
        if (strtolower((string)($assignment->kind ?? '')) === 'dentist') {
            return true;
        }
        $name = trim((string)($assignment->employee?->name ?? ''));
        return preg_match('/^drg(?=\.|\s|$)/i', $name) === 1;
    }
}
