@extends('layouts.app')
@section('title','Penggajian Dokter')
@section('content')
<div class="page-head"><div><p class="eyebrow">MODUL KHUSUS DOKTER</p><h1 class="page-title">Honor & Slip Dokter</h1><p class="page-sub">Perhitungan per dokter, cabang, jadwal, pasien, tindakan, dan cash.</p></div><a href="{{ route('doctors.create',['period'=>$period]) }}" class="btn btn-primary">+ Buat Honor Dokter</a></div>
<div class="card"><div class="card-pad"><form method="get" style="display:flex;flex-wrap:wrap;gap:14px;align-items:end">
<div class="field"><label>Periode</label><input class="input" type="month" name="period" value="{{ $period }}"></div>
<div class="field"><label>Unit / Cabang</label><select class="input" name="branch"><option value="">Semua Unit</option>@foreach($branches as $b)<option @selected($branch===$b) value="{{ $b }}">{{ $b }}</option>@endforeach</select></div>
<div class="field"><label>Status</label><select class="input" name="status"><option value="">Semua</option><option value="draft" @selected($status==='draft')>Draft</option><option value="approved" @selected($status==='approved')>Disetujui</option></select></div>
<button class="btn btn-primary">Tampilkan</button><a class="btn btn-outline" href="{{ route('doctors.index') }}">Reset</a>
@can('manage-settings')<a class="btn btn-outline" href="{{ route('doctors.settings') }}">Aturan Dokter & Tindakan</a>@endcan
</form></div></div>
<div class="kpis" style="margin:18px 0"><div class="kpi"><small>Jumlah Slip</small><b>{{ number_format($stats->total_slips,0,',','.') }}</b></div><div class="kpi"><small>Draft</small><b>{{ $stats->draft_count }}</b></div><div class="kpi"><small>Disetujui</small><b>{{ $stats->approved_count }}</b></div><div class="kpi"><small>Total Hak Dokter</small><b style="font-size:20px">Rp{{ number_format($stats->net_sum,0,',','.') }}</b></div></div>
<div class="card"><div class="card-head"><div><h3>Daftar Honor Dokter</h3><p class="card-sub">Total cash Rp{{ number_format($stats->cash_sum,0,',','.') }} · Sisa transfer Rp{{ number_format($stats->transfer_sum,0,',','.') }}</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Dokter</th><th>Unit</th><th>Periode</th><th>Status</th><th class="money">Hak Dokter</th><th class="money">Sudah Cash</th><th class="money">Transfer</th><th>Aksi</th></tr></thead><tbody>
@forelse($payrolls as $p)<tr><td><strong>{{ $p->assignment?->employee?->name }}</strong><small style="display:block;color:#6b7d98">{{ $p->assignment?->profile_label }}</small></td><td>{{ $p->assignment?->branch }}</td><td>{{ $p->period }}</td><td><span class="tag {{ $p->status==='approved'?'tag-green':'tag-amber' }}">{{ $p->status==='approved'?'Disetujui':'Draft' }}</span></td><td class="money">Rp{{ number_format($p->net_total,0,',','.') }}</td><td class="money">Rp{{ number_format($p->cash_total,0,',','.') }}</td><td class="money"><strong>Rp{{ number_format($p->transfer_due,0,',','.') }}</strong></td><td><a href="{{ route('doctors.show',$p) }}" class="btn btn-secondary btn-sm">Detail</a></td></tr>
@empty<tr><td colspan="8" class="empty"><b>Belum ada slip dokter untuk filter ini.</b> Mulai dari tombol Buat Honor Dokter di atas.</td></tr>@endforelse
</tbody></table></div><div class="pagination">@include('partials.pagination',['paginator'=>$payrolls])</div></div>
@endsection
