<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Dashboard') | Mitra Sehat Payroll</title>
    <link rel="stylesheet" href="{{ asset('payroll.css') }}">
    @stack('head')
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="logo" href="{{ route('dashboard') }}"><span class="logo-mark logo-image"><img src="{{ asset('images/logo-klinik-mitra-sehat.png') }}" alt="Logo Mitra Sehat"></span><span><span class="logo-name">Mitra Sehat</span><br><span class="logo-sub">PAYROLL SYSTEM</span></span></a>
        <div class="nav-caption">Menu Utama</div>
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon">◫</span> Dashboard</a>
        <a class="nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}" href="{{ route('employees.index') }}"><span class="nav-icon">♙</span> Data Karyawan</a>
        <a class="nav-link {{ request()->routeIs('payrolls.*') ? 'active' : '' }}" href="{{ route('payrolls.index') }}"><span class="nav-icon">▤</span> Penggajian</a>
        <a class="nav-link {{ request()->routeIs('doctors.*') ? 'active' : '' }}" href="{{ route('doctors.index') }}"><span class="nav-icon">⚕</span> Honor Dokter</a>
        <a class="nav-link {{ request()->routeIs('doctors.import.*') ? 'active' : '' }}" href="{{ route('doctors.import.index') }}"><span class="nav-icon">⇪</span> Import Excel Dokter</a>
        <a class="nav-link {{ request()->routeIs('deliveries.*') ? 'active' : '' }}" href="{{ route('deliveries.index') }}"><span class="nav-icon">✉</span> Pengiriman Email</a>
        <a class="nav-link {{ request()->routeIs('reports.*') && !request()->routeIs('reports.audit') ? 'active' : '' }}" href="{{ route('reports.index') }}"><span class="nav-icon">▥</span> Laporan & Riwayat</a>
        @can('manage-settings')
            <div class="nav-caption">Administrasi</div>
            <a class="nav-link {{ request()->routeIs('settings.*','google.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><span class="nav-icon">⚙</span> Pengaturan & BPJS</a>
            <a class="nav-link {{ request()->routeIs('reports.audit') ? 'active' : '' }}" href="{{ route('reports.audit') }}"><span class="nav-icon">◷</span> Log Aktivitas</a>
        @endcan
        <div class="side-bottom">
            <div class="user-card"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'admin' ? 'Administrator' : 'Payroll Staff' }}</small></span></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="side-exit" type="submit">↪ Keluar dari aplikasi</button></form>
        </div>
    </aside>
    <main class="main">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:13px"><button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Buka menu">☰</button><span class="topbar-title">@yield('title', 'Dashboard')</span></div>
            <div class="topbar-right"><span class="pill">● Sistem Payroll Internal</span><span style="color:#7893be;font-size:12px">{{ now()->locale('id')->translatedFormat('d M Y') }}</span></div>
        </header>
        <div class="content">
            @if(session('success'))<div class="alert alert-success">✓ {{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="alert alert-error"><strong>Ada isian yang perlu diperbaiki:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
