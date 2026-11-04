<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet"><meta name="referrer" content="no-referrer">
<title>Verifikasi Slip Gaji | PT. Mitra Sehat Maju Sentosa</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fc;color:#162949;font:15px/1.65 Arial,Helvetica,sans-serif}.wrap{max-width:650px;margin:38px auto;padding:0 18px}.panel{background:#fff;border:1px solid #dce5f6;border-radius:18px;box-shadow:0 12px 35px #18345b0a;padding:30px}.brand{display:flex;gap:16px;align-items:center;border-bottom:2px solid #243c9a;padding-bottom:18px}.brand img{width:66px;height:66px;object-fit:contain}.brand h1{font-size:17px;margin:0;color:#27388c;line-height:1.3}.brand p{margin:3px 0 0;color:#697792;font-size:12px}.status{margin:22px 0;padding:14px;border-radius:10px;background:#e7f8ee;color:#16613e;font-weight:bold}.status.bad{background:#fff0ee;color:#aa2929}.number{background:#f3f6fc;border-radius:8px;padding:12px;margin-bottom:18px;font-weight:bold;overflow-wrap:anywhere}.line{display:flex;justify-content:space-between;gap:20px;padding:9px 0;border-bottom:1px solid #e9eef7}.line span{color:#56647e}.line b{text-align:right}.net{font-size:23px;color:#283d9d}.note{font-size:13px;color:#64758f;margin-top:22px}details{margin-top:16px;padding:12px;border:1px solid #dbe5f7;border-radius:10px}summary{font-weight:bold;cursor:pointer;color:#283d9d}details h3{font-size:13px;color:#62718f;margin:14px 0 4px}details .line{font-size:13px;padding:5px 0}.footer{font-size:12px;color:#6f7b93;text-align:center;margin:20px 0}@media(max-width:480px){.panel{padding:20px}.line{font-size:14px}.brand h1{font-size:15px}}
</style></head><body><main class="wrap"><section class="panel"><div class="brand"><img src="{{ asset('images/logo-klinik-mitra-sehat.png') }}" alt="Logo perusahaan"><div><h1>PT. MITRA SEHAT MAJU SENTOSA</h1><p>Portal verifikasi resmi slip gaji</p></div></div>
@if($valid)
<div class="status">✓ SLIP TERVERIFIKASI — DATA RESMI DAN UTUH</div>
<div class="number">No. Slip: {{ $payroll->slip_number }}</div>
<div class="line"><span>Nama karyawan</span><b>{{ $data['employee']['name'] ?? '-' }}</b></div>
<div class="line"><span>NIP Pegawai</span><b>{{ $data['employee']['code'] ?? '-' }}</b></div>
<div class="line"><span>Unit / Cabang</span><b>{{ $data['employee']['branch'] ?? '-' }}</b></div>
<div class="line"><span>Periode gaji</span><b>{{ \Carbon\Carbon::createFromFormat('!Y-m',$payroll->period)->locale('id')->translatedFormat('F Y') }}</b></div>
<div class="line"><span>Penghasilan bruto pada slip</span><b>Rp {{ number_format($payroll->gross_total,0,',','.') }}</b></div>
<div class="line"><span>Total pengurangan pada slip</span><b>Rp {{ number_format($payroll->deduction_total,0,',','.') }}</b></div>
<div class="line"><span>Gaji bersih resmi</span><b class="net">Rp {{ number_format($payroll->net_total,0,',','.') }}</b></div>
<div class="line"><span>Tanggal disetujui</span><b>{{ $payroll->approved_at?->locale('id')->translatedFormat('d F Y H:i') ?: '-' }}</b></div>
<details><summary>Lihat rincian penggajian resmi</summary>
<h3>Penerimaan Tunai</h3>
@foreach($data['income'] ?? [] as $label => $amount)
<div class="line"><span>{{ preg_replace('/ \(#[0-9]+\)$/','',$label) }}</span><b>Rp {{ number_format($amount,0,',','.') }}</b></div>
@endforeach
<h3>Iuran Perusahaan yang Dicatat pada Slip</h3>
@foreach($data['employer_contributions'] ?? [] as $label => $amount)
<div class="line"><span>{{ $label }}</span><b>Rp {{ number_format($amount,0,',','.') }}</b></div>
@endforeach
<h3>Potongan Pegawai</h3>
@foreach($data['employee_deductions'] ?? [] as $label => $amount)
<div class="line"><span>{{ preg_replace('/ \(#[0-9]+\)$/','',$label) }}</span><b>Rp {{ number_format($amount,0,',','.') }}</b></div>
@endforeach
</details>
<p class="note"><strong>Untuk pihak pemeriksa:</strong> cocokkan NIP, periode, nomor slip, dan terutama <strong>gaji bersih yang tertera di sini</strong> dengan dokumen PDF/kertas. Bila berbeda, dokumen yang diajukan kemungkinan telah diubah dan jangan dianggap sah. Halaman ini hanya dapat diakses oleh pemegang tautan/QR slip; harap jaga kerahasiaannya.</p>
@else
<div class="status bad">✕ SLIP TIDAK VALID — INTEGRITAS GAGAL</div>
<p>Data slip tidak sesuai dengan catatan saat diterbitkan. Dokumen ini tidak dapat digunakan sebagai bukti penggajian yang sah. Hubungi administrator PT. Mitra Sehat Maju Sentosa untuk pemeriksaan.</p>
@endif
</section><p class="footer">Mitra Sehat Payroll • Verifikasi langsung dari server resmi perusahaan</p></main></body></html>
