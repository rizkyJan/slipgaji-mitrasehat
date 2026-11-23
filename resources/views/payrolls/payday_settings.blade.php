@extends('layouts.app')
@section('title','Jadwal Gajian Semua Payroll')
@section('content')
<div class="page-head"><div><p class="eyebrow">PENGATURAN PAYROLL</p><h1 class="page-title">Jadwal Gajian Bersama</h1>
<p class="page-sub">Satu jadwal untuk karyawan dan dokter per periode. Tidak mengubah perhitungan gaji atau menandai pembayaran sudah diterima.</p></div></div>
<div class="two-cols equal"><div>
<div class="card card-pad"><h3 class="section-title">Atur Tanggal Gajian</h3>
<form method="get" action="{{ route('payday.index') }}"><div class="field"><label>Periode Gaji</label><input class="input" type="month" name="period" value="{{ $period }}" required></div><div class="form-actions"><button class="btn btn-secondary" type="submit">Tampilkan Periode</button></div></form>
<div class="alert alert-success" style="margin:15px 0">Periode kerja: <strong>{{ \Carbon\Carbon::parse($workDates[0])->locale('id')->translatedFormat('d F Y') }} – {{ \Carbon\Carbon::parse($workDates[1])->locale('id')->translatedFormat('d F Y') }}</strong></div>
<form method="post" action="{{ route('payday.save') }}">@csrf
<input type="hidden" name="period" value="{{ $period }}">
<div class="field"><label for="pay-date-global">Tanggal gajian {{ $period }}</label>
<input id="pay-date-global" class="input" type="date" name="pay_date" value="{{ old('pay_date',$payDate) }}" required></div>
@error('pay_date')<p class="form-note" style="color:#b91c1c">{{ $message }}</p>@enderror
<div class="form-actions"><button class="btn btn-primary" type="submit">Simpan Jadwal Semua Payroll</button></div>
</form><p class="form-note">Jadwal ini digunakan untuk penggajian karyawan dan sebagai tanggal bawaan saat membuat slip honor dokter baru. Draft dokter yang sudah mempunyai tanggal khusus tetap memakai tanggalnya. Slip yang telah disetujui tidak diubah.</p></div>
</div><div><div class="card card-pad"><h3 class="section-title">Riwayat Jadwal Per Periode</h3>
<div class="table-wrap"><table><thead><tr><th>Periode</th><th>Tanggal Gajian</th><th></th></tr></thead><tbody>
@forelse($saved as $setting)
@php $p=substr($setting->key,strlen(\App\Services\PayrollPayday::KEY_PREFIX)); @endphp
<tr><td>{{ $p }}</td><td>{{ $setting->value }}</td><td><a class="btn btn-outline btn-sm" href="{{ route('payday.index',['period'=>$p]) }}">Edit</a></td></tr>
@empty<tr><td colspan="3">Belum ada tanggal khusus tersimpan.</td></tr>@endforelse
</tbody></table></div><p class="form-note">Jika belum ada jadwal khusus, sistem menggunakan tanggal 26 atau pengaturan awal yang sudah tersedia.</p></div>
</div></div>
@endsection
