@extends('layouts.app')
@section('title', 'Rekap Import Honor Dokter')
@push('head')
<style>
.im-card{margin-bottom:16px}.im-soft{background:#f5f8fd;border:1px solid #dfebfb;border-radius:12px;padding:14px}
.im-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.im-metric{border:1px solid #dce7f7;background:#f6f9ff;border-radius:12px;padding:15px}.im-metric strong{display:block;font-size:26px;color:#153c80}.im-metric small{color:#617795}
.im-note{font-size:13px;color:#617795;line-height:1.65}.im-compact{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:12px}
.im-compact .field{min-width:0}.im-compact .input{width:100%}.im-details{border:1px solid #dce7f7;border-radius:13px;margin-bottom:16px;overflow:hidden;background:white}.im-details>summary{padding:17px 20px;cursor:pointer;font-weight:750;color:#153c80;background:#f7faff}.im-details[open]>summary{border-bottom:1px solid #e4edf9}
.im-procedure{border:1px solid #e0e9f6;border-radius:12px;padding:15px;background:#fbfdff;margin-bottom:10px}.im-procedure header{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px}.im-procedure small{font-size:12px;color:#5b7295}
.im-guide{padding:13px 15px;margin:12px 0;border:1px solid #c8daf8;border-radius:12px;background:#eef5ff;color:#234573;font-size:14px;line-height:1.65}.im-warning{color:#a54b11;font-weight:700}.im-good{color:#185a3b;font-weight:700}
.im-calc{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:14px 0}.im-calc>div{border:1px solid #e0e9f6;border-radius:10px;padding:13px;background:#fbfdff}.im-calc small{font-size:12px;color:#677e9f;display:block}.im-calc strong{font-size:20px;color:#153c80;display:block;margin-top:5px}
@media(max-width:700px){.im-calc{grid-template-columns:1fr}}
@media(max-width:660px){.im-metrics{grid-template-columns:1fr}.im-metric strong{font-size:22px}}
</style>
@endpush
@section('content')
<div class="page-head">
  <div>
    <p class="eyebrow">HONOR DOKTER · IMPORT OTOMATIS</p>
    <h1 class="page-title">Rekap Honor 1 Periode</h1>
    <p class="page-sub">{{ $batch->original_filename }} · {{ $batch->row_count }} jadwal dari seluruh sheet · Data belum menjadi slip.</p>
  </div>
  <a href="{{ route('doctors.import.index') }}" class="btn btn-outline">Import Lainnya</a>
</div>
@if($errors->any())
<div class="alert alert-error" style="margin-bottom:16px"><strong>Periksa isian sebelum membuat draft:</strong>
<ul style="margin:8px 0 0 18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="card im-card"><div class="card-pad">
<form method="GET" action="{{ route('doctors.import.review',$batch) }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
 <div class="field" style="flex:1;min-width:290px"><label>Pilih periode penggajian (26–25) dan dokter</label>
 <select name="group" class="input">@foreach($groups as $key=>$g)
 <option value="{{ $key }}" @selected($group===$key)>{{ $g['period'] }} · {{ $g['doctor'] }} · {{ $g['count'] }}× praktik {{ $g['issues']?'· '.$g['issues'].' catatan':'' }}</option>
 @endforeach</select></div>
 <button class="btn btn-primary" type="submit">Tampilkan Rekap</button>
</form>
</div></div>
<div class="card im-card"><div class="card-head"><div><h3>1. Rekap Bulanan {{ $period }}</h3><p class="card-sub">26 bulan sebelumnya sampai 25 bulan ini · {{ \Carbon\Carbon::parse($periodFrom)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($periodTo)->format('d/m/Y') }}</p></div></div>
 <div class="card-pad">
 <div class="im-guide"><strong>Aturan Poli 2 per kedatangan:</strong> total pasien BPJS + umum dipakai untuk menentukan kuota <strong>20 pasien yang sudah termasuk transport</strong>. Setelah itu, <strong>fee BPJS dan fee umum dihitung terpisah</strong>.<br>
 <strong>Contoh:</strong> 25 pasien BPJS + 3 pasien umum = 28 pasien; 20 masuk kuota → <strong>5 BPJS berfee + 3 umum berfee = 8 pasien berfee</strong>.<br>
 <strong>Asumsi import otomatis:</strong> kuota dialokasikan ke BPJS terlebih dahulu, sesuai contoh di atas. Jika urutan atau pembagian sebenarnya berbeda, koreksi pada rincian di bagian 3 sebelum membuat draft. Shift selain Poli 2 tetap menghitung BPJS dan umum dari pasien pertama.</div>
 <div class="im-metrics" style="margin-bottom:14px" aria-live="polite">
 <div class="im-metric"><small>Jumlah kehadiran</small><strong id="im-total-shifts">{{ $totals['shifts'] }}×</strong></div>
 <div class="im-metric"><small>BPJS tercatat (sebulan)</small><strong id="im-total-bpjs-raw">{{ number_format($totals['bpjs'],0,',','.') }}</strong></div>
 <div class="im-metric"><small>Umum tercatat (sebulan)</small><strong id="im-total-umum-raw">{{ number_format($totals['umum'],0,',','.') }}</strong></div>
 <div class="im-metric"><small><strong>BPJS berfee</strong> setelah aturan Poli 2</small><strong id="im-total-bpjs-paid">Menghitung...</strong></div>
 <div class="im-metric"><small><strong>Umum berfee</strong> setelah aturan Poli 2</small><strong id="im-total-umum-paid">Menghitung...</strong></div>
 <div class="im-metric"><small>Pasien Poli 2 termasuk transport</small><strong id="im-total-poli-included">Menghitung...</strong></div>
 </div>
 <div class="table-wrap"><table style="min-width:1010px"><thead><tr><th>Jenis jadwal</th><th style="text-align:right">Hadir (×)</th><th style="text-align:right">BPJS tercatat</th><th style="text-align:right">BPJS berfee</th><th style="text-align:right">Umum tercatat</th><th style="text-align:right">Umum berfee</th><th style="text-align:right">Termasuk Poli 2</th><th style="text-align:right">Transport (Rp)*</th></tr></thead>
 <tbody id="im-monthly-summary">
 @foreach($monthly as $type=>$r)<tr><td>{{ config('doctor_payroll.shift_labels.'.$type,$type==='unmapped'?'Belum dipetakan':$type) }}</td><td style="text-align:right">{{ $r['shifts'] }}×</td><td style="text-align:right">{{ number_format($r['bpjs'],0,',','.') }}</td><td style="text-align:right">Menghitung...</td><td style="text-align:right">{{ number_format($r['umum'],0,',','.') }}</td><td style="text-align:right">Menghitung...</td><td style="text-align:right">Menghitung...</td><td style="text-align:right">Pilih profil</td></tr>@endforeach
 </tbody>
 <tfoot id="im-monthly-total"></tfoot></table></div>
 <div class="im-calc" aria-live="polite">
  <div><small>Estimasi transport (sesuai jam praktik)</small><strong id="im-calc-transport">Pilih profil</strong></div>
  <div><small>Estimasi honor pasien BPJS saja</small><strong id="im-calc-bpjs">Pilih profil</strong></div>
  <div><small>Estimasi honor pasien umum saja</small><strong id="im-calc-umum">Pilih profil</strong></div>
  <div><small>Shift diprorata / toleransi 60 menit</small><strong id="im-calc-partial">Pilih profil</strong></div>
 </div>
 <p class="im-note" id="im-calc-note">*Pilih profil dokter di bagian 2 untuk nominal transport dan tarif pasien. Fee pasien tidak dibagi berdasarkan jam; hanya transport yang dapat diprorata.</p>
 <p class="im-note" style="margin:12px 0 0">Rekap otomatis digabung dari @foreach($sheetCounts as $s=>$n)<strong>{{ $s }} ({{ $n }} jadwal)</strong>{{ !$loop->last?' + ':'' }}@endforeach. Kolom <strong>tercatat</strong> adalah jumlah asli dari Excel, sedangkan kolom <strong>berfee</strong> adalah jumlah yang diperhitungkan untuk honor. Angka berubah saat rincian dikoreksi. Perhitungan final dilakukan ulang oleh server saat draft dibuat.</p>
 </div></div>
@if($issueCount)
<div class="alert alert-error" style="margin-bottom:16px"><strong>{{ $issueCount }} jadwal perlu diperiksa:</strong> buka bagian <em>Periksa / koreksi rincian jadwal</em> di bawah untuk mengecek jam, jenis shift, tanda POTONGAN, dan pembagian Poli 2 sebelum membuat draft.</div>
@endif
@if($incomplete)
<div class="alert alert-error" style="margin-bottom:16px"><strong>Periksa kelengkapan file:</strong> terdapat {{ count($missingDays) }} tanggal tanpa jadwal dokter apa pun dalam periode 26–25{{ $missingDaysLabel ? ' (contoh: '.$missingDaysLabel.')' : '' }}. Ini indikasi yang harus dikonfirmasi, bukan otomatis berarti ada jadwal yang hilang.</div>
@endif
<form method="POST" action="{{ route('doctors.import.generate',$batch) }}" id="im-generate">
@csrf
<input type="hidden" name="group" value="{{ $group }}">
<div class="card im-card"><div class="card-head"><h3>2. Dokter dan Unit/Cabang</h3></div><div class="card-pad">
 <div class="field"><label>Profil tarif untuk perhitungan honor</label><select name="doctor_assignment_id" id="im-assignment" class="input" required>
  <option value="">-- Pilih dokter & unit yang BENAR --</option>
  @foreach($assignments as $a)<option value="{{ $a->id }}" @selected((string)old('doctor_assignment_id')===(string)$a->id)>{{ $a->employee?->name }} — {{ $a->branch }} ({{ $a->profile_label }})</option>@endforeach
 </select></div>
 <p class="im-note" style="margin:10px 0 0">Excel tidak menjelaskan unit/cabang. Pastikan profil di atas cocok dengan dokter {{ explode('|',$group,2)[1]??'' }}. Tarif otomatis diambil dari profil ini. <strong>Pratinjau rekap di bagian 1 akan diperbarui setelah profil dipilih.</strong></p>
</div></div>
<details class="im-details" @if($errors->any()) open @endif>
<summary>3. Periksa / koreksi rincian jadwal ({{ count($filtered) }} baris) @if($issueCount) · {{ $issueCount }} catatan perlu perhatian @endif</summary>
<div class="card-pad"><p class="im-note" style="margin:0 0 12px">Detail tanggal hanya untuk verifikasi. <strong>Draft yang dihasilkan tetap mode Rekap Bulanan</strong> (jumlah × tarif), bukan daftar per hari. Jadwal salah bisa dikoreksi atau tidak dicentang.</p></div>
<div class="table-wrap"><table style="min-width:1340px"><thead><tr><th>Pakai</th><th>Asal</th><th>Tanggal</th><th>Jenis Shift</th><th>Mulai</th><th>Selesai</th><th>BPJS tercatat</th><th>Umum tercatat</th><th>BPJS dan Umum berfee (otomatis)</th><th>Koreksi BPJS berfee</th><th>Koreksi Umum berfee</th></tr></thead>
<tbody>
@foreach($filtered as $r)
@php($i=$r['id'])
<tr data-im-row style="vertical-align:top">
<td><input data-im-include type="checkbox" name="rows[{{ $i }}][include]" value="1" @checked(!is_array(old('rows')) || (string)old('rows.'.$i.'.include')==='1') aria-label="Gunakan baris {{ $r['source_row'] }}"></td>
<td><strong>{{ $r['sheet'] }}</strong><small style="display:block;color:#7183a3">Baris {{ $r['source_row'] }}</small>
@foreach(($r['issues']??[]) as $msg)<small style="display:block;color:#ad590b;margin-top:5px">⚠ {{ $msg }}</small>@endforeach
</td>
<td><input class="input" style="min-width:150px" type="date" name="rows[{{ $i }}][date]" value="{{ old('rows.'.$i.'.date',$r['date']) }}" min="{{ $periodFrom }}" max="{{ $periodTo }}"></td>
<td><select data-im-shift class="input" name="rows[{{ $i }}][shift_type]" style="min-width:132px"><option value="">Pilih</option>@foreach(config('doctor_payroll.shift_labels') as $key=>$label)<option value="{{ $key }}" @selected(old('rows.'.$i.'.shift_type',$r['shift_type'])===$key)>{{ $label }}</option>@endforeach</select><small style="display:block;color:#7183a3">Excel: {{ $r['shift_label'] }}</small></td>
<td><input class="input" style="min-width:105px" type="time" data-im-start name="rows[{{ $i }}][start_time]" value="{{ old('rows.'.$i.'.start_time',$r['start_time']) }}"></td>
<td><input class="input" style="min-width:105px" type="time" data-im-end name="rows[{{ $i }}][end_time]" value="{{ old('rows.'.$i.'.end_time',$r['end_time']) }}"></td>
<td><input data-im-bpjs class="input" style="width:83px" type="number" min="0" max="1000" name="rows[{{ $i }}][bpjs_count]" value="{{ old('rows.'.$i.'.bpjs_count',$r['bpjs']) }}"></td>
<td><input data-im-umum class="input" style="width:83px" type="number" min="0" max="1000" name="rows[{{ $i }}][general_count]" value="{{ old('rows.'.$i.'.general_count',$r['umum']) }}"></td>
<td style="min-width:150px"><strong data-im-row-paid>Menunggu</strong><small data-im-row-transport style="display:block;color:#677e9f"></small></td>
<td><input data-im-excess-bpjs class="input" style="width:90px" type="number" min="0" max="1000" name="rows[{{ $i }}][bpjs_excess]" value="{{ old('rows.'.$i.'.bpjs_excess') }}" placeholder="Otomatis"></td>
<td><input data-im-excess-umum class="input" style="width:90px" type="number" min="0" max="1000" name="rows[{{ $i }}][general_excess]" value="{{ old('rows.'.$i.'.general_excess') }}" placeholder="Otomatis"></td>
</tr>
@endforeach
</tbody></table></div>
<div class="card-pad"><div class="im-soft im-note"><strong>Poli 2 campuran dihitung otomatis:</strong> kosongkan kedua kolom <em>Koreksi BPJS berfee</em> dan <em>Koreksi Umum berfee</em> untuk memakai asumsi kuota BPJS lebih dahulu. Contoh <strong>25 BPJS + 3 umum = 5 BPJS berfee + 3 umum berfee</strong>. Jika catatan pelayanan menunjukkan pembagian berbeda, isi <strong>kedua kolom</strong> dengan angka sebenarnya; totalnya harus sama dengan jumlah pasien di atas kuota. <strong>Jangan menggunakan hasil otomatis tanpa mengecek catatan asli.</strong></div></div>
</details>
<div class="card im-card"><div class="card-head"><div><h3>4. Layanan Tambahan Sebulan (opsional)</h3><p class="card-sub">Isi angka bila layanan tidak tercantum di kolom pasien pada Excel.</p></div></div><div class="card-pad im-compact">
<div class="field"><label>Pasien USG (×)</label><input class="input" name="services[usg]" type="number" min="0" max="100000" value="{{ old('services.usg',0) }}"></div>
<div class="field"><label>Homecare motor klinik (×)</label><input class="input" name="services[homecare_clinic]" type="number" min="0" max="100000" value="{{ old('services.homecare_clinic',0) }}"></div>
<div class="field"><label>Homecare motor sendiri (×)</label><input class="input" name="services[homecare_own]" type="number" min="0" max="100000" value="{{ old('services.homecare_own',0) }}"></div>
</div></div>
<div class="card im-card"><div class="card-head"><div><h3>5. Tindakan Medis / Protesa (× jumlah)</h3><p class="card-sub">Tambahkan tindakan jika memang tercatat dalam nota terpisah. Tidak tersedia pada Excel contoh.</p></div></div><div class="card-pad">
<div id="im-procedures"></div>
<div style="display:flex;gap:9px;flex-wrap:wrap"><button id="im-add-procedure" type="button" class="btn btn-outline">+ Tambah Tindakan</button><a class="btn btn-outline" href="{{ route('doctors.settings') }}">Katalog Tarif Tindakan</a></div>
<p class="im-note" style="margin:12px 0 0">Honor tindakan mengikuti katalog (nominal tetap, persentase setelah biaya, atau protesa). Isi dasar perhitungan dan jumlah tindakan dengan benar. Bagian ini opsional; bisa disunting lagi pada draft rekap.</p>
</div></div>
<div class="card im-card"><div class="card-head"><h3>6. Periksa lalu Buat Draft Rekap</h3></div><div class="card-pad" style="display:grid;gap:12px">
 <label><input type="checkbox" name="confirm_mapping" value="1" required> Saya sudah mencocokkan dokter, cabang, dan profil tarif.</label>
 <label><input type="checkbox" name="confirm_review" value="1" required> Saya sudah memeriksa data 26–25, koreksi jam/pasien, <strong>pembagian BPJS/umum Poli 2 otomatis</strong> dengan catatan asli, dan tindakan.</label>
 <p class="im-note" style="margin:0"><strong>Hasilnya 1 draft honor bulanan per dokter/unit</strong>, bukan slip per tanggal. Tidak langsung disetujui atau dikirim Gmail. Setelah draft terbentuk, periksa ulang SIP/PJ, cash dan potongan sebelum persetujuan.</p>
 <div><button type="submit" class="btn btn-primary">Buat Draft Rekap Bulanan</button></div>
</div></div>
</form>
<template id="im-procedure-template">
<div class="im-procedure">
<header><b>Tindakan #__NO__</b><button type="button" class="btn btn-outline" data-remove-procedure>Hapus</button></header>
<div class="im-compact">
<div class="field" style="grid-column:1/-1"><label>Nama tindakan</label><select class="input" name="actions[__IDX__][procedure_id]" required>
<option value="">-- Pilih tindakan dari katalog --</option>
@foreach($procedures as $proc)<option value="{{ $proc->id }}" data-branch="{{ $proc->branch }}" data-mode="{{ $proc->mode }}" data-fixed="{{ $proc->fixed_fee }}" data-pct="{{ $proc->percentage }}">{{ $proc->label }}{{ $proc->branch?' · '.$proc->branch:'' }} ({{ $proc->mode }})</option>@endforeach
</select></div>
<div class="field"><label>Jumlah tindakan (×)</label><input class="input" type="number" name="actions[__IDX__][qty]" min="1" max="1000" value="1" required></div>
<div class="field"><label>Fee tetap / unit (Rp)</label><input class="input" type="number" name="actions[__IDX__][fixed_fee]" min="0" max="999999999" placeholder="Dari katalog"></div>
<div class="field"><label>Persentase fee (%)</label><input class="input" type="number" name="actions[__IDX__][percentage]" min="0" max="100" step="0.01" placeholder="Dari katalog"></div>
<div class="field"><label>Tarif/pendapatan tindakan (Rp)</label><input class="input" type="number" name="actions[__IDX__][price]" min="0" max="999999999" value="0" required></div>
<div class="field"><label>Biaya bahan/lab (Rp)</label><input class="input" type="number" name="actions[__IDX__][material]" min="0" max="999999999" value="0" required></div>
<div class="field"><label>Klaim BPJS (Rp)</label><input class="input" type="number" name="actions[__IDX__][claim]" min="0" max="999999999" value="0" required></div>
<div class="field"><label>Bayar pasien (Rp)</label><input class="input" type="number" name="actions[__IDX__][patient]" min="0" max="999999999" value="0" required></div>
</div><small>Fee tetap memakai jumlah × fee/unit; persentase memakai (pendapatan − bahan) × persentase; protesa mengikuti katalog.</small>
</div>
</template>
@push('scripts')
<script>
(()=>{
const labels=@json(config('doctor_payroll.shift_labels'));
@php($previewProfiles=$assignments->mapWithKeys(fn($a)=>[(string)$a->id=>($a->rules?:[])])->all())
const rateProfiles=@json($previewProfiles);
const fmt=n=>Number(n||0).toLocaleString('id-ID');
const money=n=>'Rp'+fmt(Math.round(n));
const summary=document.getElementById('im-monthly-summary');
const rowEls=Array.from(document.querySelectorAll('[data-im-row]'));
const assignSelect=document.getElementById('im-assignment');
const nval=(v)=>Math.max(0,Number(v)||0);
const duration=(start,end)=>{
 if(!start&&!end)return null;
 if(!start||!end)return NaN;
 const a=start.split(':').map(Number),b=end.split(':').map(Number);
 if(a.length!==2||b.length!==2||a.some(x=>!Number.isFinite(x))||b.some(x=>!Number.isFinite(x)))return NaN;
 let minutes=b[0]*60+b[1]-a[0]*60-a[1];
 if(minutes<=0)minutes+=1440;
 return minutes/60;
};
function syncSummary(){
 const rules=rateProfiles[String(assignSelect.value)]||null;
 const mode=rules?.patient_mode||'all';
 const quota=nval(rules?.poli2_included??20);
 const groups=new Map();
 let shifts=0,rawBpjs=0,rawUmum=0,paidBpjs=0,paidUmum=0,poliIncluded=0;
 let transportTotal=0,partial=0,withinGrace=0,pending=0,badHours=0;
 for(const row of rowEls){
  const checked=row.querySelector('[data-im-include]').checked;
  const paidElement=row.querySelector('[data-im-row-paid]');
  const transportElement=row.querySelector('[data-im-row-transport]');
  if(!checked){paidElement.textContent='Tidak dipakai';transportElement.textContent='';continue;}
  const type=row.querySelector('[data-im-shift]').value||'unmapped';
  const bpjs=nval(row.querySelector('[data-im-bpjs]').value);
  const umum=nval(row.querySelector('[data-im-umum]').value);
  const raw=bpjs+umum;
  const x=groups.get(type)||{n:0,b:0,u:0,paidB:0,paidU:0,included:0,transport:0,pending:0,invalid:0};
  x.n++;x.b+=bpjs;x.u+=umum;shifts++;rawBpjs+=bpjs;rawUmum+=umum;
  let pB=0,pU=0,unresolved=false,auto=true;
  if(type==='poli2'){
   const includedHere=Math.min(raw,quota);
   x.included+=includedHere;poliIncluded+=includedHere;
  }
  if(type==='unmapped'){
   unresolved=true;
   paidElement.textContent='Pilih jenis shift';
  } else if(mode==='none'){
   paidElement.textContent='0 berfee (profil tanpa fee pasien)';
  } else if(type==='poli2'){
   const excess=Math.max(0,raw-quota);
   if(excess>0){
    if(bpjs===0)pU=excess;
    else if(umum===0)pB=excess;
    else {
     const btxt=row.querySelector('[data-im-excess-bpjs]').value.trim();
     const utxt=row.querySelector('[data-im-excess-umum]').value.trim();
     if(btxt===''&&utxt===''){
      // Alokasi default kuota: BPJS lebih dahulu, lalu umum.
      // 25 BPJS + 3 umum, kuota 20 => 5 BPJS + 3 umum berfee.
      const bIncluded=Math.min(bpjs,quota);
      pB=bpjs-bIncluded;
      pU=Math.max(0,umum-Math.max(0,quota-bIncluded));
     } else {
      auto=false;
      const xb=Number(btxt),xu=Number(utxt);
      if(btxt===''||utxt===''||!Number.isInteger(xb)||!Number.isInteger(xu)||xb<0||xu<0||xb+xu!==excess||xb>bpjs||xu>umum)unresolved=true;
      else {pB=xb;pU=xu;}
     }
    }
   }
   if(unresolved)paidElement.textContent='Periksa koreksi BPJS + umum: harus '+fmt(excess)+' pasien berfee';
   else paidElement.textContent='BPJS '+fmt(pB)+' + umum '+fmt(pU)+' berfee'+(auto?' (otomatis)':' (dikoreksi)');
  } else {
   pB=bpjs;pU=umum;
   paidElement.textContent='BPJS '+fmt(pB)+' + umum '+fmt(pU)+' berfee';
  }
  if(unresolved){pending++;x.pending++;paidElement.className='im-warning';}
  else {paidElement.className='im-good';x.paidB+=pB;x.paidU+=pU;paidBpjs+=pB;paidUmum+=pU;}
  if(rules){
   const standard=Math.max(.25,Number(rules.shift_hours?.[type]??rules.full_hours??5)||5);
   const hours=duration(row.querySelector('[data-im-start]').value,row.querySelector('[data-im-end]').value);
   const worked=hours===null?standard:hours;
   if(!Number.isFinite(worked)||worked<.25||worked>16){badHours++;x.invalid++;transportElement.textContent='Periksa jam praktik';transportElement.className='im-warning';}
   else {
    const rate=nval(rules.shift_rates?.[type]);
    const hourly=nval(rules.hourly_override);
    const fullTransport=Math.round(worked*60)+60>=Math.round(standard*60);
    const perVisit=fullTransport?rate:Math.round(Math.min(rate,(hourly>0?hourly:rate/standard)*worked));
    x.transport+=perVisit;transportTotal+=perVisit;
    if(!fullTransport){partial++;transportElement.textContent=worked.toLocaleString('id-ID',{maximumFractionDigits:2})+'/'+standard.toLocaleString('id-ID',{maximumFractionDigits:2})+' jam · transport prorata '+money(perVisit);}
    else if(worked+0.00001<standard){withinGrace++;transportElement.textContent='Transport penuh (toleransi 60 menit) '+money(perVisit);}
    else transportElement.textContent='Transport penuh '+money(perVisit);
    transportElement.className='';
   }
  } else transportElement.textContent='Pilih profil untuk tarif/jam';
  groups.set(type,x);
 }
 summary.replaceChildren();
 for(const [type,x] of groups){
  const tr=document.createElement('tr');
  const values=[labels[type]||'Belum dipetakan',fmt(x.n)+'×',fmt(x.b),fmt(x.paidB)+(x.pending?' (cek)':''),fmt(x.u),fmt(x.paidU)+(x.pending?' (cek)':''),type==='poli2'?fmt(x.included):'—',rules?(x.invalid?'Periksa jam':money(x.transport)):'Pilih profil'];
  values.forEach((v,i)=>{const td=document.createElement('td');td.textContent=v;if(i>0)td.style.textAlign='right';tr.appendChild(td)});
  summary.appendChild(tr);
 }
 const totalRow=document.createElement('tr');
 totalRow.style.fontWeight='750';totalRow.style.background='#eef5ff';
 const totalValues=['TOTAL SEPERIODE',fmt(shifts)+'×',fmt(rawBpjs),fmt(paidBpjs)+(pending?' (cek)':''),fmt(rawUmum),fmt(paidUmum)+(pending?' (cek)':''),fmt(poliIncluded),rules?(badHours?'Periksa jam':money(transportTotal)):'Pilih profil'];
 totalValues.forEach((v,i)=>{const td=document.createElement('td');td.textContent=v;if(i>0)td.style.textAlign='right';totalRow.appendChild(td)});
 document.getElementById('im-monthly-total').replaceChildren(totalRow);
 document.getElementById('im-total-shifts').textContent=fmt(shifts)+'×';
 document.getElementById('im-total-bpjs-raw').textContent=fmt(rawBpjs);
 document.getElementById('im-total-umum-raw').textContent=fmt(rawUmum);
 document.getElementById('im-total-bpjs-paid').textContent=fmt(paidBpjs)+(pending?' + perlu koreksi':'');
 document.getElementById('im-total-umum-paid').textContent=fmt(paidUmum)+(pending?' + perlu koreksi':'');
 document.getElementById('im-total-poli-included').textContent=fmt(poliIncluded)+' pasien';
 document.getElementById('im-calc-transport').textContent=rules?(badHours?'Perlu koreksi jam':money(transportTotal)):'Pilih profil';
 document.getElementById('im-calc-bpjs').textContent=rules?(pending?'Perlu koreksi':money(paidBpjs*nval(rules.bpjs_fee))):'Pilih profil';
 document.getElementById('im-calc-umum').textContent=rules?(pending?'Perlu koreksi':money(paidUmum*nval(rules.general_fee))):'Pilih profil';
 document.getElementById('im-calc-partial').textContent=rules?fmt(partial)+' diprorata · '+fmt(withinGrace)+' toleransi'+(badHours?' · '+fmt(badHours)+' jam bermasalah':''):'Pilih profil';
 const note=document.getElementById('im-calc-note');
 if(!rules)note.textContent='Jumlah BPJS/umum tercatat dan berfee sudah dipisahkan. Pilih profil dokter di bagian 2 untuk melihat tarif nominal dan transport per jam.';
 else if(pending||badHours)note.textContent='Perlu koreksi: '+pending+' jadwal pembagian/jenis shift dan '+badHours+' jadwal dengan jam bermasalah. Honor belum siap dikonfirmasi.';
 else note.textContent='Estimasi dari profil terpilih. Poli 2 otomatis mengalokasikan kuota ke BPJS dahulu; periksa dengan catatan pelayanan. Transport penuh jika kekurangan maksimal 60 menit dari standar; selebihnya diprorata. Fee pasien tetap dihitung sesuai BPJS/umum berfee. Tindakan, SIP/PJ, cash dan potongan belum termasuk nominal pratinjau.';
}
rowEls.forEach(el=>{el.addEventListener('input',syncSummary);el.addEventListener('change',syncSummary)});
assignSelect.addEventListener('change',syncSummary);
syncSummary();
const area=document.getElementById('im-procedures'),template=document.getElementById('im-procedure-template');let seq=0;
const assignments=@json($assignments->mapWithKeys(fn($a)=>[(string)$a->id=>$a->branch])->all());
function filterProcedureOptions(){const branch=assignments[document.getElementById('im-assignment').value]||'';
 area.querySelectorAll('select[name$="[procedure_id]"]').forEach(select=>{
   Array.from(select.options).forEach(option=>{if(!option.value)return;const allowed=!option.dataset.branch||option.dataset.branch.toLowerCase()===branch.toLowerCase();option.disabled=!allowed;option.hidden=!allowed});
   if(select.selectedOptions[0]?.disabled)select.value='';
 });
}
function addProcedure(initial={}){
 const idx=seq++;const div=document.createElement('div');
 div.innerHTML=template.innerHTML.replaceAll('__IDX__',String(idx)).replaceAll('__NO__',String(idx+1));
 const block=div.firstElementChild;area.appendChild(block);
 for(const [k,v] of Object.entries(initial)){const input=block.querySelector(`[name="actions[${idx}][${k}]"]`);if(input)input.value=v??''}
 block.querySelector('[data-remove-procedure]').addEventListener('click',()=>block.remove());
 block.querySelector('select[name$="[procedure_id]"]').addEventListener('change',e=>{
    const option=e.target.selectedOptions[0];const fixed=block.querySelector(`[name="actions[${idx}][fixed_fee]"]`);
    const pct=block.querySelector(`[name="actions[${idx}][percentage]"]`);
    if(option?.value){fixed.value=option.dataset.fixed||'';pct.value=option.dataset.pct||''}
 });
 filterProcedureOptions();
}
document.getElementById('im-add-procedure').addEventListener('click',()=>addProcedure());
document.getElementById('im-assignment').addEventListener('change',filterProcedureOptions);
const oldActions=@json(old('actions',[]));Object.values(oldActions).forEach(a=>addProcedure(a));
})();
</script>
@endpush
@endsection
