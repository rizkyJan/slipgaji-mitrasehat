<?php
namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Services\SlipVerification;
use Illuminate\Http\Request;

class SlipVerificationController extends Controller
{
    public function show(Request $request, Payroll $payroll, string $token, SlipVerification $verifier)
    {
        // Tautan berisi token acak yang tidak dapat ditebak; slip tidak pernah diindeks.
        abort_unless($verifier->validToken($payroll, $token), 404);
        $valid = $verifier->isIntact($payroll);
        $data = $valid ? $payroll->calculation_snapshot : null;
        return response()->view('slips.verify', compact('payroll', 'valid', 'data'), 200)
            ->header('Cache-Control','private, no-store, no-cache, max-age=0')
            ->header('Pragma','no-cache')
            ->header('X-Robots-Tag','noindex, nofollow, noarchive')
            ->header('Referrer-Policy','no-referrer')
            ->header('X-Content-Type-Options','nosniff');
    }
}
