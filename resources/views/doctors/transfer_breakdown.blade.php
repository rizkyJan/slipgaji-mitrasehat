@php
    $transferBreakdownReady = \Illuminate\Support\Facades\Schema::hasTable('doctor_transfer_breakdowns');
    $transferBreakdownRows = $transferBreakdownReady
        ? \Illuminate\Support\Facades\DB::table('doctor_transfer_breakdowns')
            ->where('doctor_payroll_id', $p->id)->orderBy('work_date')->orderBy('session')->get()
        : collect();
    [$transferWorkStart, $transferWorkEnd] = \App\Services\DoctorPayrollPeriod::bounds((string)$p->period);
@endphp
@if($transferBreakdownReady)
<div class="card" style="margin:18px 0">
 <div class="card-head"><h3>Fee Tidak Langsung (Rincian Transfer)</h3></div>
 <div class="card-pad">
  <p class="dr-help">Hari dan tanggal PRAKTIK, bukan tanggal cash diterima. Rincian ini hanya untuk lampiran PDF dan tidak mengubah hak dokter, PPh 21, cash, atau transfer.</p>
  @if($transferBreakdownRows->isNotEmpty())
   <div class="table-wrap"><table><thead><tr><th>Hari</th><th>Tanggal</th><th>Pasien BPJS</th><th>Pasien Umum</th></tr></thead><tbody>
    @foreach($transferBreakdownRows as $tr)
     <tr><td>{{ strtoupper(\Carbon\Carbon::parse($tr->work_date)->locale('id')->translatedFormat('l')) }} {{ $tr->session }}</td>
      <td>{{ \Carbon\Carbon::parse($tr->work_date)->format('d-m-Y') }}</td><td>{{ $tr->bpjs_count }}</td><td>{{ $tr->umum_count }}</td></tr>
    @endforeach
   </tbody></table></div>
  @else
   <p class="dr-help">Belum ada rincian tanggal praktik tersimpan. PDF tidak akan membuat tanggal atau jumlah pasien sendiri untuk mode rekap bulanan.</p>
  @endif
  @can('manage-settings')
    <div style="margin:10px 0 14px"><a class="btn btn-outline" href="{{ route('doctors.transfer_excel.preview', $p) }}">Ambil otomatis dari Arsip Analisis Excel</a> <small class="dr-help">Pratinjau dulu; tidak mengubah honor resmi.</small></div>
   <details style="margin-top:14px"><summary style="cursor:pointer;font-weight:600">Tambah / edit rincian Fee Tidak Langsung</summary>
    <form method="post" action="{{ route('doctors.transfer_breakdown.save', $p) }}" style="margin-top:12px">
     @csrf
     <p class="dr-help">Isi tanggal dari {{ $transferWorkStart }} sampai {{ $transferWorkEnd }}. Pilih PAGI/ SIANG/ MALAM. Bisa disimpan beberapa baris sekaligus; centang Hapus untuk menghapus baris lama.</p>
     <div class="table-wrap"><table><thead><tr><th>Tanggal praktik</th><th>Sesi</th><th>Pasien BPJS</th><th>Pasien Umum</th><th>Hapus</th></tr></thead><tbody>
      @foreach($transferBreakdownRows as $i => $tr)
       <tr><td><input type="hidden" name="rows[{{ $i }}][id]" value="{{ $tr->id }}"><input type="date" name="rows[{{ $i }}][date]" min="{{ $transferWorkStart }}" max="{{ $transferWorkEnd }}" value="{{ $tr->work_date }}" style="min-width:150px"></td>
        <td><select name="rows[{{ $i }}][session]">@foreach(['PAGI','SIANG','MALAM'] as $sess)<option value="{{ $sess }}" @selected($tr->session===$sess)>{{ $sess }}</option>@endforeach</select></td>
        <td><input type="number" name="rows[{{ $i }}][bpjs]" min="0" max="100000" value="{{ $tr->bpjs_count }}" style="width:100px"></td>
        <td><input type="number" name="rows[{{ $i }}][umum]" min="0" max="100000" value="{{ $tr->umum_count }}" style="width:100px"></td>
        <td><input type="checkbox" name="rows[{{ $i }}][remove]" value="1" aria-label="Hapus baris {{ $i + 1 }}"></td></tr>
      @endforeach
      @for($i = $transferBreakdownRows->count(); $i < $transferBreakdownRows->count()+10; $i++)
       <tr><td><input type="date" name="rows[{{ $i }}][date]" min="{{ $transferWorkStart }}" max="{{ $transferWorkEnd }}" style="min-width:150px"></td>
        <td><select name="rows[{{ $i }}][session]"><option value="PAGI">PAGI</option><option value="SIANG">SIANG</option><option value="MALAM">MALAM</option></select></td>
        <td><input type="number" name="rows[{{ $i }}][bpjs]" min="0" max="100000" value="0" style="width:100px"></td>
        <td><input type="number" name="rows[{{ $i }}][umum]" min="0" max="100000" value="0" style="width:100px"></td><td>-</td></tr>
      @endfor
     </tbody></table></div>
     @if($errors->has('rows'))<p style="color:#b42318">{{ $errors->first('rows') }}</p>@endif
     <button type="submit" class="btn btn-primary" style="margin-top:12px">Simpan Rincian</button>
    </form>
   </details>
  @endcan
 </div>
</div>

@else
<div class="card" style="margin:18px 0"><div class="card-pad">
<p class="dr-help">Tabel Fee Tidak Langsung belum disiapkan oleh migration. Perhitungan honor tidak berubah. Hubungi admin sistem untuk menjalankan migration yang ditentukan.</p>
</div></div>
@endif
