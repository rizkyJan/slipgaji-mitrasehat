@can('manage-settings')
@php
    $workPeriodModel = $kind === 'doctor' ? $p : $payroll;
    [$periodFrom, $periodTo] = $kind === 'doctor'
        ? \App\Services\PayrollWorkPeriod::forDoctor($workPeriodModel)
        : \App\Services\PayrollWorkPeriod::forEmployee($workPeriodModel);
    $saveRoute = $kind === 'doctor'
        ? route('work-periods.doctor', $workPeriodModel)
        : route('work-periods.employee', $workPeriodModel);
@endphp
<div class="card" style="margin:18px 0">
    <div class="card-head"><h3>Rentang Periode Kerja</h3></div>
    <div class="card-pad">
        <p style="margin:0 0 10px">Periode gajian: <strong>{{ $workPeriodModel->period }}</strong> &nbsp; | &nbsp;
            Periode kerja di PDF: <strong>{{ \App\Services\PayrollWorkPeriod::nice($periodFrom) }} – {{ \App\Services\PayrollWorkPeriod::nice($periodTo) }}</strong>
        </p>
        <form method="post" action="{{ $saveRoute }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end" onsubmit="return confirm('Ubah tampilan tanggal periode kerja pada slip ini? Nominal tidak berubah.');">
            @csrf
            <label style="display:flex;flex-direction:column;gap:5px">Dari tanggal
                <input type="date" name="work_from" value="{{ old('work_from', $periodFrom) }}" required style="padding:9px;border:1px solid #cbd5e1;border-radius:8px"></label>
            <label style="display:flex;flex-direction:column;gap:5px">Sampai tanggal
                <input type="date" name="work_to" value="{{ old('work_to', $periodTo) }}" required style="padding:9px;border:1px solid #cbd5e1;border-radius:8px"></label>
            <button class="btn btn-primary" type="submit">Simpan Periode Kerja</button>
            <a class="btn btn-outline" href="{{ route('work-periods.index', ['period'=>$workPeriodModel->period]) }}">Atur untuk Beberapa Dokter / Karyawan</a>
        </form>
        @if($errors->has('work_from') || $errors->has('work_to'))
            <div class="alert alert-error" style="margin-top:12px">{{ $errors->first('work_from') ?: $errors->first('work_to') }}</div>
        @endif
        <p class="muted" style="margin-top:10px;font-size:12px">Khusus label tanggal pada PDF. Tidak mengubah periode gajian, perhitungan honor/gaji, PPh 21, cash/transfer, maupun data Analisis Excel. Edit pada slip yang telah disetujui akan terlihat pada PDF yang dicetak ulang, tetapi PDF lama yang sudah dikirim tidak berubah.</p>
    </div>
</div>
@endcan
