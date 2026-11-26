<?php
namespace App\Services;

use App\Models\DoctorPayroll;
use Carbon\Carbon;

final class DoctorFeeReportPdf
{
    /** PDF suplemen (bukan slip final / bukan QR). */
    public function render(DoctorPayroll $payroll, $days, array $totals): string
    {
        $pdf = new PdfCanvas();
        $name = (string)($payroll->assignment?->employee?->name ?? '-');
        [$start, $end] = DoctorPayrollPeriod::bounds((string)$payroll->period);
        $money = static fn(int $v): string => 'Rp'.number_format($v, 0, ',', '.');
        $date = static fn(string $d): string => Carbon::parse($d)->locale('id')->translatedFormat('d M Y');
        $y = 0;
        $page = 0;
        $header = function () use ($pdf, $payroll, $name, $start, $end, &$y, &$page): void {
            if ($page++ > 0) $pdf->newPage();
            $pdf->rgb(.07, .23, .53);
            $pdf->text(48, 790, 'LAMPIRAN REKONSILIASI FEE HARIAN', 12, true);
            $pdf->rgb(.27, .31, .39);
            $pdf->text(48, 767, 'Dokter: '.$name, 9, true);
            $pdf->text(48, 751, 'Periode: '.$payroll->period.' ('.$start.' s.d. '.$end.')', 8.5);
            $pdf->text(48, 735, 'Status slip: '.strtoupper((string)$payroll->status).' | Dicetak: '.now()->format('d/m/Y H:i'), 8.5);
            $pdf->rgb(.61, .67, .78); $pdf->line(48, 723, 547, 723, .6);
            $pdf->rgb(.08, .26, .56);
            $pdf->text(53, 700, 'TANGGAL PRAKTIK', 8, true);
            $pdf->text(317, 700, 'FEE', 8, true, 'right');
            $pdf->text(428, 700, 'DIBAYAR', 8, true, 'right');
            $pdf->text(543, 700, 'BELUM DIAMBIL', 8, true, 'right');
            $pdf->line(48, 688, 547, 688, .5);
            $y = 670;
        };
        $header();
        $shown = 0;
        foreach ($days as $day) {
            if (!in_array($day->reconciliation_status, ['unpaid', 'partial'], true)) continue;
            if ($y < 120) $header();
            $pdf->rgb(.19, .25, .34);
            $pdf->text(53, $y, $date($day->work_date->format('Y-m-d')), 9);
            $pdf->text(317, $y, $money((int)$day->fee_amount), 9, false, 'right');
            $pdf->text(428, $y, $money((int)$day->paid_amount), 9, false, 'right');
            $pdf->rgb(.08, .26, .56);
            $pdf->text(543, $y, $money((int)$day->remaining), 9, true, 'right');
            $y -= 25; $shown++;
        }
        if ($shown === 0) {
            $pdf->rgb(.30, .37, .46);
            $pdf->text(53, $y, 'Tidak ada fee tertunggak yang telah diverifikasi.', 9);
            $y -= 29;
        }
        if ($y < 140) $header();
        $pdf->rgb(.73, .78, .87); $pdf->line(48, $y + 7, 547, $y + 7, .6);
        $pdf->rgb(.08, .23, .52);
        $pdf->text(53, $y - 10, 'TOTAL BELUM DIAMBIL (TERVERIFIKASI)', 9.1, true);
        $pdf->text(543, $y - 10, $money((int)$totals['outstanding']), 9.5, true, 'right');
        $y -= 42;
        $pdf->rgb(.42, .47, .54);
        if ((int)$totals['pending'] > 0) {
            $pdf->text(53, $y, 'PERINGATAN: '.$totals['pending'].' tanggal belum diverifikasi; tidak dimasukkan dalam total.', 8);
            $y -= 20;
        }
        $pdf->text(53, $y, 'Pembayaran cash tercatat: '.$money((int)$totals['cash']).'; transfer tercatat: '.$money((int)$totals['transfer']).'.', 8);
        $y -= 22;
        $pdf->text(53, $y, 'Dokumen rekonsiliasi terpisah. Tidak mengubah nilai slip honor/QR yang telah terbit.', 7.6);
        $pdf->rgb(.45, .49, .56);
        $pdf->text(48, 42, 'Data berdasarkan konfirmasi admin dan transaksi yang dicatat, bukan status otomatis dari Excel.', 7.2);
        return $pdf->output();
    }
}
