@extends('layouts.app')
@section('title','Rincian Fee Harian Dokter')
@section('content')
@php
    $money = static fn($v) => 'Rp'.number_format((int)$v,0,',','.');
    $statusLabels=['pending'=>'Perlu konfirmasi','unpaid'=>'Belum diambil','partial'=>'Dibayar sebagian','paid'=>'Lunas'];
    $doctorName=$doctorPayroll->assignment?->employee?->name ?: '-';
@endphp
<div class="page-head"><div><p class="eyebrow">PENCATATAN FEE HARIAN</p><h1 class="page-title">{{ $doctorName }}</h1><p class="page-sub">{{ $doctorPayroll->assignment?->branch }} · Periode {{ $doctorPayroll->period }} · {{ $periodStart }} s.d. {{ $periodEnd }}</p></div><div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn btn-outline" href="{{ route('doctors.fees.index',['period'=>$doctorPayroll->period]) }}">Daftar Fee Harian</a><a class="btn btn-secondary" href="{{ route('doctors.show',$doctorPayroll) }}">Detail Honor</a><a class="btn btn-primary" href="{{ route('doctors.fees.pdf',$doctorPayroll) }}">Unduh Lampiran PDF</a></div></div>
<div class="alert" style="margin-bottom:14px;background:#fff8e8;border:1px solid #e7c882;color:#704d10;padding:14px;border-radius:12px"><b>Penting:</b> "Belum diambil" hanya muncul setelah nominal fee <b>dan status pembayarannya</b> diperiksa admin. Ini rekonsiliasi terpisah dari slip honor. Status <b>Perlu konfirmasi</b> tidak dianggap belum dibayar. Data cash/transfer di sini <b>tidak otomatis memotong sisa transfer pada slip utama</b>. Cocokkan dahulu agar pembayaran tidak berulang. Slip/QR yang telah disetujui tetap terkunci.</div>
<div class="kpis" style="margin-bottom:18px">
<div class="kpi"><small>Jadwal tercatat</small><b>{{ $totals['dates'] }}</b></div>
<div class="kpi"><small>Perlu konfirmasi</small><b>{{ $totals['pending'] }}</b></div>
<div class="kpi"><small>Cash terverifikasi</small><b style="font-size:18px">{{ $money($totals['cash']) }}</b></div>
<div class="kpi"><small>Transfer terverifikasi</small><b style="font-size:18px">{{ $money($totals['transfer']) }}</b></div>
<div class="kpi"><small>Belum diambil (terverifikasi)</small><b style="font-size:18px">{{ $money($totals['outstanding']) }}</b></div>
</div>
@can('manage-settings')
<div class="card" style="margin-bottom:18px"><div class="card-head"><div><h3>Tambahkan tanggal praktik</h3><p class="card-sub">Tanggal harus berada dalam periode kerja slip. Nominal dan pembayaran diisi setelah verifikasi.</p></div></div>
<div class="card-pad"><form action="{{ route('doctors.fees.add',$doctorPayroll) }}" method="post" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap">@csrf<div class="field"><label>Tanggal praktik</label><input class="input" type="date" name="work_date" min="{{ $periodStart }}" max="{{ $periodEnd }}" required></div><button class="btn btn-primary">+ Tambah tanggal</button></form>
@if(count($sourceDates)>0)<details style="margin-top:20px"><summary style="cursor:pointer;font-weight:700;color:#173862">Ambil tanggal dari arsip Import Excel ({{ count($sourceDates) }} tanggal belum dicatat)</summary>
<p style="font-size:13px;color:#566884">Hanya mengisi tanggal, bukan nominal atau status bayar. Daftar dari arsip import dokter dan periode yang cocok; bisa mencakup jadwal yang dahulu tidak dipilih.</p>
<form method="post" action="{{ route('doctors.fees.import',$doctorPayroll) }}">@csrf<div style="display:flex;gap:12px;flex-wrap:wrap;margin:12px 0">
@foreach($sourceDates as $sourceDate)<label style="border:1px solid #d5dff0;padding:9px 12px;border-radius:9px"><input type="checkbox" name="dates[]" value="{{ $sourceDate }}"> {{ $sourceDate }}</label>@endforeach
</div><button class="btn btn-secondary">Tambahkan tanggal yang dipilih sebagai perlu konfirmasi</button></form></details>@endif
</div></div>
@endcan
<div class="card"><div class="card-head"><div><h3>Rincian per tanggal</h3><p class="card-sub">Fee harian bukan hasil pembagian rata rekap bulanan. Konfirmasi nominal sesuai operan dan bukti pembayaran.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Tanggal praktik</th><th>Nominal fee</th><th>Sudah dibayar</th><th>Belum diambil</th><th>Status</th><th>Pemeriksaan / pembayaran</th></tr></thead><tbody>
@forelse($days as $day)
@php $status=$day->reconciliation_status; @endphp
<tr>
<td style="white-space:nowrap"><b>{{ $day->work_date->format('d/m/Y') }}</b><small style="display:block;color:#627590">{{ $day->source==='import'?'Dari arsip import':'Manual' }}</small></td>
<td>{{ $day->fee_amount===null?'—':$money($day->fee_amount) }}</td>
<td>{{ $money($day->paid_amount) }}</td>
<td><strong>{{ $day->remaining===null?'—':$money($day->remaining) }}</strong></td>
<td><span class="tag {{ $status==='paid'?'tag-green':'tag-amber' }}">{{ $statusLabels[$status] }}</span></td>
<td style="min-width:245px">
@if($day->note)<div style="font-size:12px;color:#52677e;max-width:280px">{{ $day->note }}</div>@endif
@if($day->activePayments->isNotEmpty())
<details style="margin:8px 0"><summary style="cursor:pointer;font-size:12px">Lihat {{ $day->activePayments->count() }} pembayaran</summary>
@foreach($day->activePayments as $payment)
<div style="margin:8px 0;border-left:3px solid #c7d8ee;padding:5px 8px;font-size:12px">
<b>{{ $payment->method==='cash'?'Cash':'Transfer' }} {{ $money($payment->amount) }}</b><br>{{ $payment->payment_date->format('d/m/Y') }}<br>{{ $payment->note }}
@can('manage-settings')
<details style="margin-top:6px"><summary style="cursor:pointer;color:#9b3225">Batalkan transaksi (dengan alasan)</summary>
<form action="{{ route('doctors.fees.void',[$doctorPayroll,$day->id,$payment->id]) }}" method="post">@csrf<input class="input" name="reason" required minlength="8" maxlength="500" placeholder="Alasan koreksi" style="margin:6px 0"><button class="btn btn-outline btn-sm" onclick="return confirm('Batalkan transaksi ini? Riwayat tetap disimpan.')">Batalkan</button></form></details>
@endcan
</div>
@endforeach
</details>
@endif
@can('manage-settings')
<details style="margin-top:8px"><summary style="cursor:pointer;font-weight:700;color:#0a4ca9">Edit fee & verifikasi</summary>
<form action="{{ route('doctors.fees.save',[$doctorPayroll,$day->id]) }}" method="post" style="margin:12px 0;display:grid;gap:7px">@csrf
<label style="font-size:12px">Fee tanggal ini (Rp)</label><input class="input" name="fee_amount" type="number" min="1" max="9999999999" value="{{ $day->fee_amount }}" placeholder="Nominal dari operan">
<input type="hidden" name="confirmed" value="0"><label style="font-size:13px"><input type="checkbox" name="confirmed" value="1" @checked($day->confirmed)> Nominal fee sudah diperiksa</label>
<input type="hidden" name="payment_checked" value="0"><label style="font-size:13px"><input type="checkbox" name="payment_checked" value="1" @checked($day->payment_checked)> Status cash/transfer sudah diperiksa (termasuk memastikan tidak ada pembayaran lain)</label>
<label style="font-size:12px">Catatan</label><input class="input" name="note" maxlength="500" value="{{ $day->note }}" placeholder="Sumber/bukti operan"><button class="btn btn-primary btn-sm">Simpan verifikasi</button></form></details>
@if($day->confirmed && $day->fee_amount !== null && ((int)$day->fee_amount - (int)$day->paid_amount)>0)
<details style="margin-top:10px"><summary style="cursor:pointer;font-weight:700;color:#0a4ca9">+ Catat pembayaran</summary>
<form action="{{ route('doctors.fees.payment',[$doctorPayroll,$day->id]) }}" method="post" style="margin:12px 0;display:grid;gap:7px">@csrf<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
<label style="font-size:12px">Tanggal pembayaran</label><input class="input" type="date" name="payment_date" min="{{ $day->work_date->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" value="{{ now()->format('Y-m-d') }}" required>
<label style="font-size:12px">Metode</label><select class="input" name="method" required><option value="cash">Cash</option><option value="transfer">Transfer</option></select>
<label style="font-size:12px">Nominal dibayar (Rp)</label><input class="input" type="number" name="amount" min="1" max="{{ (int)$day->fee_amount-(int)$day->paid_amount }}" required>
<label style="font-size:12px">Keterangan bukti (opsional)</label><input class="input" name="note" maxlength="500" placeholder="Misalnya: bukti transfer tanggal ...">
<button class="btn btn-primary btn-sm">Simpan pembayaran</button></form></details>
@endif
@endcan
</td>
</tr>
@empty
<tr><td colspan="6" class="empty"><b>Belum ada tanggal yang dicatat.</b> Tambahkan manual atau pilih dari arsip import yang tersedia.</td></tr>
@endforelse
</tbody></table></div></div>
<div style="margin-top:16px;padding:12px 0;color:#697b93;font-size:12px">Pembayaran yang sudah disetujui dalam slip utama dan riwayat fee harian perlu dicek bersama sebelum transfer agar tidak terjadi pembayaran ganda. Ledger di sini tidak menandai transfer sudah terjadi hanya karena tanggal gajian tiba.</div>
@endsection
