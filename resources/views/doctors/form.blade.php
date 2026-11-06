@extends('layouts.app')
@section('title','Input Honor Dokter')
@push('head')
<style>
.dr-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;align-items:end}
.dr-grid .field,.dr-grid label{min-width:0}.dr-grid .input{width:100%;min-width:0}
.dr-repeat{border:1px solid #d8e4f5;border-radius:13px;padding:14px;margin:12px 0;background:#f9fbff}
.dr-repeat-h{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.dr-help{font-size:12px;color:#6480a6;line-height:1.6}.dr-toolbar{display:flex;gap:9px;flex-wrap:wrap}
.dr-side{position:sticky;top:12px}.dr-total{background:#194aa6;color:white;padding:18px;border-radius:14px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.dr-total strong{font-size:23px}.dr-results{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #edf2f9;gap:10px}
.dr-mode-block[hidden]{display:none!important}
.dr-recap-list{display:grid;gap:8px}.dr-recap-row{display:grid;grid-template-columns:minmax(145px,1fr) minmax(90px,115px) minmax(135px,1fr);gap:12px;align-items:center;padding:10px 12px;background:#f7faff;border:1px solid #e2ebf9;border-radius:10px}
.dr-recap-row .input{max-width:120px;padding:10px 12px}.dr-recap-row b{color:#183c79}.dr-recap-row small{display:block;color:#6880a4}.dr-recap-row .dr-recap-time{grid-column:1/-1;padding:8px 0;border-top:1px solid #e2ebf9}.dr-recap-row .dr-recap-time .dr-grid{grid-template-columns:repeat(3,minmax(110px,1fr))}.dr-recap-plain{font-size:13px;color:#586d8d}.dr-compact-menu{display:flex;gap:8px;flex-wrap:wrap}.dr-compact-menu label{cursor:pointer;border:1px solid #cfdbf0;border-radius:10px;padding:10px 15px;background:#f7faff;color:#153970;font-weight:650}.dr-compact-menu label:has(input:checked){background:#1d4bb4;color:white;border-color:#1d4bb4}.dr-compact-menu input{margin-right:6px}.dr-basic{border:1px solid #e0e9f6;border-radius:12px;background:white;margin-bottom:16px}.dr-basic summary{cursor:pointer;padding:16px;font-weight:750;color:#173c79}.dr-basic .dr-grid{padding:0 16px 16px}
@media(max-width:650px){.dr-recap-row{grid-template-columns:1fr 92px}.dr-recap-row .dr-recap-value{grid-column:1/-1;text-align:right}.dr-recap-row .dr-recap-time .dr-grid{grid-template-columns:1fr 1fr}}
@media(max-width:1150px){.dr-main{grid-template-columns:1fr!important}.dr-side{position:static}}
</style>
@endpush
@section('content')
@php
$procOptions=$procedures->map(fn($p)=>['id'=>$p->id,'label'=>$p->label,'mode'=>$p->mode,'fixed_fee'=>$p->fixed_fee,'percentage'=>$p->percentage])->values()->all();
$editing=isset($doctorPayroll);
$chosenMode=old('input_mode',$input['input_mode']??(!empty($input['shifts'])?'daily':'recap'));
$rateConfig=$assignment?->rules??[];
$save=$editing?route('doctors.update',$doctorPayroll):route('doctors.store');
@endphp
<div class="page-head"><div><p class="eyebrow">PENGGAJIAN DOKTER</p><h1 class="page-title">{{ $editing?'Edit Draft':'Rekap' }} Honor Dokter</h1><p class="page-sub">Cukup isi jumlah kali (×) seperti rekap bulanan. Sistem menghitung nominal otomatis. Detail harian tersedia bila diperlukan.</p></div><a class="btn btn-outline" href="{{ route('doctors.index') }}">Kembali</a></div>
<div class="card" style="margin-bottom:16px"><div class="card-pad"><form method="get" action="{{ route('doctors.create') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
<div class="field" style="min-width:300px;flex:2"><label>Dokter & Unit/Cabang</label><select class="input" name="assignment" required @disabled($editing)><option value="">Pilih dokter per cabang</option>@foreach($assignments as $a)<option value="{{ $a->id }}" @selected($assignment?->id===$a->id)>{{ $a->employee?->name }} — {{ $a->branch }} ({{ $a->profile_label }})</option>@endforeach</select></div>
<div class="field"><label>Periode</label><input class="input" type="month" name="period" value="{{ $period }}" required @readonly($editing)></div>
@if(!$editing)<button class="btn btn-primary" type="submit">Pilih & Lanjut</button>@endif
@can('manage-settings')<a class="btn btn-outline" href="{{ route('doctors.settings') }}">Atur Tarif</a>@endcan
</form></div></div>
@if($assignment)
<form id="doctor-payroll-form" method="post" action="{{ $save }}">@csrf @if($editing)@method('PUT')@endif
<input type="hidden" name="doctor_assignment_id" value="{{ $assignment->id }}"><input type="hidden" name="period" value="{{ $period }}">
<div class="dr-main" style="display:grid;grid-template-columns:minmax(0,1fr) 325px;gap:20px;align-items:start">
<div>
<details class="dr-basic" @if((int)($input['base_salary']??0)>0 || (int)($input['sip_fee']??0)>0 || (int)($input['pj_fee']??0)>0 || (int)($input['absence_days']??0)>0) open @endif><summary>Honor tetap / SIP / PJ / potongan tidak hadir (opsional)</summary>
<div class="dr-grid">
@foreach(['base_salary'=>'Gaji Pokok (Rp)','sip_fee'=>'Fee SIP (Rp)','pj_fee'=>'Fee PJ (Rp)','absence_days'=>'Jumlah Hari Tidak Hadir'] as $field=>$label)
<div class="field"><label>{{ $label }}</label><input class="input" type="number" name="{{ $field }}" min="0" value="{{ $input[$field]??0 }}" required></div>@endforeach
</div></details>
<div class="card" style="margin-bottom:16px"><div class="card-head"><div><h3>1. Cara Input</h3><p class="card-sub">Rekap bulanan lebih praktis. Jika perlu tanggal per kunjungan, pilih Detail Harian.</p></div></div>
<div class="card-pad"><div class="dr-compact-menu">
<label><input type="radio" name="input_mode" value="recap" @checked($chosenMode==='recap')>Rekap Bulanan (× Jumlah)</label>
<label><input type="radio" name="input_mode" value="daily" @checked($chosenMode==='daily')>Detail Harian</label></div>
<p class="dr-help" style="margin-top:12px">Pilih salah satu mode. Input mode lain tidak akan dihitung supaya tidak terjadi gaji ganda.</p></div></div>
<section id="dr-monthly-mode" class="dr-mode-block">
<div class="card" style="margin-bottom:16px"><div class="card-head"><div><h3>2. Uang Transport — Berapa Kali Hadir?</h3><p class="card-sub">Contoh: Hari biasa Rp70.000 × 5 kali. Untuk jam parsial, buka 'Jam khusus' pada baris itu.</p></div></div>
<div class="card-pad"><div class="dr-recap-list" id="dr-recap-transport"></div><div class="dr-toolbar" style="margin-top:12px"><button type="button" class="btn btn-outline" id="add-recap-transport">+ Tambah Variasi Shift</button></div><p class="dr-help" style="margin-top:8px">Jika ada hari biasa yang full dan parsial, gunakan dua baris Hari Biasa dengan durasi berbeda. Tidak perlu mengisi tanggal untuk rekap bulanan.</p></div></div>
<div class="card" style="margin-bottom:16px"><div class="card-head"><div><h3>3. Uang Pasien & Layanan — Berapa Pasien/Kali?</h3><p class="card-sub">Isi jumlah dari rekap akhir bulan. Nominal = jumlah × tarif dokter.</p></div></div>
<div class="card-pad"><div class="dr-recap-list" id="dr-recap-counts"></div><p class="dr-help" style="margin-top:12px"><b>Poli 2:</b> kolom 'setelah 20' hanya untuk pasien yang mendapat fee mulai urutan ke-21 <b>pada setiap kedatangan</b>. Jangan menghitung 20 dari total bulanan. Jika belum ada pembagian pasien, gunakan Detail Harian untuk menghitung tepat.</p></div></div>
</section>
<section id="dr-daily-mode" class="dr-mode-block" hidden>
<div class="card" style="margin-bottom:16px"><div class="card-head"><div><h3>2. Jadwal Praktik / Jam / Pasien per Hari</h3><p class="card-sub">Mode rinci untuk dokter yang ingin dicatat per tanggal, termasuk Poli 2 dan shift parsial.</p></div></div>
<div class="card-pad"><div class="dr-help">Jam kosong = transport penuh. Untuk Poli 2, {{ $assignment->rules['poli2_included']??20 }} pasien pertama termasuk transport pada setiap shift.</div>
<div id="doctor-shifts"></div><button class="btn btn-outline" type="button" id="add-shift">+ Tambah Jadwal Praktik</button><div id="dr-shift-breakdown" class="dr-help" style="margin-top:14px" aria-live="polite"></div></div></div>
</section>
<details class="card" style="margin-bottom:16px" @if(!empty($input['actions'])) open @endif><summary class="card-head" style="cursor:pointer"><div><h3>4. Tindakan Medis / Protesa (jika ada)</h3><p class="card-sub">Pilih tindakan, isi jumlah (×). Tanggal boleh kosong pada mode rekap; fee dihitung otomatis.</p></div></summary>
<div class="card-pad"><div id="doctor-actions"></div><button type="button" class="btn btn-outline" id="add-action">+ Tambah Tindakan</button> <a href="{{ route('doctors.settings') }}" class="btn btn-outline">Lihat Katalog</a></div></details>
<details class="card" style="margin-bottom:16px" @if(!empty($input['extras'])) open @endif><summary class="card-head" style="cursor:pointer"><div><h3>5. Tambahan & Potongan (jika ada)</h3><p class="card-sub">Misalnya penyuluhan, bonus, B3, BPJS kesehatan, tidak masuk, atau PPh 21.</p></div></summary>
<div class="card-pad"><div id="doctor-extras"></div><div class="dr-toolbar"><button class="btn btn-outline" type="button" id="add-income">+ Penerimaan</button><button class="btn btn-outline" type="button" id="add-deduction">+ Potongan</button></div><p class="dr-help" style="margin-top:10px">PPh 21 dokter belum otomatis mengikuti rumus karyawan tetap. Masukkan pajak hasil pemeriksaan petugas sesuai status pajak dan jenis hubungan kerja.</p></div></details>
<details class="card" style="margin-bottom:16px" @if(!empty($input['cash'])) open @endif><summary class="card-head" style="cursor:pointer"><div><h3>6. Cash yang Sudah Diambil Dokter</h3><p class="card-sub">Catat kapan dan berapa uang yang sudah diterima selesai praktik. Bukan potongan honor.</p></div></summary>
<div class="card-pad"><div id="doctor-cash"></div><div class="dr-toolbar"><button type="button" class="btn btn-outline" id="add-cash">+ Catat Pengambilan Cash</button><button type="button" class="btn btn-outline" id="cash-from-transport">Isi Cash dari Transport Jadwal</button></div></div></details>
<div class="card"><div class="card-head"><h3>7. Catatan Internal</h3></div><div class="card-pad"><textarea class="input" name="notes" rows="3" maxlength="2000" placeholder="Catatan opsional">{{ $input['notes']??'' }}</textarea></div></div>
</div>
<aside class="dr-side"><div class="card"><div class="card-head"><h3>Pratinjau Otomatis</h3></div><div class="card-pad"><p class="dr-help">Dihitung ulang di server setiap isian berubah.</p>
<div id="dr-error" class="alert alert-error" style="display:none;margin:12px 0"></div>
<div class="dr-results"><span>Penerimaan</span><b id="dr-gross">—</b></div><div class="dr-results"><span>Potongan</span><b id="dr-cut">—</b></div>
<div class="dr-results"><span>Sudah cash</span><b id="dr-cash">—</b></div>
<div class="dr-total" style="margin-top:12px"><span>Hak Dokter</span><strong id="dr-net">—</strong></div>
<div class="dr-total" style="background:#102f66;margin-top:10px"><span>Sisa Transfer</span><strong id="dr-transfer">—</strong></div>
<div id="dr-warning" class="dr-help" style="color:#a4451f;margin:12px 0"></div>
<button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">{{ $editing?'Simpan Perubahan Draft':'Simpan Draft Honor' }}</button>
</div></div></aside>
</div></form>
@endif
@push('scripts')
@if($assignment)
<script>
(()=>{
const root=document.getElementById('doctor-payroll-form');
const init=@json($input);
const procedures=@json($procOptions);
const doctorPct=@json($assignment->rules['procedure_pct']??35);
const shiftsLabels=@json(config('doctor_payroll.shift_labels'));
const shiftRates=@json($rateConfig['shift_rates']??[]);
const shiftHours=@json($rateConfig['shift_hours']??[]);
const defaultHours=Number(@json($rateConfig['full_hours']??5));
const hourlyOverride=Number(@json($rateConfig['hourly_override']??0));
const patientMode=@json($rateConfig['patient_mode']??'all');
const patientRates={bpjs:Number(@json($rateConfig['bpjs_fee']??0)),general:Number(@json($rateConfig['general_fee']??0)),usg:Number(@json($rateConfig['usg_fee']??0)),homeclinic:Number(@json($rateConfig['homecare_clinic']??0)),homeown:Number(@json($rateConfig['homecare_own']??0))};
const poliQuota=@json($assignment->rules['poli2_included']??20);
const period=@json($period);
const money=n=>'Rp'+Number(n||0).toLocaleString('id-ID');
const esc=x=>String(x??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const dateDefault=period+'-01';let counts={shifts:0,actions:0,extras:0,cash:0,recap_transport:0};
const recapLabels=[
 ['bpjs_regular','Pasien BPJS (selain Poli 2)',patientRates.bpjs],
 ['umum_regular','Pasien Umum (selain Poli 2)',patientRates.general],
 ['poli2_bpjs_paid','Pasien BPJS Poli 2 setelah kuota',patientRates.bpjs],
 ['poli2_umum_paid','Pasien Umum Poli 2 setelah kuota',patientRates.general],
 ['usg','Pasien USG',patientRates.usg],
 ['homecare_clinic','Homecare motor klinik',patientRates.homeclinic],
 ['homecare_own','Homecare motor sendiri',patientRates.homeown],
];
function recapTransport(row={}){
 const i=counts.recap_transport++, key=x=>`recap_transport[${i}][${x}]`;
 const type=row.shift_type||'biasa', num=row.qty??0;
 const options=Object.entries(shiftsLabels).map(([k,v])=>`<option value="${esc(k)}" ${k===type?'selected':''}>${esc(v)}</option>`).join('');
 const html=`<div class="dr-recap-row dr-transport-item">
  <div class="field"><label>Jenis Shift</label><select class="input" name="${key('shift_type')}">${options}</select><small data-full-rate></small></div>
  <div class="field"><label>Jumlah (×)</label><input class="input" name="${key('qty')}" type="number" min="0" max="100" value="${esc(num)}" required></div>
  <div class="dr-recap-value"><small data-formula></small><b data-subtotal>Rp0</b></div>
  <details class="dr-recap-time"><summary class="dr-help">Jam khusus / parsial (opsional)</summary><div class="dr-grid" style="margin-top:10px">
   ${field(key('start_time'),'Mulai (opsional)','time',row.start_time??'')}
   ${field(key('end_time'),'Selesai (opsional)','time',row.end_time??'')}
   ${field(key('hours_per_visit'),'Durasi per kedatangan (jam)','number',row.hours_per_visit??'','min="0.25" max="24" step="0.25" placeholder="Full shift"')}
  </div><p class="dr-help">Isi jam mulai & selesai ATAU durasi; bila keduanya kosong, dianggap full shift.</p></details>
  <button type="button" data-delete-recap="1" class="btn btn-outline btn-sm" style="justify-self:start">Hapus baris</button>
 </div>`;
 const div=document.getElementById('dr-recap-transport');div.insertAdjacentHTML('beforeend',html);
 div.lastElementChild.querySelectorAll('input,select').forEach(el=>el.addEventListener('input',updateRecapTotals));
 updateRecapTotals();
}
function recapPatients(){
 const c=init.recap_counts||{};const div=document.getElementById('dr-recap-counts');
 div.innerHTML=recapLabels.filter(([key,label,price])=>price>0||Number(c[key]??0)>0).map(([key,label,price])=>`<div class="dr-recap-row dr-patient-item" data-rate="${esc(price)}" data-poli="${key.startsWith('poli2_')?'1':'0'}"><div><b>${esc(label)}</b><small>${money(price)} per pasien/kali</small></div><div class="field"><label>Jumlah (×)</label><input class="input" type="number" min="0" max="100000" name="recap_counts[${key}]" value="${esc(c[key]??0)}" required></div><div class="dr-recap-value"><b data-subtotal>Rp0</b></div></div>`).join('');
 if(!div.children.length)div.innerHTML='<p class="dr-recap-plain">Profil dokter ini belum memiliki tarif pasien/layanan. Atur tarif jika diperlukan.</p>';
 div.querySelectorAll('input').forEach(el=>el.addEventListener('input',updateRecapTotals));updateRecapTotals();
}
function timeHours(a,b){if(!a||!b)return null;const m=s=>{const [h,min]=s.split(':').map(Number);return h*60+min};let d=m(b)-m(a);if(d<=0)d+=1440;return d/60;}
function updateRecapTotals(){
 document.querySelectorAll('.dr-transport-item').forEach(item=>{
  const t=item.querySelector('[name$="[shift_type]"]').value;
  const qty=Number(item.querySelector('[name$="[qty]"]').value||0);
  const std=Number(shiftHours[t]??defaultHours) || 5;
  const st=item.querySelector('[name$="[start_time]"]').value, en=item.querySelector('[name$="[end_time]"]').value;
  const custom=item.querySelector('[name$="[hours_per_visit]"]').value;
  const worked=timeHours(st,en)??(custom?Number(custom):std);
  const full=Number(shiftRates[t]||0);
  const per=worked>=std?full:Math.round(Math.min(full,(hourlyOverride>0?hourlyOverride:full/std)*worked));
  item.querySelector('[data-full-rate]').textContent=`Tarif full ${money(full)} / ${std} jam`;
  item.querySelector('[data-formula]').textContent=`${qty} × ${money(per)}`;
  item.querySelector('[data-subtotal]').textContent=money(Math.max(0,qty)*Math.max(0,per));
 });
 const poliExists=[...document.querySelectorAll('.dr-transport-item')].some(item=>item.querySelector('[name$="[shift_type]"]').value==='poli2' && Number(item.querySelector('[name$="[qty]"]').value)>0);
 document.querySelectorAll('.dr-patient-item').forEach(item=>{
  const qty=Number(item.querySelector('input').value||0);
  if(item.dataset.poli==='1'){
   const visible=poliExists||qty>0;item.style.display=visible?'':'none';item.querySelector('input').disabled=!visible || root.querySelector('input[name="input_mode"]:checked')?.value!=='recap';
  }
  const rate=Number(item.dataset.rate||0);
  const realRate=patientMode==='none'&&['bpjs_regular','umum_regular','poli2_bpjs_paid','poli2_umum_paid'].some(k=>item.querySelector('input').name.includes(k))?0:rate;
  item.querySelector('[data-subtotal]').textContent=money(qty*realRate);
 });
}
function updateMode(){
 const mode=root.querySelector('input[name="input_mode"]:checked')?.value||'recap';
 for(const [value,id] of [['recap','dr-monthly-mode'],['daily','dr-daily-mode']]){
  const panel=document.getElementById(id),active=mode===value;panel.hidden=!active;
  panel.querySelectorAll('input,select,textarea,button').forEach(el=>{el.disabled=!active;});
 }
 document.querySelectorAll('#doctor-actions input[name$="[work_date]"]').forEach(el=>{el.required=mode==='daily';});
 document.getElementById('dr-shift-breakdown').style.display=mode==='daily'?'':'none';
 document.getElementById('cash-from-transport').style.display=mode==='daily'?'':'none';
 updateRecapTotals();preview();
}
const field=(name,label,type,value='',extra='')=>`<div class="field"><label>${label}</label><input class="input" name="${name}" type="${type}" value="${esc(value)}" ${extra}></div>`;
const select=(name,label,options,value)=>`<div class="field"><label>${label}</label><select class="input" name="${name}">${options.map(([key,txt])=>`<option value="${esc(key)}" ${String(key)===String(value)?'selected':''}>${esc(txt)}</option>`).join('')}</select></div>`;
const wrap=(html,title,copy=false)=>`<div class="dr-repeat"><div class="dr-repeat-h"><strong>${title}</strong><div class="dr-toolbar">${copy?'<button class="btn btn-outline btn-sm" type="button" data-duplicate="1">Salin ke hari berikut</button>':''}<button class="btn btn-outline btn-sm" type="button" data-delete="1">Hapus</button></div></div><div class="dr-grid">${html}</div></div>`;
function addShift(row={}){
 const i=counts.shifts++;const n=key=>`shifts[${i}][${key}]`;
 const html=field(n('work_date'),'Tanggal','date',row.work_date||dateDefault,'required')+
 select(n('shift_type'),'Jenis Jadwal',Object.entries(shiftsLabels),row.shift_type||'biasa')+
 field(n('start_time'),'Jam masuk (opsional)','time',row.start_time||'')+
 field(n('end_time'),'Jam selesai (opsional)','time',row.end_time||'')+
 field(n('standard_hours'),'Override lama shift (opsional)','number',row.standard_hours??'','step="0.25" min="0.25" max="24" placeholder="Otomatis dari profil"')+
 field(n('transport_override'),'Koreksi transport (opsional)','number',row.transport_override??'','min="0" placeholder="Otomatis"')+
 field(n('bpjs_count'),'Pasien BPJS','number',row.bpjs_count??0,'min="0" required')+
 field(n('general_count'),'Pasien umum','number',row.general_count??0,'min="0" required')+
 field(n('bpjs_excess'),'BPJS setelah kuota Poli 2','number',row.bpjs_excess??'','min="0" placeholder="Isi jika campuran"')+
 field(n('general_excess'),'Umum setelah kuota Poli 2','number',row.general_excess??'','min="0" placeholder="Isi jika campuran"')+
 field(n('usg_count'),'Pasien USG','number',row.usg_count??0,'min="0" required')+
 field(n('homecare_clinic'),'Homecare motor klinik','number',row.homecare_clinic??0,'min="0" required')+
 field(n('homecare_own'),'Homecare motor sendiri','number',row.homecare_own??0,'min="0" required');
 const container=document.getElementById('doctor-shifts');
 container.insertAdjacentHTML('beforeend',wrap(html,'Kedatangan / Shift #'+(i+1),true));
 const item=container.lastElementChild;
 const shiftPicker=item.querySelector('[name$="[shift_type]"]');
 function togglePoli(){
  const poli=shiftPicker.value==='poli2';
  item.querySelectorAll('[name$="[bpjs_excess]"],[name$="[general_excess]"]').forEach(input=>{
   input.closest('.field').style.display=poli?'':'none';
   if(!poli)input.value='';
  });
  const note=item.querySelector('[data-poli-note]');
  note.textContent=poli?'Poli 2: '+poliQuota+' pasien pertama termasuk transport. Jika jumlah pasien lebih besar dari '+poliQuota+' dan jenisnya campuran, isi pembagian pasien setelah kuota.':'Selain Poli 2: pasien BPJS dan umum dihitung sejak pasien pertama; kolom pembagian setelah kuota tidak diperlukan.';
 }
 item.querySelector('.dr-grid').insertAdjacentHTML('beforeend','<div class="dr-help" style="grid-column:1/-1" data-poli-note></div>');
 shiftPicker.addEventListener('change',togglePoli);
 togglePoli();
}
function addAction(row={}){
 const i=counts.actions++;const n=key=>`actions[${i}][${key}]`;
 const proc=procedures.find(p=>p.id===(Number(row.procedure_id)))||procedures[0];if(!proc)return;
 const html=field(n('work_date'),'Tanggal tindakan (opsional di rekap)','date',row.work_date||'')+
 select(n('procedure_id'),'Pilih tindakan',procedures.map(p=>[p.id,p.label]),row.procedure_id||proc.id)+
 field(n('qty'),'Jumlah tindakan','number',row.qty??1,'min="1" required')+
 field(n('fixed_fee'),'Fee per tindakan (Rp)','number',row.fixed_fee??proc.fixed_fee,'min="0"')+
 field(n('percentage'),'Fee dokter (%)','number',row.percentage??(proc.mode==='fixed'?proc.percentage:doctorPct),'min="0" max="100" step="0.01"')+
 field(n('price'),'Tarif tindakan / biaya (Rp)','number',row.price??0,'min="0" required')+
 field(n('material'),'BHMP / Lab (Rp)','number',row.material??0,'min="0" required')+
 field(n('claim'),'Klaim BPJS protesa (Rp)','number',row.claim??0,'min="0" required')+
 field(n('patient'),'Bayaran pasien protesa (Rp)','number',row.patient??0,'min="0" required');
 const parent=document.getElementById('doctor-actions');parent.insertAdjacentHTML('beforeend',wrap(html,'Tindakan #'+(i+1)));
 const item=parent.lastElementChild;const picker=item.querySelector('[name$="[procedure_id]"]');
 item.querySelector('[name$="[work_date]"]').required=root.querySelector('input[name="input_mode"]:checked')?.value==='daily';
 function toggle(reset){const selected=procedures.find(p=>p.id===Number(picker.value))||proc;
  if(reset){item.querySelector('[name$="[fixed_fee]"]').value=selected.fixed_fee;item.querySelector('[name$="[percentage]"]').value=selected.mode==='fixed'?selected.percentage:doctorPct;}
  const show={fixed:['fixed_fee'],percentage:['price','material','percentage'],prosthesis:['claim','patient','material','percentage']}[selected.mode]||[];
  item.querySelectorAll('.field').forEach(el=>{const name=el.querySelector('input, select')?.name||'';const key=name.match(/\[([^\]]+)\]$/)?.[1];if(['fixed_fee','price','material','percentage','claim','patient'].includes(key))el.style.display=show.includes(key)?'':'none';});
 }
 picker.addEventListener('change',()=>toggle(true));toggle(false);
}
function addExtra(row={}){
 const i=counts.extras++;const n=key=>`extras[${i}][${key}]`;
 const type=row.type||'income';const name=row.label??(type==='income'?'Bonus':'Potongan');
 const html=field(n('label'),'Keterangan','text',name,'required maxlength="120" list="doctor-extra-names"')+
 select(n('type'),'Jenis',[['income','Penerimaan (+)'],['deduction','Potongan (-)']],type)+
 field(n('amount'),'Nominal (Rp)','number',row.amount??0,'min="0" required');
 document.getElementById('doctor-extras').insertAdjacentHTML('beforeend',wrap(html,'Tambahan / Potongan #'+(i+1)));
}
function addCash(row={}){
 const i=counts.cash++;const n=key=>`cash[${i}][${key}]`;
 document.getElementById('doctor-cash').insertAdjacentHTML('beforeend',wrap(
 field(n('date'),'Tanggal ambil','date',row.date||dateDefault,'required')+
 field(n('amount'),'Sudah diterima CASH (Rp)','number',row.amount??0,'min="1" required')+
 field(n('note'),'Catatan (opsional)','text',row.note||'','maxlength="150"'),'Pengambilan Cash #'+(i+1)));
}
(root.querySelectorAll('input')||[]).forEach(el=>{});
(init.shifts||[]).forEach(addShift);(init.actions||[]).forEach(addAction);(init.extras||[]).forEach(addExtra);(init.cash||[]).forEach(addCash);
recapPatients();
if((init.recap_transport||[]).length)(init.recap_transport||[]).forEach(recapTransport);
else Object.keys(shiftsLabels).filter(t=>Number(shiftRates[t]||0)>0).forEach(t=>recapTransport({shift_type:t,qty:0}));
root.querySelectorAll('input[name="input_mode"]').forEach(el=>el.addEventListener('change',updateMode));
document.getElementById('add-recap-transport').onclick=()=>{recapTransport();preview();};
document.getElementById('dr-recap-transport').addEventListener('click',e=>{if(e.target.closest('[data-delete-recap]')){e.target.closest('.dr-recap-row').remove();preview();}});
document.getElementById('add-shift').onclick=()=>{addShift();preview()};
document.getElementById('add-action').onclick=()=>{addAction();preview()};
document.getElementById('add-income').onclick=()=>{addExtra({type:'income',label:'Bonus',amount:0});preview()};
document.getElementById('add-deduction').onclick=()=>{addExtra({type:'deduction',label:'Potongan',amount:0});preview()};
document.getElementById('add-cash').onclick=()=>{addCash();preview()};
root.addEventListener('click',e=>{const btn=e.target.closest('[data-delete]');if(btn){btn.closest('.dr-repeat').remove();preview();return;}
 const copy=e.target.closest('[data-duplicate]');if(copy){
  const row={};copy.closest('.dr-repeat').querySelectorAll('input,select').forEach(el=>{const key=el.name.match(/\[([^\]]+)\]$/)?.[1];if(key)row[key]=el.value;});
  if(row.work_date){const d=new Date(row.work_date+'T12:00:00Z');d.setUTCDate(d.getUTCDate()+1);const next=d.toISOString().slice(0,10);if(next.slice(0,7)===period)row.work_date=next;}
  addShift(row);preview();
 }
});
let tid=0,lastPreview=null,previewSeq=0;
document.getElementById('cash-from-transport').onclick=()=>{if(!lastPreview){alert('Selesaikan isian jadwal dulu.');return;}if(!lastPreview.shifts?.length){alert('Belum ada jadwal.');return;}if(document.getElementById('doctor-cash').children.length>0&&!confirm('Sudah ada catatan cash. Tambahkan lagi transport dari semua jadwal?'))return;for(const item of lastPreview.shifts){if(item.transport>0)addCash({date:item.date,amount:item.transport,note:'Transport '+item.type});}preview();};
root.addEventListener('input',()=>{clearTimeout(tid);tid=setTimeout(preview,380)});root.addEventListener('change',()=>{clearTimeout(tid);tid=setTimeout(preview,180)});
const error=document.getElementById('dr-error');const warning=document.getElementById('dr-warning');
async function preview(){
 const ticket=++previewSeq;try{
 const formData=new FormData(root);const response=await fetch(@json(route('doctors.preview', [], false)),{method:'POST',body:formData,credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});
 const data=await response.json();if(ticket!==previewSeq)return;if(!response.ok){let errs=Object.values(data.errors||{}).flat();throw new Error(errs[0]||data.message||'Periksa kembali isian.');}
 error.style.display='none';lastPreview=data;
 for(const [key,id]of Object.entries({gross_total:'dr-gross',deduction_total:'dr-cut',net_total:'dr-net',cash_total:'dr-cash',transfer_due:'dr-transfer'}))document.getElementById(id).textContent=money(data[key]);
 const breakdown=document.getElementById('dr-shift-breakdown');
 breakdown.innerHTML=(data.shifts||[]).map((shift,i)=>{
  const kind=esc(shiftsLabels[shift.type]||shift.type);
  const hours=Number(shift.hours).toLocaleString('id-ID');const full=Number(shift.standard_hours).toLocaleString('id-ID');
  const work=shift.start_time&&shift.end_time?esc(shift.start_time+'–'+shift.end_time):'Full shift';
  let formula=shift.transport_mode==='manual'?'Koreksi manual':shift.transport_mode==='full'?'Tarif penuh':
    (shift.transport_mode==='hourly'?'Tarif per jam khusus '+money(shift.hourly_rate):money(shift.full_rate)+' / '+full+' jam × '+hours+' jam');
  return `<div style="padding:8px 10px;margin:6px 0;background:#eef5ff;border-radius:8px"><b>${esc(shift.date)} · ${kind}</b> (${work})<div>${formula} = <b>${money(shift.transport)}</b></div><div>Pasien BPJS: ${shift.bpjs_paid}/${shift.bpjs} berfee; umum: ${shift.general_paid}/${shift.general} berfee${shift.type==='poli2'?' (kuota Poli 2: '+(shift.patient_quota??20)+' pasien)':''}. Total fee pasien: <b>${money(shift.patient_fee)}</b></div></div>`;
 }).join('');
 warning.textContent=(data.warnings||[]).join(' ');
 }catch(e){if(ticket!==previewSeq)return;lastPreview=null;document.getElementById('dr-shift-breakdown').innerHTML='';error.style.display='block';error.textContent=e.message;}
}
updateMode();
})();
</script>
<datalist id="doctor-extra-names"><option value="Penyuluhan"><option value="Insentif Tindakan"><option value="Bonus"><option value="Fee PJ"><option value="BPJS Kesehatan"><option value="B3"><option value="Tidak Masuk"><option value="PPh 21"><option value="Potongan Lain"></datalist>
@endif
@endpush
@endsection
