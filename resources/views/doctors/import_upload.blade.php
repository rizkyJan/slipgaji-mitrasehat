@extends('layouts.app')
@section('title', 'Import Excel Dokter')
@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">HONOR DOKTER · OTOMATISASI</p>
        <h1 class="page-title">Import Excel Jadwal Dokter</h1>
        <p class="page-sub">Upload rekap jadwal dan jumlah pasien. Periksa datanya sebelum membuat draft slip honor.</p>
    </div>
    <a class="btn btn-outline" href="{{ route('doctors.index') }}">Kembali ke Honor Dokter</a>
</div>
<div class="card" style="margin-bottom:18px">
    <div class="card-head"><div><h3>1. Pilih file Excel</h3><p class="card-sub">Format .xlsx, maksimal 5 MB. Semua sheet dibaca dan jadwal digabung otomatis sesuai periode honor 26 bulan sebelumnya–25 bulan berjalan.</p></div></div>
    <div class="card-pad">
        <form method="POST" action="{{ route('doctors.import.upload') }}" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
            @csrf
            <div class="field" style="min-width:260px;flex:1">
                <label>File rekap jadwal dokter</label>
                <input class="input" type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </div>
            <button class="btn btn-primary" type="submit">Upload & Analisis Excel</button>
        </form>
        <p style="font-size:12px;color:#6b7d98;margin-top:12px">File asli tidak disimpan oleh fitur ini. Data jadwal hasil pembacaan disimpan terenkripsi untuk pemeriksaan internal. Formula Excel tidak dijalankan.</p>
    </div>
</div>
<div class="card" style="margin-bottom:18px">
    <div class="card-head"><h3>Alur import yang aman</h3></div>
    <div class="card-pad">
        <p style="margin:0 0 8px">1. Upload XLSX berisi satu atau lebih sheet → 2. Sistem menggabungkan tanggal 26–25 per dokter → 3. Koreksi jadwal, shift, pasien → 4. Pilih profil dokter + unit → 5. Buat draft honor → 6. Lengkapi tindakan/cash → 7. Setujui dan kirim lewat Gmail.</p>
        <p style="margin:0;color:#9d5910;font-size:13px"><strong>Penting:</strong> Excel jadwal hanya berisi BPJS, umum, dan kehadiran. Data tindakan, USG, SIP/PJ, potongan, dan cash tidak boleh ditebak oleh sistem. Cakupan tanggal harus diperiksa; bila satu tanggal ada di file, belum tentu semua shift di tanggal itu sudah tercatat.</p>
    </div>
</div>
<div class="card">
    <div class="card-head"><h3>Import terakhir milik akun ini</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>File</th><th>Tanggal upload</th><th>Jadwal</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($batches as $batch)
                <tr>
                    <td>{{ $batch->original_filename }}</td>
                    <td>{{ $batch->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ number_format($batch->row_count,0,',','.') }} baris</td>
                    <td><a class="btn btn-outline btn-sm" href="{{ route('doctors.import.review',$batch) }}">Lanjutkan Review</a></td>
                </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada file yang diimpor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
