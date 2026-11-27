@extends('layouts.app')
@section('title','Pengaturan Rentang Periode Kerja')
@section('content')
<div class="page-head">
    <div><p class="eyebrow">PENGATURAN PAYROLL</p><h1 class="page-title">Rentang Periode Kerja</h1>
    <p class="page-sub">Atur tanggal kerja berbeda untuk dokter dan karyawan pada bulan penggajian yang dipilih.</p></div>
    <div class="actions"><a class="btn btn-outline" href="{{ route('doctors.index') }}">Honor Dokter</a><a class="btn btn-outline" href="{{ route('payrolls.index') }}">Penggajian Karyawan</a></div>
</div>
<div class="card" style="margin-bottom:20px"><div class="card-pad">
    <form method="get" action="{{ route('work-periods.index') }}" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px">
        <label style="display:flex;flex-direction:column;gap:5px">Periode penggajian (bulan)
        <input type="month" name="period" value="{{ $period }}" required style="padding:9px;border:1px solid #cbd5e1;border-radius:8px"></label>
        <button class="btn btn-outline" type="submit">Pilih Periode</button>
    </form>
    <p class="muted" style="margin-top:10px">Tanpa pengaturan khusus, slip tetap memakai periode {{ \App\Services\PayrollWorkPeriod::nice($from) }} – {{ \App\Services\PayrollWorkPeriod::nice($to) }}. Pengaturan ini berlaku pada slip <strong>{{ $period }}</strong> milik orang/unit yang dipilih, termasuk slip yang sudah dibuat. Tidak otomatis mengubah bulan penggajian lainnya.</p>
</div></div>
<form method="post" action="{{ route('work-periods.bulk') }}" onsubmit="return confirm('Terapkan rentang kerja yang sama untuk seluruh dokter/karyawan yang dicentang pada periode ini?');">
@csrf
<input type="hidden" name="period" value="{{ $period }}">
<div class="card" style="margin-bottom:20px"><div class="card-head"><h3>Tanggal yang Akan Diterapkan</h3></div><div class="card-pad">
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:end">
        <label style="display:flex;flex-direction:column;gap:5px">Dari tanggal<input type="date" name="work_from" value="{{ old('work_from',$from) }}" required style="padding:9px;border:1px solid #cbd5e1;border-radius:8px"></label>
        <label style="display:flex;flex-direction:column;gap:5px">Sampai tanggal<input type="date" name="work_to" value="{{ old('work_to',$to) }}" required style="padding:9px;border:1px solid #cbd5e1;border-radius:8px"></label>
        <button class="btn btn-primary" type="submit">Terapkan ke yang Dipilih</button>
    </div>
    @if($errors->any())<div class="alert alert-error" style="margin-top:10px">{{ $errors->first() }}</div>@endif
    <p class="muted" style="margin-top:10px">Pilih orang/unit di bawah ini. Nominal honor dan gaji tidak akan dihitung ulang. Untuk dokter yang bertugas di lebih dari satu unit, setiap penempatan dapat dipilih sendiri.</p>
</div></div>
<div class="card" style="margin-bottom:20px"><div class="card-head"><h3>Pilih Dokter</h3></div><div class="card-pad">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:8px">
    @forelse($doctors as $doctor)
        @php $existing = $overrides->get('doctor:'.$doctor->id); @endphp
        <label style="display:flex;gap:8px;align-items:start;padding:10px;border:1px solid #e2e8f0;border-radius:8px">
            <input type="checkbox" name="targets[]" value="doctor:{{ $doctor->id }}" @checked(in_array('doctor:'.$doctor->id, old('targets',[])))>
            <span><strong>{{ $doctor->employee?->name ?: 'Dokter #'.$doctor->id }}</strong><br><small>{{ $doctor->branch }}</small>
            @if($existing)<br><small style="color:#0f766e">Sudah diatur: {{ $existing->work_from }} s.d. {{ $existing->work_to }}</small>@endif</span>
        </label>
    @empty<p>Belum ada dokter.</p>@endforelse
    </div>
</div></div>
<div class="card" style="margin-bottom:20px"><div class="card-head"><h3>Pilih Karyawan</h3></div><div class="card-pad">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:8px">
    @forelse($employees as $employee)
        @php $existing = $overrides->get('employee:'.$employee->id); @endphp
        <label style="display:flex;gap:8px;align-items:start;padding:10px;border:1px solid #e2e8f0;border-radius:8px">
            <input type="checkbox" name="targets[]" value="employee:{{ $employee->id }}" @checked(in_array('employee:'.$employee->id, old('targets',[])))>
            <span><strong>{{ $employee->name }}</strong><br><small>{{ $employee->branch ?: '-' }} · {{ $employee->employee_code }}</small>
            @if($existing)<br><small style="color:#0f766e">Sudah diatur: {{ $existing->work_from }} s.d. {{ $existing->work_to }}</small>@endif</span>
        </label>
    @empty<p>Belum ada karyawan.</p>@endforelse
    </div>
</div></div>
<button class="btn btn-primary" type="submit">Simpan ke Semua yang Dicentang</button>
</form>
@endsection
