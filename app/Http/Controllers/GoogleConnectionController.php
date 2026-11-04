<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleConnectionController extends Controller
{
    public function connect(Request $request, GmailService $gmail)
    {
        $state = Str::random(64);
        $request->session()->put('payroll_google_oauth_state', $state);
        try {
            return redirect()->away($gmail->authorizationUrl($state));
        } catch (\RuntimeException $e) {
            $request->session()->forget('payroll_google_oauth_state');
            return redirect()->route('settings.index')->with('error', $e->getMessage());
        }
    }

    public function callback(Request $request, GmailService $gmail)
    {
        $expected = $request->session()->pull('payroll_google_oauth_state');
        if (!$expected || !is_string($request->query('state')) || !hash_equals($expected, $request->query('state'))) {
            return redirect()->route('settings.index')->with('error', 'Verifikasi keamanan Google gagal. Silakan hubungkan ulang.');
        }
        if ($request->query('error')) {
            return redirect()->route('settings.index')->with('error', 'Izin Google tidak diberikan: '.$request->query('error'));
        }
        $code = $request->query('code');
        if (!is_string($code) || $code === '') return redirect()->route('settings.index')->with('error', 'Kode otorisasi Google tidak ditemukan.');
        try {
            $connection = $gmail->connect($request->user(), $code);
            AuditLog::record('google.connected', $connection, ['email' => $connection->email]);
            return redirect()->route('settings.index')->with('success', 'Google terhubung sebagai '.$connection->email.'. Tidak perlu login ulang saat mengirim selama Google masih mengizinkan akses.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('settings.index')->with('error', $e->getMessage());
        }
    }

    public function disconnect(Request $request, GmailService $gmail)
    {
        $connection = $request->user()->googleConnection;
        if ($connection) {
            AuditLog::record('google.disconnected', $connection, ['email' => $connection->email]);
            $gmail->disconnect($connection);
        }
        return back()->with('success', 'Sambungan Google telah diputus.');
    }
}
