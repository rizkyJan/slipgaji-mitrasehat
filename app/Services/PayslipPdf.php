<?php
namespace App\Services;

use App\Models\AppSetting;
use App\Models\Payroll;
use Carbon\Carbon;

class PayslipPdf
{
    public function render(Payroll $payroll, ?array $settings = null): string
    {
        $verifier = app(SlipVerification::class);
        if ($payroll->status === 'approved') {
            $payroll = $verifier->issue($payroll);
            $verifier->assertIntact($payroll);
        }
        $pdf = new PdfCanvas();
        $setting = fn (string $key, string $default): string => $settings !== null && array_key_exists($key, $settings)
            ? (string) $settings[$key] : AppSetting::setting($key, $default);
        $c = $payroll->calculation_snapshot;
        $e = $c['employee'];
        $period = Carbon::createFromFormat('!Y-m', $payroll->period)->locale('id')->translatedFormat('F Y');
        $clinic = $setting('clinic_name', 'PT. MITRA SEHAT MAJU SENTOSA');
        $address = $setting('clinic_address', 'Sukoharjo, Jawa Tengah');
        $style = $setting('slip_style', 'classic');
        // Kop resmi PT. Mitra Sehat Maju Sentosa (logo PT. MSMS).
        $pdf->jpeg(public_path('images/logo-pdf.jpg'), 48, 769, 67, 67);
        $pdf->rgb(.08,.23,.52);
        $pdf->text(128, 804, strtoupper($clinic), 12.5, true);
        $pdf->text(128, 787, $address, 9);
        $pdf->rgb(.13,.19,.31);
        $pdf->text(548, 798, 'SLIP GAJI', 17, true, 'right');
        $pdf->line(48, 763, 548, 763, 1.3);
        $y = 744;
        $pdf->text(48, $y, 'Periode', 9);
        $pdf->text(136, $y, ': '.ucwords($period), 9, true);
        $pdf->text(323, $y, 'Karyawan', 9);
        $pdf->text(414, $y, ': '.$e['name'], 9, true);
        $y -= 18;
        $pdf->text(48, $y, 'NIP Pegawai', 9);
        $pdf->text(136, $y, ': '.$e['code'], 9);
        $pdf->text(323, $y, 'Unit / Cabang', 9);
        $branch = (string)($e['branch'] ?? $payroll->employee?->branch ?? '-');
        if (strlen($branch) > 34) $branch = substr($branch, 0, 31).'...';
        $pdf->text(414, $y, ': '.$branch, 9);
        $y -= 18;
        $pdf->text(48, $y, 'Pembayaran', 9);
        $pdf->text(136, $y, ': '.ucfirst($c['payment_method']), 9);
        $pdf->text(323, $y, 'Jabatan', 9);
        $pdf->text(414, $y, ': '.($e['position'] ?: '-'), 9);
        $y -= 18;
        $pdf->rgb(.08,.23,.52);
        $pdf->text(48, $y, 'No. Slip', 8.8);
        $pdf->text(136, $y, ': '.($payroll->slip_number ?: 'DRAFT - BELUM SAH'), 8.8, true);
        $pdf->rgb(.17,.20,.18);
        $y -= 24;

        $line = function (string $label, int $amount, bool $total = false) use ($pdf, &$y): void {
            if ($y < 140) {
                $pdf->rgb(.55,.60,.58);
                $pdf->text(48, 36, 'LANJUTAN PADA HALAMAN BERIKUTNYA', 8);
                $pdf->newPage();
                $pdf->rgb(.08,.23,.52);
                $pdf->text(48, 800, 'SLIP GAJI - LANJUTAN', 13, true);
                $pdf->rgb(.20,.20,.20);
                $pdf->line(48, 788, 548, 788, .6);
                $y = 763;
            }
            if ($total) { $pdf->rgb(.4,.4,.4); $pdf->line(315,$y+8,548,$y+8,.4); $pdf->rgb(.12,.22,.44); }
            $pdf->text(55, $y, $label, $total ? 10 : 9, $total);
            $pdf->text(548, $y, number_format($amount, 0, ',', '.'), $total ? 10 : 9, $total, 'right');
            $y -= $total ? 25 : 17;
        };
        $heading = function (string $title) use ($pdf, &$y): void {
            $pdf->rgb(.89,.94,1);
            $pdf->rectangle(48, $y-7, 500, 23, true);
            $pdf->rgb(.08,.27,.65);
            $pdf->text(56,$y+1,strtoupper($title),10,true);
            $y-=29;
            $pdf->rgb(.17,.20,.18);
        };

        $heading('Penerimaan');
        foreach ($c['income'] as $label => $amount) $line($label, (int) $amount);
        if ($style === 'classic') {
            if (!empty($c['employer_contributions']['BPJS Kesehatan Perusahaan'])) $line('BPJS Kesehatan Perusahaan', (int) $c['employer_contributions']['BPJS Kesehatan Perusahaan']);
            $tkEmployer = (int) array_sum(array_diff_key($c['employer_contributions'], array_flip(['BPJS Kesehatan Perusahaan'])));
            if ($tkEmployer) $line('BPJS Ketenagakerjaan Perusahaan', $tkEmployer);
            $line('Total Penghasilan Bruto', (int) $c['gross_total'], true);
        } else {
            $line('Total Penghasilan Tunai', (int) $c['income_total'], true);
        }
        $heading('Pengurangan');
        if ($style === 'classic') {
            if (!empty($c['employer_contributions']['BPJS Kesehatan Perusahaan'])) $line('Iuran BPJS Kesehatan Perusahaan', (int) $c['employer_contributions']['BPJS Kesehatan Perusahaan']);
            if ($tkEmployer) $line('Iuran BPJS Ketenagakerjaan Perusahaan', $tkEmployer);
        }
        foreach ($c['employee_deductions'] as $label => $amount) $line($label, (int) $amount);
        if (!array_key_exists('PPh 21', $c['employee_deductions'])) $line('PPh 21', 0);
        $line('Total Pengurangan', (int) ($style === 'classic' ? $c['deduction_total'] : $c['employee_deduction_total']), true);
        if ($style !== 'classic') {
            $heading('Iuran Ditanggung Klinik (Bukan Potongan Pegawai)');
            foreach ($c['employer_contributions'] as $label => $amount) $line($label, (int) $amount);
        }
        if ($y < 195) {
            $pdf->newPage();
            $pdf->rgb(.08,.23,.52);
            $pdf->text(48, 800, 'SLIP GAJI - RINGKASAN', 13, true);
            $pdf->line(48, 787, 548, 787, .6);
            $y = 752;
        }
        $pdf->rgb(.09,.28,.66);
        $pdf->rectangle(48, $y-10, 500, 34, true);
        $pdf->rgb(1,1,1);
        $pdf->text(60, $y+1, 'TOTAL DITERIMA KARYAWAN', 12, true);
        $pdf->text(538, $y+1, 'Rp '.number_format((int)$c['net_total'], 0, ',', '.'), 13, true, 'right');
        $y -= 38;
        $pdf->rgb(.17,.20,.18);
        $words = Terbilang::rupiah((int)$c['net_total']);
        foreach ($this->wrap($words, 92) as $str) {
            $pdf->text(48, $y, '# Terbilang: '.$str, 8.4, false);
            $y -= 13;
        }
        $y -= 9;
        $pdf->rgb(.45,.46,.46);
        $pdf->line(48,$y,548,$y,.7);
        $date = ($payroll->approved_at ?? now())->locale('id')->translatedFormat('d F Y');
        $pdf->text(548, $y-18, 'Sukoharjo, '.$date, 9, false, 'right');
        if ($payroll->status === 'approved') {
            $qrUrl = $verifier->publicUrl($payroll);
            $pdf->qr($qrUrl, 48, $y - 122, 87);
            $pdf->rgb(.08,.23,.52);
            $pdf->text(145, $y - 42, 'VERIFIKASI KEASLIAN', 9.3, true);
            $pdf->rgb(.35,.38,.44);
            $pdf->text(145, $y - 58, 'Pindai QR untuk melihat', 8);
            $pdf->text(145, $y - 70, 'nominal resmi di server.', 8);
            $pdf->text(145, $y - 84, 'Cocokkan sebelum digunakan.', 8);
        }
        $pdf->rgb(.55,.55,.55);
        $pdf->text(48, 43, 'Diterbitkan oleh sistem Mitra Sehat Payroll. Dokumen bersifat rahasia.', 7.5);
        if ($payroll->status !== 'approved') $pdf->text(548,43,'DRAFT - BELUM SAH',8,true,'right');
        return $pdf->output();
    }

    private function wrap(string $words, int $limit): array
    {
        return explode("\n", wordwrap($words, $limit, "\n", false));
    }
}
