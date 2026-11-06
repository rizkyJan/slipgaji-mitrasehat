@extends('layouts.app')
@section('title','Profil Tarif Dokter')
@section('content')
@php
$editing=(bool)$assignment;
$r=$assignment?->rules??($presets['umum']??[]);
$v=fn($key,$default=0)=>old($key,$r[$key]??$default);
@endphp
<div class="page-head"><div><p class="eyebrow">PENGATURAN HONOR</p><h1 class="page-title">{{ $editing?'Edit':'Tambah' }} Profil Dokter</h1><p class="page-sub">Tarif ini berlaku untuk satu dokter di satu unit; perubahan berikutnya tidak mengubah slip lama.</p></div><a class="btn btn-outline" href="{{ route('doctors.settings') }}">Kembali</a></div>
<form method="post" action="{{ $editing?route('doctors.assignments.update',$assignment):route('doctors.assignments.store') }}">@csrf @if($editing)@method('PUT')@endif
<div class="card" style="margin-bottom:18px"><div class="card-pad"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(225px,1fr));gap:15px">
<div class="field"><label>Pilih dokter dari Data Karyawan *</label><select class="input" required name="employee_id"><option value="">Pilih dokter</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id',$assignment?->employee_id)==$e->id)>{{ $e->name }} · {{ $e->employee_code }}</option>@endforeach</select></div>
<div class="field"><label>Unit/Cabang *</label><input class="input" required name="branch" value="{{ old('branch',$assignment?->branch??'') }}" list="cabang-dokter" placeholder="Misalnya Carikan / Darasan"><datalist id="cabang-dokter"><option value="Carikan"><option value="Darasan"><option value="Serenan"></datalist></div>
<div class="field"><label>Gunakan template tarif</label><select class="input" id="doctor-preset"><option value="">Pilih untuk mengisi otomatis</option>@foreach($presets as $code=>$template)<option value="{{ $code }}">{{ $template['label'] }}</option>@endforeach</select></div>
<div class="field"><label>Nama profil *</label><input class="input" required name="profile_label" value="{{ old('profile_label',$assignment?->profile_label??'Dokter Umum') }}"></div>
<div class="field"><label>Fee PJ termasuk</label><select class="input" name="pj_mode"><option value="income" @selected(old('pj_mode',$assignment?->pj_mode??'income')==='income')>Penerimaan (+)</option><option value="deduction" @selected(old('pj_mode',$assignment?->pj_mode??'income')==='deduction')>Potongan (-)</option></select></div>
<div class="field"><label>Jenis dokter</label><select class="input" name="kind">@foreach(['general'=>'Dokter Umum','dentist'=>'Dokter Gigi','usg'=>'Dokter USG','other'=>'Lainnya'] as $key=>$label)<option value="{{ $key }}" @selected(old('kind',$assignment?->kind??'general')===$key)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label>Aktif</label><label style="display:block;margin-top:13px"><input type="checkbox" name="active" value="1" @checked(old('active',$assignment?->active??true))> Profil digunakan</label></div>
</div></div></div>
<div class="card" style="margin-bottom:18px"><div class="card-head"><div><h3>Gaji & Tarif Kehadiran</h3><p class="card-sub">Atur tarif dan durasi shift penuh untuk masing-masing jenis jadwal. Jika masuk kurang dari durasi penuh, transport otomatis proporsional. Jadwal lama yang sudah disetujui tidak berubah.</p></div></div><div class="card-pad" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:14px">
@foreach(['base_salary'=>'Gaji pokok bulanan (Rp)','absence_rate'=>'Potongan per hari tidak masuk (Rp)','sip_fee'=>'Fee SIP bulanan (Rp)','pj_fee'=>'Fee PJ bulanan (Rp)'] as $name=>$label)<div class="field"><label>{{ $label }}</label><input class="input" type="number" min="0" name="{{ $name }}" value="{{ old($name,$assignment?->$name??0) }}" required></div>@endforeach
@foreach(config('doctor_payroll.shift_labels') as $key=>$label)
<div class="field"><label>Transport FULL {{ $label }} (Rp)</label><input class="input" type="number" min="0" data-shift="{{ $key }}" name="shift_rates[{{ $key }}]" value="{{ old('shift_rates.'.$key,$r['shift_rates'][$key]??0) }}" required></div>
<div class="field"><label>Lama FULL {{ $label }} (jam)</label><input class="input" type="number" step="0.25" min="0.25" max="24" data-shift-hours="{{ $key }}" name="shift_hours[{{ $key }}]" value="{{ old('shift_hours.'.$key,$r['shift_hours'][$key]??($r['full_hours']??5)) }}" required><small class="dr-help">5,5 jam ditulis 5.5</small></div>
@endforeach
<div class="field"><label>Durasi default lama (jam, fallback)</label><input class="input" type="number" step="0.25" min="0.25" max="24" name="full_hours" value="{{ $v('full_hours',5) }}" required><small class="dr-help">Cadangan untuk slip lama. Durasi per shift di atas yang dipakai sekarang.</small></div>
<div class="field"><label>Tarif per jam khusus (Rp)</label><input class="input" type="number" min="0" name="hourly_override" value="{{ $v('hourly_override') }}" required><small class="dr-help">Isi 0 agar otomatis tarif full shift / jam full × jam praktik. Jika nonzero, nilai ini dipakai untuk semua shift parsial.</small></div>
</div></div>
<div class="card" style="margin-bottom:18px"><div class="card-head"><h3>Pasien, USG, Homecare & Tindakan</h3></div><div class="card-pad" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px">
<div class="field" style="grid-column:1/-1"><div class="dr-help" style="padding:12px;background:#edf5ff;border-radius:9px"><strong>Aturan R4.4:</strong> Hari biasa, malam, libur, KIA dan jenis jadwal selain Poli 2: fee BPJS/umum sejak pasien pertama. <strong>Hanya Poli 2</strong>: pasien pertama sampai kuota termasuk transport; mulai pasien berikutnya memperoleh fee. Pembagian BPJS/umum sesudah kuota wajib diisi jika pasien campuran.</div></div>
<div class="field"><label>Aturan fee pasien dokter</label><select class="input" name="patient_mode" id="patient-mode"><option value="all" @selected($v('patient_mode','all')!=='none')>Otomatis: semua pasien, Poli 2 setelah kuota</option><option value="none" @selected($v('patient_mode','all')==='none')>Tanpa fee pasien BPJS / umum (misalnya dokter khusus USG)</option></select></div>
<input type="hidden" name="included" value="{{ $v('included',20) }}">
<div class="field"><label>Kuota pasien termasuk transport KHUSUS Poli 2</label><input class="input" type="number" min="0" max="1000" name="poli2_included" value="{{ $v('poli2_included',20) }}" required><small class="dr-help">Default 20 pasien; mulai pasien ke-21 mendapat fee. Tidak berlaku pada shift lain.</small></div>
@foreach(['bpjs_fee'=>'Fee pasien BPJS (Rp)','general_fee'=>'Fee pasien umum (Rp)','usg_fee'=>'Fee pasien USG (Rp)','homecare_clinic'=>'Homecare motor klinik (Rp)','homecare_own'=>'Homecare motor sendiri (Rp)'] as $key=>$label)<div class="field"><label>{{ $label }}</label><input class="input" type="number" min="0" name="{{ $key }}" value="{{ $v($key) }}" required></div>@endforeach
<div class="field"><label>Fee tindakan gigi (%) - default</label><input class="input" type="number" step="0.01" min="0" max="100" name="procedure_pct" value="{{ $v('procedure_pct',35) }}" required></div>
</div></div>
<div class="card" style="margin-bottom:18px">
 <div class="card-head"><div><h3>Tampilan Komponen Slip Dokter</h3>
  <p class="card-sub">Berlaku untuk dokter dan cabang ini. Hanya memengaruhi baris laporan PDF, bukan nominal honor atau perhitungan. Slip yang telah disetujui tetap memakai pengaturan yang tersimpan saat dibuat.</p></div></div>
 <div class="card-pad" style="display:grid;gap:17px">
 @foreach([
  'optional_zero'=>['Tampilkan komponen opsional Rp0','Gaji pokok, SIP, PJ, bonus, dan potongan bernilai nol. Default: disembunyikan.',0],
  'patient_zero'=>['Laporkan layanan pasien Rp0 yang relevan','Tampilkan rekap pasien BPJS/umum walaupun tidak ada fee; layanan yang tidak berlaku pada profil ini tetap disembunyikan.',1],
  'shift_zero'=>['Laporkan jadwal dengan transport Rp0','Jadwal praktik tetap muncul pada lampiran walaupun uang transportnya Rp0.',1],
 ] as $flag=>$detail)
 <div>
  <input type="hidden" name="slip_display[{{ $flag }}]" value="0">
  <label style="display:flex;align-items:center;gap:10px;font-weight:650">
   <input type="checkbox" class="doctor-slip-display" data-display-key="{{ $flag }}" name="slip_display[{{ $flag }}]" value="1" @checked((bool)old('slip_display.'.$flag,$r['slip_display'][$flag]??$detail[2]))>
   {{ $detail[0] }}
  </label>
  <small class="dr-help" style="display:block;margin-left:26px">{{ $detail[1] }}</small>
 </div>
 @endforeach
 </div>
</div>
<button type="submit" class="btn btn-primary">Simpan Profil Dokter</button>
</form>
@push('scripts')<script>
const doctorPresets=@json($presets);
document.getElementById('doctor-preset').addEventListener('change',e=>{
 const r=doctorPresets[e.target.value];if(!r)return;
 const hours=Object.fromEntries(Object.keys(r.shift_rates||{}).map(k=>[k,r.shift_hours?.[k]??r.full_hours??5]));
 const values={...r,shift_hours:hours,profile_label:r.label,patient_mode:r.patient_mode==='none'?'none':'all',poli2_included:r.poli2_included??20};
 // Memilih template tidak mengubah pengaturan visibilitas yang dipilih admin.
 Object.entries(values).forEach(([name,value])=>{
  if(name==='shift_rates')Object.entries(value).forEach(([shift,fee])=>{const el=document.querySelector(`[data-shift="${shift}"]`);if(el)el.value=fee;});
  else if(name==='shift_hours')Object.entries(value).forEach(([shift,hours])=>{const el=document.querySelector(`[data-shift-hours="${shift}"]`);if(el)el.value=hours;});
  else {const el=document.querySelector(`[name="${name}"]`);if(el)el.value=value;}
 });
});
</script>@endpush
@endsection
