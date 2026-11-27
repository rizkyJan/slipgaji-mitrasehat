@extends('layouts.app')
@section('title','Pratinjau Fee Tidak Langsung dari Excel')
@section('content')
<div class="page-head"><div><p class="eyebrow">R4.28 · LAMPIRAN SAJA</p><h1 class="page-title">Ambil Fee Tidak Langsung dari Analisis Excel</h1><p class="page-sub">{{ $doctorPayroll->assignment->employee?->name }} · {{ $doctorPayroll->period }} · {{ $doctorPayroll->slip_number ?? 'Draft' }}</p></div><a class="btn btn-outline" href="{{ route('doctors.show',$doctorPayroll) }}">Kembali</a></div>
<div class="card" style="margin:18px 0"><div class="card-pad">
<p class="dr-help">Pilih arsip Excel yang berisi dokter dan periode kerja yang benar. Hanya membaca arsip yang sudah tersimpan; tidak mengubah Analisis Excel, perhitungan honor, PPh 21, cash, transfer, atau status persetujuan.</p>
@if($existingCount > 0)
 <div class="alert alert-error">Sudah ada {{ $existingCount }} baris Fee Tidak Langsung. Impor otomatis tidak boleh menimpanya. Periksa rincian yang ada di halaman Detail Honor terlebih dahulu.</div>
@endif
@if($errors->any())<div class="alert alert-error">{{ $errors->first() }}</div>@endif
@if(!$choices)<p class="dr-help">Belum ada arsip Analisis Excel pada periode ini. Upload lewat menu Import Excel Dokter terlebih dahulu, tanpa membuat slip baru.</p>@else
<form method="get" action="{{ route('doctors.transfer_excel.preview', $doctorPayroll) }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
 <div style="flex:1;min-width:300px"><label for="source_archive"><b>Sumber arsip Excel</b></label><select id="source_archive" onchange="const p=this.value.split('|');document.getElementById('batch_id').value=p.shift();document.getElementById('doctor').value=p.join('|');" required>
 <option value="">-- Pilih arsip dan nama dokter --</option>
 @foreach($choices as $c)
 <option value="{{ $c['batch_id'].'|'.$c['doctor'] }}" @selected($selectedBatch && $c['batch_id']===$selectedBatch->id && $c['doctor']===$selectedDoctor)>#{{ $c['batch_id'] }} · {{ $c['filename'] }} · {{ $c['doctor'] }} ({{ $c['count'] }} baris)</option>
 @endforeach
 </select><input type="hidden" name="batch_id" id="batch_id" value="{{ $selectedBatch?->id }}"><input type="hidden" name="doctor" id="doctor" value="{{ $selectedDoctor }}"></div>
 <button type="submit" class="btn btn-outline">Periksa Rincian</button>
</form>
@endif
</div></div>
@if($result)
<div class="card" style="margin-bottom:18px"><div class="card-head"><h3>Pratinjau dari Excel (belum disimpan)</h3></div><div class="card-pad">
 <p>Nama sumber: <b>{{ $selectedDoctor }}</b>. Nama di slip: <b>{{ $doctorPayroll->assignment->employee?->name }}</b>. Pastikan ini orang yang sama.</p>
 <p>Baris asli: <b>{{ $result['sourceCount'] }}</b> · Hasil per tanggal/sesi: <b>{{ count($result['rows']) }}</b></p>
 <p>Total pasien arsip: <b>BPJS {{ $result['sourceBpjs'] }} / Umum {{ $result['sourceUmum'] }}</b></p>
 <p>Total pasien berfee pada slip: <b>BPJS {{ $result['expectedBpjs'] }} / Umum {{ $result['expectedUmum'] }}</b></p>
 @if(!$result['totalsMatch'])<div class="alert alert-error">Perhatian: total pasien arsip tidak sama dengan jumlah pasien berfee pada slip. Bisa disebabkan aturan Poli 2, perbedaan data, atau arsip yang salah. Periksa sebelum menyetujui rincian.</div>@endif
 @if($result['errors'])<div class="alert alert-error"><b>Impor diblokir karena ada baris yang belum dapat dipastikan:</b><ul>@foreach($result['errors'] as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
 <div class="table-wrap"><table><thead><tr><th>Hari/Sesi</th><th>Tanggal</th><th>BPJS</th><th>Umum</th></tr></thead><tbody>
 @foreach($result['rows'] as $row)<tr><td>{{ strtoupper(\Carbon\Carbon::parse($row['date'])->locale('id')->translatedFormat('l')) }} {{ $row['session'] }}</td><td>{{ \Carbon\Carbon::parse($row['date'])->format('d-m-Y') }}</td><td>{{ $row['bpjs'] }}</td><td>{{ $row['umum'] }}</td></tr>@endforeach
 </tbody></table></div>
 @if(!$result['errors'] && $result['rows'] && !$existingCount)
 <form method="post" action="{{ route('doctors.transfer_excel.apply',$doctorPayroll) }}" style="margin-top:15px">@csrf
 <input type="hidden" name="batch_id" value="{{ $selectedBatch->id }}"><input type="hidden" name="doctor" value="{{ $selectedDoctor }}"><input type="hidden" name="fingerprint" value="{{ $result['fingerprint'] }}">
 <p><label><input type="checkbox" name="confirm_mapping" value="1" required> Saya sudah memastikan arsip ini milik dokter dan periode yang benar.</label></p>
 <p><label><input type="checkbox" name="confirm_counts" value="1" required> Saya sudah meninjau jumlah pasien dan perbedaan dengan pasien berfee pada slip, bila ada.</label></p>
 <button class="btn btn-primary" type="submit" onclick="return confirm('Salin rincian Excel ke tabel lampiran? Nominal honor tidak berubah.')">Gunakan Rincian Excel untuk Lampiran</button>
 </form>
 @endif
</div></div>
@endif
@endsection
