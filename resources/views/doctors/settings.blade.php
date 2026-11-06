@extends('layouts.app')
@section('title','Aturan Dokter')
@section('content')
<div class="page-head"><div><p class="eyebrow">ATURAN DINAMIS</p><h1 class="page-title">Profil Dokter & Daftar Tindakan</h1><p class="page-sub">Satu dokter dapat memiliki lebih dari satu profil untuk cabang berbeda.</p></div><a class="btn btn-primary" href="{{ route('doctors.assignments.create') }}">+ Buat Profil Dokter</a></div>
<div class="card" style="margin-bottom:20px"><div class="card-head"><h3>Profil Dokter per Cabang</h3></div><div class="table-wrap"><table><thead><tr><th>Dokter</th><th>Unit</th><th>Profil</th><th>Gaji Pokok</th><th>SIP / PJ</th><th>Status</th><th></th></tr></thead><tbody>@forelse($assignments as $a)<tr><td><strong>{{ $a->employee?->name }}</strong></td><td>{{ $a->branch }}</td><td>{{ $a->profile_label }}</td><td>Rp{{ number_format($a->base_salary,0,',','.') }}</td><td>Rp{{ number_format($a->sip_fee,0,',','.') }} / Rp{{ number_format($a->pj_fee,0,',','.') }}</td><td>{{ $a->active?'Aktif':'Nonaktif' }}</td><td><a class="btn btn-outline btn-sm" href="{{ route('doctors.assignments.edit',$a) }}">Edit</a></td></tr>@empty<tr><td colspan="7" class="empty">Belum ada profil dokter, buat profil pertama dari daftar karyawan.</td></tr>@endforelse</tbody></table></div></div>
<div class="card"><div class="card-head"><div><h3>Katalog Tindakan</h3><p class="card-sub">Tambah jenis tindakan sekali; saat menghitung honor cukup pilih dari dropdown.</p></div></div>
<div class="card-pad"><form method="post" action="{{ route('doctors.procedures.store') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:10px;align-items:end">@csrf
<div class="field"><label>Nama tindakan</label><input class="input" name="label" required placeholder="Nama tindakan"></div>
<div class="field"><label>Unit (opsional)</label><input class="input" name="branch" placeholder="Kosong = semua"></div>
<div class="field"><label>Metode fee</label><select class="input" name="mode"><option value="fixed">Fee tetap / tindakan</option><option value="percentage">Persentase setelah BHMP</option><option value="prosthesis">Protesa (BPJS + pasien - lab)</option></select></div>
<div class="field"><label>Fee tetap (Rp)</label><input class="input" type="number" min="0" name="fixed_fee" value="0" required></div>
<div class="field"><label>Persentase (%)</label><input class="input" type="number" step="0.01" min="0" max="100" name="percentage" value="35" required></div>
<button class="btn btn-primary" type="submit">+ Tambah Tindakan</button></form></div>
<div class="table-wrap"><table><thead><tr><th>Nama Tindakan</th><th>Unit</th><th>Metode</th><th class="money">Fee / %</th><th>Status & Ubah</th></tr></thead><tbody>@foreach($procedures as $proc)<tr>
<td>{{ $proc->label }}</td><td>{{ $proc->branch??'Semua' }}</td><td>{{ $proc->mode }}</td>
<td class="money">{{ $proc->mode==='fixed'?'Rp'.number_format($proc->fixed_fee,0,',','.'):number_format($proc->percentage,2,',','.').'%' }}</td>
<td style="min-width:240px"><span class="tag {{ $proc->active?'tag-green':'tag-amber' }}">{{ $proc->active?'Aktif':'Nonaktif' }}</span>
<details style="margin:6px 0"><summary style="cursor:pointer;color:#194aa6">Edit tindakan / tarif</summary>
<form method="post" action="{{ route('doctors.procedures.update',$proc) }}" style="display:grid;gap:7px;margin:12px 0">@csrf @method('PUT')
<label>Nama <input class="input" name="label" value="{{ $proc->label }}" required></label>
<label>Unit / Cabang (opsional) <input class="input" name="branch" value="{{ $proc->branch }}"></label>
<label>Metode <select class="input" name="mode"><option value="fixed" @selected($proc->mode==='fixed')>Fee tetap</option><option value="percentage" @selected($proc->mode==='percentage')>Persentase BHMP</option><option value="prosthesis" @selected($proc->mode==='prosthesis')>Protesa</option></select></label>
<label>Fee Tetap <input class="input" type="number" min="0" name="fixed_fee" value="{{ $proc->fixed_fee }}" required></label>
<label>Persentase <input class="input" type="number" min="0" max="100" step="0.01" name="percentage" value="{{ $proc->percentage }}" required></label>
<button class="btn btn-primary btn-sm">Simpan Tarif</button></form></details>
<form method="post" action="{{ route('doctors.procedures.status',$proc) }}" onsubmit="return confirm('Ubah status tindakan ini?')">@csrf<button type="submit" class="btn btn-outline btn-sm">{{ $proc->active?'Nonaktifkan':'Aktifkan' }}</button></form>
</td></tr>@endforeach</tbody></table></div></div>
@endsection
