@extends('layouts.app')
@section('title', 'Penggajian')

@push('head')
<style>
/* Khusus halaman daftar slip; tidak mengubah CSS global Laravel. */
.payroll-list-filters{display:grid;grid-template-columns:minmax(155px,190px) minmax(175px,1fr) minmax(160px,190px) minmax(150px,180px) auto;gap:12px;align-items:end}
.payroll-list-filters .field{min-width:0}
.payroll-list-filters .field label{font-size:12px;font-weight:700;color:#294873}
.payroll-list-filters .input{width:100%;min-width:0}
.payroll-list-filter-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.payroll-list-summary-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;color:var(--muted);font-size:12px;margin:12px 0 0}
.payroll-list-summary-foot strong{color:#153e85}
.payroll-list-kpis .kpi{min-width:0}
.payroll-list-kpis .kpi b{font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
.payroll-list-unit{color:#6b7d98;font-size:12px;white-space:nowrap}
@media(max-width:1260px){.payroll-list-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.payroll-list-filter-actions{grid-column:1/-1}}
@media(max-width:650px){.payroll-list-filters{grid-template-columns:1fr}.payroll-list-filter-actions{grid-column:auto}.payroll-list-kpis .kpi b{font-size:22px}}
</style>
@endpush

@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">PERIODE PENGGAJIAN</p>
        <h1 class="page-title">Daftar Slip Gaji</h1>
        <p class="page-sub">Lihat jumlah slip, total gaji, dan filter berdasarkan unit serta status.</p>
    </div>
    <a href="{{ route('payrolls.create', ['period' => $period]) }}" class="btn btn-primary">＋ Proses Gaji Baru</a>
</div>

<div class="card">
    <div class="card-pad">
        <form method="get" action="{{ route('payrolls.index') }}" class="payroll-list-filters">
            <div class="field">
                <label for="filter-period">Periode</label>
                <input id="filter-period" name="period" type="month" class="input" value="{{ $period }}" required>
            </div>
            <div class="field">
                <label for="filter-search">Cari karyawan / NIP</label>
                <input id="filter-search" name="search" type="search" class="input" placeholder="Nama atau NIP pegawai" value="{{ $search }}">
            </div>
            <div class="field">
                <label for="filter-branch">Unit / Cabang</label>
                <select id="filter-branch" name="branch" class="input">
                    <option value="">Semua Unit</option>
                    @foreach($branches as $unit)
                        <option value="{{ $unit }}" @selected($branch === $unit)>{{ $unit }}</option>
                    @endforeach
                    @if($branch !== '' && !$branches->contains($branch))
                        <option value="{{ $branch }}" selected>{{ $branch }}</option>
                    @endif
                </select>
            </div>
            <div class="field">
                <label for="filter-status">Status Slip</label>
                <select id="filter-status" name="status" class="input">
                    <option value="" @selected($status === '')>Semua Status</option>
                    <option value="draft" @selected($status === 'draft')>Draft</option>
                    <option value="approved" @selected($status === 'approved')>Disetujui</option>
                </select>
            </div>
            <div class="payroll-list-filter-actions">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
                <a href="{{ route('payrolls.index', ['period' => $period]) }}" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="kpis payroll-list-kpis" aria-label="Ringkasan penggajian sesuai filter">
    <div class="kpi">
        <span class="kpi-icon">▤</span>
        <small>Jumlah Slip Gaji</small>
        <b>{{ number_format($stats->total_slips, 0, ',', '.') }}</b>
        <div class="kpi-foot">Total slip sesuai filter</div>
    </div>
    <div class="kpi">
        <span class="kpi-icon">◷</span>
        <small>Masih Draft</small>
        <b>{{ number_format($stats->draft_count, 0, ',', '.') }}</b>
        <div class="kpi-foot">Belum disetujui</div>
    </div>
    <div class="kpi">
        <span class="kpi-icon">✓</span>
        <small>Sudah Disetujui</small>
        <b>{{ number_format($stats->approved_count, 0, ',', '.') }}</b>
        <div class="kpi-foot">Siap diunduh / dikirim</div>
    </div>
    <div class="kpi">
        <span class="kpi-icon">Rp</span>
        <small>Total Gaji Bersih</small>
        <b style="font-size:22px">Rp{{ number_format($stats->net_total, 0, ',', '.') }}</b>
        <div class="kpi-foot">Akumulasi nominal hasil filter</div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h3>Rincian Slip Gaji</h3>
            <p class="card-sub">{{ number_format($stats->total_slips, 0, ',', '.') }} slip pada periode {{ $period }}{{ $branch !== '' ? ' • Unit: '.$branch : '' }}{{ $status !== '' ? ' • Status: '.($status === 'approved' ? 'Disetujui' : 'Draft') : '' }}</p>
        </div>
        <a href="{{ route('deliveries.index', ['period' => $period]) }}" class="btn btn-outline btn-sm">Pengiriman →</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Unit / Cabang</th>
                    <th>Periode</th>
                    <th>Status</th>
                    <th class="money">Penghasilan</th>
                    <th class="money">Potongan</th>
                    <th class="money">Diterima</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payrolls as $p)
                    <tr>
                        <td class="name-cell">
                            <strong>{{ $p->employee->name ?? 'Karyawan tidak ditemukan' }}</strong>
                            <small>{{ $p->employee->employee_code ?? '-' }}</small>
                        </td>
                        <td><span class="payroll-list-unit">{{ $p->employee?->branch ?: '-' }}</span></td>
                        <td>{{ $p->period }}</td>
                        <td>
                            <span class="tag {{ $p->status === 'approved' ? 'tag-green' : 'tag-amber' }}">
                                {{ $p->status === 'approved' ? 'Disetujui' : 'Draft' }}
                            </span>
                        </td>
                        <td class="money">{{ number_format($p->calculation_snapshot['income_total'] ?? 0, 0, ',', '.') }}</td>
                        <td class="money">{{ number_format($p->calculation_snapshot['employee_deduction_total'] ?? 0, 0, ',', '.') }}</td>
                        <td class="money"><strong>Rp{{ number_format($p->net_total, 0, ',', '.') }}</strong></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-secondary btn-sm" href="{{ route('payrolls.show', $p) }}">Detail</a>
                                @if($p->status === 'draft')
                                    <a class="btn btn-outline btn-sm" href="{{ route('payrolls.edit', $p) }}">Edit</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty"><b>Tidak ada slip gaji sesuai filter</b>Coba ubah periode, unit, status, atau kata pencarian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">@include('partials.pagination', ['paginator' => $payrolls])</div>
    <div class="card-pad" style="padding-top:4px">
        <div class="payroll-list-summary-foot">
            <div>Nominal <strong>disetujui: Rp{{ number_format($stats->approved_net, 0, ',', '.') }}</strong></div>
            <div>Nominal <strong>draft: Rp{{ number_format($stats->draft_net, 0, ',', '.') }}</strong></div>
            <div>Ringkasan mencakup seluruh hasil filter, bukan hanya halaman ini.</div>
        </div>
    </div>
</div>
@endsection
