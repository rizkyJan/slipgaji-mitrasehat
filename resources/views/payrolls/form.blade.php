@extends('layouts.app')
@section('title', isset($payroll) ? 'Edit Draft' : 'Input Penggajian')
@section('content')
<div class="page-head"><div><p class="eyebrow">PENGGAJIAN KARYAWAN</p><h1 class="page-title">{{ isset($payroll) ? 'Edit Draft Gaji' : 'Input Gaji Bulanan' }}</h1><p class="page-sub">Pilih karyawan → salin gaji sebelumnya → periksa perhitungan otomatis → simpan draft.</p></div><a href="{{ route('payrolls.index',['period'=>$period]) }}" class="btn btn-secondary">← Daftar Slip</a></div>
<div class="card card-pad"><h3 class="section-title">1. Pilih Karyawan & Periode</h3>
<div class="form-grid"><div class="field"><label>Karyawan</label><select id="employeeSelect" class="input" @if(isset($payroll)) disabled @endif><option value="">— Pilih karyawan —</option>@foreach($employees as $item)<option value="{{ $item->id }}" @selected($employee && $employee->id===$item->id)>{{ $item->employee_code }} — {{ $item->name }}</option>@endforeach</select></div><div class="field"><label>Periode Gaji</label><input class="input" type="month" id="periodSelect" value="{{ $period }}" @if(isset($payroll)) disabled @endif></div></div>
@if($employee)
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px;flex-wrap:wrap"><span class="subtle">Jabatan: <strong>{{ $employee->position ?: 'Belum ditentukan' }}</strong> · Email: {{ $employee->email ?: 'Belum diisi' }}</span>
@if(!isset($payroll))
@if($last)<div class="actions"><a href="{{ route('payrolls.create',['employee_id'=>$employee->id,'period'=>$period,'copy'=>1]) }}" class="btn btn-primary">⧉ Gunakan Gaji Sebelumnya ({{ $last->period }})</a><a href="{{ route('payrolls.create',['employee_id'=>$employee->id,'period'=>$period,'copy'=>'recurring']) }}" class="btn btn-secondary">Salin Komponen Rutin</a></div>
@else<span class="tag tag-gray">Belum ada riwayat sebelumnya</span>@endif
@endif</div>
@endif
</div>
@if(!$employee)<div class="card"><div class="empty" style="padding:65px 20px"><div style="font-size:40px;margin-bottom:10px">▤</div><b>Mulai dari karyawan</b>Pilih karyawan dan periode gaji pada formulir di atas.</div></div>
@else
@php $inputData = old('base_salary') !== null ? array_merge($input, old()) : $input; $editing = isset($payroll); @endphp
<form id="salaryForm" method="post" action="{{ $editing ? route('payrolls.update',$payroll) : route('payrolls.store') }}">@csrf @if($editing) @method('put') @endif
<input type="hidden" name="employee_id" value="{{ $employee->id }}"><input type="hidden" name="period" value="{{ $period }}">
<div class="two-cols"><div>
<div class="card card-pad"><h3 class="section-title">2. Rincian Penghasilan</h3>
<div class="form-grid cols3"><div class="field"><label>Gaji Pokok (Rp)</label><input class="input auto-preview" type="number" min="0" name="base_salary" value="{{ $inputData['base_salary'] ?? 0 }}" required></div>
<div class="field"><label>Tunjangan Tetap (Rp)</label><input class="input auto-preview" type="number" min="0" name="fixed_allowance" value="{{ $inputData['fixed_allowance'] ?? 0 }}" required></div>
<div class="field"><label>Uang Makan (Rp)</label><input class="input auto-preview" type="number" min="0" name="meal_allowance" value="{{ $inputData['meal_allowance'] ?? 0 }}" required></div></div>
<div class="form-note">Uang makan {{ $employee->meal_is_fixed?'dihitung sebagai tunjangan tetap':'tidak termasuk tunjangan tetap' }} untuk dasar BPJS sesuai pengaturan karyawan.</div>
<div class="divider"></div><h3 class="section-title">3. Bonus, Tunjangan Tambahan & Potongan</h3><p class="muted" style="margin-top:-9px">Boleh tambah, edit, atau hapus baris secara bebas. Komponen sebelumnya ikut disalin jika memakai tombol "Gunakan Gaji Sebelumnya".</p>
<div id="extraRows"></div><div class="actions" style="margin-top:13px"><button type="button" class="btn btn-outline" onclick="addExtra('income')">＋ Tambah Bonus/Penerimaan</button><button type="button" class="btn btn-secondary" onclick="addExtra('deduction')">＋ Tambah Potongan</button></div>
</div>
<div class="card card-pad"><h3 class="section-title">4. Dasar Perhitungan BPJS</h3><div class="form-grid">
<div class="field"><label>Dasar Upah BPJS TK yang Dilaporkan (Rp)</label><input class="input auto-preview" type="number" min="0" name="reported_tk_wage" value="{{ $inputData['reported_tk_wage'] ?? '' }}" placeholder="Otomatis bila dikosongkan"><p class="form-note">Isi jika memang berbeda dan sesuai data terlapor ke BPJS TK.</p></div>
<div class="field"><label>Dasar BPJS Kesehatan terlapor (opsional, Rp)</label><input class="input auto-preview" name="reported_health_wage" type="number" min="0" value="{{ $inputData['reported_health_wage'] ?? '' }}" placeholder="Otomatis dari UMK / upah"><p class="form-note">Bila ada selisih pembulatan sesuai iuran BPJS yang dilaporkan.</p></div>
<div class="field"><label>Tambahan Anggota Keluarga BPJS</label><input class="input auto-preview" name="family_extra" type="number" min="0" max="15" value="{{ $inputData['family_extra'] ?? 0 }}" required><p class="form-note">Isi hanya anggota tambahan yang terdaftar. Suami/istri dan maksimal 3 anak sesuai ketentuan PPU tidak dikenai tambahan iuran.</p></div>
<div class="field full"><label>PPh 21 (Rp) — kosong = otomatis</label><input class="input auto-preview" type="number" min="0" name="pph21_override" value="{{ $inputData['pph21_override'] ?? '' }}" placeholder="Otomatis berdasarkan TER; isi bila dihitung manual"><p class="form-note">Status PTKP: <strong>{{ $employee->pph21_ptkp ?: 'TK/0' }}</strong> · Mode: <strong>{{ $employee->pph21_mode ?: 'auto' }}</strong>. Untuk Desember (masa pajak terakhir), wajib isi hasil rekonsiliasi PPh tahunan, termasuk 0 jika benar-benar nihil.</p></div>
<div class="field"><label>Metode Pembayaran</label><select name="payment_method" class="input auto-preview"><option value="transfer" @selected(($inputData['payment_method']??'transfer')==='transfer')>Transfer</option><option value="tunai" @selected(($inputData['payment_method']??'transfer')==='tunai')>Tunai</option></select></div>
<div class="field"><label>Tanggal Pembayaran (opsional)</label><input class="input" type="date" name="paid_at" value="{{ old('paid_at', isset($payroll)?$payroll->paid_at?->format('Y-m-d'):null) }}"></div>
<div class="field full"><label>Catatan Payroll</label><textarea class="input" name="notes" placeholder="Opsional...">{{ old('notes',isset($payroll)?$payroll->notes:'') }}</textarea></div>
</div></div>
</div>
<div><div class="card card-pad" style="position:sticky;top:92px"><h3 class="section-title">Pratinjau Perhitungan Otomatis</h3><div class="form-note">Semua hitungan dibuat ulang di server setiap nilai berubah.</div><div id="previewStatus" class="alert alert-warning" style="margin-top:15px">Menghitung...</div>
<div id="previewBody" hidden><h4 style="margin-bottom:8px">Penerimaan</h4><div id="previewIncome"></div><div class="divider"></div><h4 style="margin-bottom:8px">Potongan Pegawai</h4><div id="previewDeduction"></div><div class="divider"></div><div class="summary-row"><span>Iuran Ditanggung Klinik</span><strong id="previewEmployer"></strong></div><div class="summary-row"><span>Dasar BPJS Kesehatan</span><strong id="previewHealthBase"></strong></div><div class="summary-row"><span>Dasar BPJS TK</span><strong id="previewTkBase"></strong></div><div class="summary-row"><span>Dasar PPh 21 (Bruto Pajak)</span><strong id="previewTaxGross"></strong></div><div class="summary-row"><span>Tarif / Metode PPh 21</span><strong id="previewTaxMethod"></strong></div><div class="summary-row"><span>UMK periode ini</span><strong id="previewUmk"></strong></div><div class="summary-box" style="margin-top:18px"><small>GAJI BERSIH DITERIMA</small><b id="previewNet">Rp0</b><small>Penghasilan tunai dikurangi potongan pegawai</small></div><div id="previewWarnings" style="margin-top:16px"></div></div>
<div class="form-actions"><a class="btn btn-secondary" href="{{ route('payrolls.index') }}">Batal</a><button class="btn btn-primary" type="submit" id="saveSalary">Simpan Draft Gaji</button></div><p class="footnote">Draft dapat diedit atau dihapus. Setelah disetujui, nilainya dikunci.</p></div></div></div>
</form>
@endif
@endsection
@push('head')
<style>
/* R3.2: hanya tata letak komponen bonus/potongan pada input penggajian. */
#salaryForm .item-grid {
    grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
}
#salaryForm .item-grid > .field { min-width: 0; }
#salaryForm .item-grid > .extra-recurring { grid-column: 1 / 3; align-self: center; }
#salaryForm .item-grid > .extra-remove { grid-column: 3; justify-self: end; }
@media (max-width: 1180px) {
    #salaryForm .item-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    #salaryForm .item-grid > .extra-label-field { grid-column: 1 / -1; }
    #salaryForm .item-grid > .extra-recurring { grid-column: 1; }
    #salaryForm .item-grid > .extra-remove { grid-column: 2; }
}
@media (max-width: 540px) {
    #salaryForm .item-grid { grid-template-columns: minmax(0, 1fr); }
    #salaryForm .item-grid > .extra-label-field,
    #salaryForm .item-grid > .extra-recurring,
    #salaryForm .item-grid > .extra-remove { grid-column: 1; }
    #salaryForm .item-grid > .extra-remove { justify-self: start; }
}
</style>
@endpush
@push('scripts')
<script>
const selector = document.getElementById('employeeSelect');
const periodSelector = document.getElementById('periodSelect');
function chooseEmployee(){ if(!selector || !periodSelector || selector.disabled) return;const params=new URLSearchParams({employee_id:selector.value,period:periodSelector.value});location.href='{{ route('payrolls.create') }}?'+params.toString() }
selector?.addEventListener('change',chooseEmployee);periodSelector?.addEventListener('change',chooseEmployee);
@if($employee)
const form = document.getElementById('salaryForm');
const extraRows = document.getElementById('extraRows');
const initialExtras = @json(old('extras', $inputData['extras'] ?? []));
let extraIndex = 0, calcTimer, calcToken=0;
const money = n=>'Rp'+Number(n||0).toLocaleString('id-ID');
function addExtra(type='income', label='', amount='', recurring=false){
    if(extraRows.children.length>=30){alert('Maksimal 30 komponen tambahan.');return;}
    const id=extraIndex++;
    const row=document.createElement('div');row.className='item-box';row.dataset.extra=id;
    row.innerHTML=`<div class="item-grid"><div class="field extra-label-field"><label>Keterangan</label><input name="extras[${id}][label]" class="input auto-preview" maxlength="90" required placeholder="Nama bonus / potongan"></div><div class="field"><label>Jenis</label><select name="extras[${id}][type]" class="input auto-preview"><option value="income">Penerimaan (+)</option><option value="deduction">Potongan (-)</option></select></div><div class="field"><label>Nominal (Rp)</label><input name="extras[${id}][amount]" type="number" class="input auto-preview" min="0" required></div><label class="check extra-recurring"><input name="extras[${id}][recurring]" type="checkbox" value="1"> Rutin (salin bulan depan)</label><button type="button" class="btn btn-danger btn-sm extra-remove" onclick="this.closest('.item-box').remove(); scheduleCalc()">Hapus</button></div>`;
    const description=row.querySelector('[name$="[label]"]');
    const kind=row.querySelector('[name$="[type]"]');
    const nominal=row.querySelector('[name$="[amount]"]');
    const suggested=type==='deduction'?'Potongan':'Bonus';
    // Ini NILAI input sungguhan, bukan placeholder. Backend mewajibkan extras.*.label.
    description.value=String(label??'').trim() || suggested;
    kind.value=type;
    nominal.value=amount === '' || amount == null ? '0' : amount;
    row.querySelector('[name$="[recurring]"]').checked=!!recurring;
    // Jika pengguna mengubah jenis tanpa mengubah keterangan bawaan, sesuaikan otomatis.
    kind.addEventListener('change',()=>{
        if(description.value==='Bonus' || description.value==='Potongan'){
            description.value=kind.value==='deduction'?'Potongan':'Bonus';
        }
    });
    // Mempermudah pengetikan nama dan nominal tanpa harus menghapus contoh bawaan.
    description.addEventListener('focus',()=>{
        if(description.value==='Bonus' || description.value==='Potongan')description.select();
    });
    nominal.addEventListener('focus',()=>{
        if(nominal.value==='0')nominal.select();
    });
    extraRows.appendChild(row);scheduleCalc();
}
function rowsToHtml(node,data){node.replaceChildren();Object.entries(data).forEach(([name,value])=>{const r=document.createElement('div');r.className='summary-row';const title=document.createElement('span');title.textContent=name.replace(/ \(#\d+\)$/,'');const amount=document.createElement('strong');amount.textContent=money(value);r.append(title,amount);node.appendChild(r)});if(!Object.keys(data).length)node.textContent='—';}
async function recalc(){
    const ticket=++calcToken;
    const status=document.getElementById('previewStatus');const panel=document.getElementById('previewBody');
    const params=new FormData(form);
    try{
        const response=await fetch(@json(route('payrolls.preview', [], false)), {method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:params,credentials:'same-origin'});
        const result=await response.json();
        if(ticket!==calcToken)return;
        if(!response.ok){const messages=result.errors?Object.values(result.errors).flat().join(' '):result.message||'Tidak dapat menghitung.';throw new Error(messages)}
        status.hidden=true;panel.hidden=false;
        rowsToHtml(document.getElementById('previewIncome'),result.income);
        rowsToHtml(document.getElementById('previewDeduction'),result.employee_deductions);
        document.getElementById('previewEmployer').textContent=money(result.employer_total);
        document.getElementById('previewHealthBase').textContent=money(result.bases.health_wage);
        document.getElementById('previewTkBase').textContent=money(result.bases.tk_wage);
        document.getElementById('previewUmk').textContent=money(result.minimum_wage.amount);
        document.getElementById('previewTaxGross').textContent=money(result.tax.taxable_gross);
        document.getElementById('previewTaxMethod').textContent=result.tax.method + (result.tax.method.startsWith('TER') ? ' ('+result.tax.rate+'%)' : '');
        document.getElementById('previewNet').textContent=money(result.net_total);
        const warn=document.getElementById('previewWarnings');warn.replaceChildren();result.warnings.forEach(w=>{const p=document.createElement('div');p.className='alert alert-warning';p.textContent=w;warn.appendChild(p)});
        document.getElementById('saveSalary').disabled=false;
    }catch(e){if(ticket!==calcToken)return;status.hidden=false;status.textContent=e.message;status.className='alert alert-error';panel.hidden=true;document.getElementById('saveSalary').disabled=true;}
}
function scheduleCalc(){clearTimeout(calcTimer);calcTimer=setTimeout(recalc,280)}
form?.addEventListener('input',scheduleCalc);form?.addEventListener('change',scheduleCalc);
for(const row of (Array.isArray(initialExtras)?initialExtras:Object.values(initialExtras))){addExtra(row.type,row.label,row.amount,row.recurring)}
recalc();
@endif
</script>
@endpush
