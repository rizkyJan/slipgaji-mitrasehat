@extends('layouts.app')
@section('title','Detail Honor Dokter')
@section('content')
@php $s=$p->calculation_snapshot;$doctor=$p->assignment->employee; @endphp
<div class="page-head"><div><p class="eyebrow">DETAIL PENGGAJIAN DOKTER</p><h1 class="page-title">{{ $doctor?->name }}</h1><p class="page-sub">{{ $p->assignment->branch }} · {{ $p->period }} · {{ $p->slip_number??'DRAFT (belum ada nomor sah)' }}</p></div><a class="btn btn-outline" href="{{ route('doctors.index') }}">Kembali</a></div>
<div class="card" style="margin-bottom:18px"><div class="card-pad" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
<span class="tag {{ $p->status==='approved'?'tag-green':'tag-amber' }}">{{ $p->status==='approved'?'Disetujui & Dikunci':'Draft' }}</span>
<a class="btn btn-outline" href="{{ route('doctors.pdf',$p) }}" target="_blank">Lihat PDF</a>
@if($p->status==='draft')<a class="btn btn-primary" href="{{ route('doctors.edit',$p) }}">Edit Draft</a>
@can('approve-payroll')<form method="post" action="{{ route('doctors.approve',$p) }}" onsubmit="return confirm('Setujui dan kunci honor dokter ini?')">@csrf<button class="btn btn-primary">Setujui & Terbitkan</button></form>@endcan
<form method="post" action="{{ route('doctors.delete',$p) }}" onsubmit="return confirm('Hapus draft dokter ini?')">@csrf @method('DELETE')<button class="btn btn-outline">Hapus Draft</button></form>
@else
@if(!$p->deliveries->contains('status','sent'))<form method="post" action="{{ route('doctors.send',$p) }}" onsubmit="return confirm('Kirim PDF honor dokter ke {{ $doctor?->email }}?')">@csrf<button class="btn btn-primary">Kirim via Gmail ke {{ $doctor?->email }}</button></form>@else<span class="tag tag-green">Sudah terkirim via Gmail</span>@endif
@endif</div></div>
<div class="kpis"><div class="kpi"><small>Total Penerimaan</small><b style="font-size:21px">Rp{{ number_format($p->gross_total,0,',','.') }}</b></div><div class="kpi"><small>Potongan</small><b style="font-size:21px">Rp{{ number_format($p->deduction_total,0,',','.') }}</b></div><div class="kpi"><small>Sudah Cash</small><b style="font-size:21px">Rp{{ number_format($p->cash_total,0,',','.') }}</b></div><div class="kpi"><small>Sisa Transfer</small><b style="font-size:21px">Rp{{ number_format($p->transfer_due,0,',','.') }}</b></div></div>
@if(count($s['warnings']??[]))<div class="alert alert-error">{{ implode(' ',$s['warnings']) }}</div>@endif
<div class="card" style="margin:18px 0"><div class="card-head"><h3>Rincian Hak Dokter</h3></div><div class="table-wrap"><table><thead><tr><th>Komponen</th><th class="money">Nominal</th></tr></thead><tbody>
@foreach($s['income'] as $label=>$amount)<tr><td>{{ $label }}</td><td class="money">Rp{{ number_format($amount,0,',','.') }}</td></tr>@endforeach
@foreach($s['deductions'] as $label=>$amount)<tr><td>Potongan: {{ $label }}</td><td class="money">(Rp{{ number_format($amount,0,',','.') }})</td></tr>@endforeach
<tr><td><b>HAK DOKTER (Cash + Transfer)</b></td><td class="money"><b>Rp{{ number_format($p->net_total,0,',','.') }}</b></td></tr></tbody></table></div></div>
<div class="card" style="margin-bottom:18px"><div class="card-head"><h3>Rincian Jadwal</h3></div><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Jadwal</th><th>Jam & Perhitungan</th><th>BPJS / Umum</th><th class="money">Transport</th><th class="money">Total Hari</th></tr></thead><tbody>@forelse($s['shifts'] as $r)<tr><td>{{ $r['date'] }}</td><td>{{ $r['type'] }}</td><td>{{ $r['start_time']??'-' }}–{{ $r['end_time']??'-' }}<br><small>{{ $r['hours'] }} / {{ $r['standard_hours'] }} jam penuh · Rp{{ number_format($r['full_rate']??0,0,',','.') }} / shift · {{ $r['transport_mode']??'lama' }}</small></td><td>{{ $r['bpjs'] }} / {{ $r['general'] }}</td><td class="money">Rp{{ number_format($r['transport'],0,',','.') }}</td><td class="money">Rp{{ number_format($r['total'],0,',','.') }}</td></tr>@empty<tr><td colspan="6">Tidak ada jadwal per kedatangan</td></tr>@endforelse</tbody></table></div></div>
<div class="card" style="margin-bottom:18px"><div class="card-head"><h3>Tindakan, Cash, dan Riwayat Email</h3></div><div class="card-pad">
<h4>Tindakan</h4>@forelse($s['actions'] as $a)<div style="padding:7px 0;border-bottom:1px solid #edf3fb">{{ $a['date'] }} · {{ $a['label'] }} ({{ $a['qty'] }}x) — <b>Rp{{ number_format($a['fee'],0,',','.') }}</b></div>@empty<p class="dr-help">Tidak ada tindakan tambahan</p>@endforelse
<h4 style="margin-top:18px">Cash Diambil</h4>@forelse($s['cash'] as $a)<div style="padding:7px 0">{{ $a['date'] }} — <b>Rp{{ number_format($a['amount'],0,',','.') }}</b> ({{ $a['note'] }})</div>@empty<p>Belum ada cash diambil</p>@endforelse
<h4 style="margin-top:18px">Pengiriman Email</h4>@forelse($p->deliveries as $mail)<div style="padding:7px 0">{{ $mail->created_at }} · {{ $mail->recipient_email }} · {{ $mail->status }}</div>@empty<p>Belum pernah dikirim</p>@endforelse
</div></div>
@endsection
