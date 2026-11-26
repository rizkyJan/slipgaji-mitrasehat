@extends('layouts.app')
@section('title','Rincian Fee Harian')
@section('content')
<div class="page-head"><div><p class="eyebrow">REKONSILIASI PEMBAYARAN DOKTER</p><h1 class="page-title">Rincian Fee Harian</h1><p class="page-sub">Periksa tanggal fee belum diambil, cash, dan transfer tanpa mengubah slip honor yang sudah ada.</p></div><a class="btn btn-outline" href="{{ route('doctors.index') }}">Honor Dokter</a></div>
<div class="card" style="margin-bottom:18px"><div class="card-pad"><form method="get" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap"><div class="field"><label>Periode gaji</label><input class="input" type="month" name="period" value="{{ $period }}"></div><button class="btn btn-primary">Tampilkan</button></form></div></div>
<div class="alert" style="margin-bottom:14px;background:#f3f6ff;border:1px solid #dbe6ff;color:#153462;padding:14px;border-radius:12px">Tanggal dari Excel hanya <b>referensi jadwal</b>. Status bayar dan nominal tidak diisi otomatis. Pembayaran dalam modul ini <b>belum mengubah Sisa Transfer pada slip utama</b>, sehingga harus direkonsiliasi sebelum pembayaran akhir.</div>
<div class="card"><div class="card-head"><div><h3>Dokter dan Slip Periode {{ $period }}</h3><p class="card-sub">Pilih dokter untuk melihat dan mengedit pencatatan fee per tanggal.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Dokter</th><th>Unit / Cabang</th><th>Periode</th><th>Status slip</th><th>Detail</th></tr></thead><tbody>
@forelse($payrolls as $p)
<tr><td><strong>{{ $p->assignment?->employee?->name ?: '-' }}</strong></td><td>{{ $p->assignment?->branch ?: '-' }}</td><td>{{ $p->period }}</td><td><span class="tag {{ $p->status==='approved'?'tag-green':'tag-amber' }}">{{ $p->status==='approved'?'Disetujui':'Draft' }}</span></td><td><a class="btn btn-secondary btn-sm" href="{{ route('doctors.fees.show',$p) }}">Rincian Fee Harian</a></td></tr>
@empty<tr><td colspan="5" class="empty">Belum ada slip honor dokter pada periode ini.</td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $payrolls->links() }}</div></div>
@endsection
