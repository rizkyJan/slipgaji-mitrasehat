<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show() { return view('auth.login'); }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        $key = 'payroll-login:'.sha1(strtolower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Anda sudah keluar dari aplikasi. Sambungan Google tetap tersimpan sampai diputus sendiri.');
    }
}
