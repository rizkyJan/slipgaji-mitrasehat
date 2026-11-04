<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Masuk | Mitra Sehat Payroll</title><link rel="stylesheet" href="{{ asset('payroll.css') }}"></head>
<body style="min-height:100vh;background:radial-gradient(circle at 15% 15%,#1e4eb3 0,#142b54 38%,#0a1936 90%);display:grid;place-items:center;padding:20px">
    <div style="width:min(450px,100%);background:white;border-radius:22px;padding:36px;box-shadow:0 28px 100px #0005">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:25px"><span class="logo-mark logo-image"><img src="{{ asset('images/logo-klinik-mitra-sehat.png') }}" alt="Logo Mitra Sehat"></span><div><div style="font-size:17px;font-weight:800">Mitra Sehat</div><div style="font-size:10px;letter-spacing:2px;color:#7893be;font-weight:800">PAYROLL SYSTEM</div></div></div>
        <h1 style="font-size:26px;letter-spacing:-1px;margin:0">Masuk ke Payroll</h1><p class="muted" style="margin:6px 0 25px">Kelola gaji dan slip karyawan dengan aman.</p>
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
        <form action="{{ route('login.submit') }}" method="post">@csrf
            <div class="field" style="margin-bottom:18px"><label>Email administrator</label><input class="input" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus placeholder="admin@klinik.com"></div>
            <div class="field" style="margin-bottom:22px"><label>Password</label><input class="input" name="password" type="password" autocomplete="current-password" required placeholder="Password akun payroll"></div>
            <button class="btn btn-primary" style="width:100%;font-size:14px;min-height:46px">Masuk Sekarang →</button>
        </form>
        <p class="footnote" style="text-align:center;margin-top:26px">Akun dibuat administrator melalui terminal. Tidak ada pendaftaran publik.</p>
    </div>
</body></html>
