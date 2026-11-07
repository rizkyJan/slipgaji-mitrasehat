<?php

namespace App\Services;

use DateTimeImmutable;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/** Read-only .xlsx scanner for doctor-shift workbooks. Does not execute formulas or macros. */
class DoctorExcelReader
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function parse(string $filename): array
    {
        if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
            throw new RuntimeException('PHP harus mengaktifkan extension zip dan simplexml untuk membaca XLSX.');
        }
        $zip = new ZipArchive();
        if ($zip->open($filename) !== true) {
            throw new RuntimeException('File bukan XLSX yang valid atau arsipnya rusak.');
        }
        try {
            $uncompressed = 0;
            if ($zip->numFiles > 2000) {
                throw new RuntimeException('XLSX memiliki terlalu banyak bagian.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $uncompressed += (int)($stat['size'] ?? 0);
                if ($uncompressed > 25 * 1024 * 1024) {
                    throw new RuntimeException('Isi XLSX terlalu besar (maksimum 25 MB tidak terkompresi).');
                }
                $name = strtolower((string)($stat['name'] ?? ''));
                if (str_contains($name, 'vbaproject.bin') || str_ends_with($name, '.xlsm')) {
                    throw new RuntimeException('Workbook bermakro tidak diterima. Gunakan XLSX tanpa makro.');
                }
            }
            $strings = $this->strings($zip);
            $sheets = $this->sheets($zip);
            $all = [];
            $bySheet = [];
            $seen = [];
            foreach ($sheets as $sheet) {
                $xml = $this->xml($zip, $sheet['path']);
                $records = [];
                $columns = null;
                foreach ($xml->sheetData->row as $row) {
                    $n = (int)$row['r'];
                    $values = [];
                    foreach ($row->c as $cell) {
                        $ref = (string)$cell['r'];
                        if (!preg_match('/^([A-Z]+)\d+$/i', $ref, $m)) continue;
                        $column = strtoupper($m[1]);
                        $type = (string)$cell['t'];
                        $value = (string)($cell->v ?? '');
                        if ($type === 's') $value = $strings[(int)$value] ?? '';
                        elseif ($type === 'inlineStr') $value = (string)($cell->is->t ?? '');
                        elseif ($type === 'str') $value = (string)($cell->v ?? '');
                        $values[$column] = trim($value);
                    }
                    // Detect columns separately for both known layout versions. Sheet names do not determine payroll periods.
                    $upper = array_map(fn ($v) => mb_strtoupper((string)$v, 'UTF-8'), $values);
                    if (in_array('TANGGAL', $upper, true)) {
                        $columns = [
                            'date' => array_search('TANGGAL', $upper, true),
                            'doctor' => $this->firstColumn($upper, ['DOKTER JAGA', 'NAMA DOKTER']),
                            'clock' => $this->firstColumn($upper, ['JAM PRAKTEK', 'JAM PRAKTIK']),
                            'shift' => $this->firstColumn($upper, ['DATA SHIFT', 'OPERAN / KENDALA', 'JENIS SHIFT']),
                            'total' => $this->firstColumn($upper, ['TOTAL PASIEN HARIAN', 'TOTAL PASIEN']),
                            'patient' => $this->firstColumnContaining($upper, 'JUMLAH PASIEN'),
                        ];
                        continue;
                    }
                    if ($columns === null || !$columns['date'] || !$columns['doctor'] || !$columns['shift']) continue;
                    // Count columns sit below the merged JUMLAH PASIEN heading. They are different between sheets.
                    $patientCol = $columns['patient'];
                    if (!$patientCol) continue;
                    $bpjsCol = $this->columnNext($patientCol, 0);
                    $umumCol = $this->columnNext($patientCol, 1);
                    $date = $this->excelDate($values[$columns['date']] ?? '');
                    $doctor = trim((string)($values[$columns['doctor']] ?? ''));
                    if (!$date || $doctor === '') continue;
                    $bpjsRaw = $values[$bpjsCol] ?? '';
                    $umumRaw = $values[$umumCol] ?? '';
                    $bpjs = filter_var($bpjsRaw, FILTER_VALIDATE_INT);
                    $umum = filter_var($umumRaw, FILTER_VALIDATE_INT);
                    if ($bpjs === false && $bpjsRaw !== '') $bpjs = -1;
                    if ($umum === false && $umumRaw !== '') $umum = -1;
                    $bpjs = $bpjs === false ? 0 : $bpjs;
                    $umum = $umum === false ? 0 : $umum;
                    $shiftLabel = trim((string)($values[$columns['shift']] ?? ''));
                    $shift = $this->shiftType($shiftLabel);
                    [$start, $end, $hoursIssue] = $this->hours((string)($values[$columns['clock']] ?? ''));
                    $issues = [];
                    if ($bpjsRaw === '' && $umumRaw === '') $issues[] = 'Jumlah pasien kosong pada Excel; periksa apakah benar nihil.';
                    if ($bpjs < 0 || $umum < 0 || $bpjs > 1000 || $umum > 1000) {
                        $issues[] = 'Jumlah pasien tidak valid.';
                    }
                    if ($shift === null) $issues[] = 'Jenis shift belum dikenali; pilih manual.';
                    if ($hoursIssue) $issues[] = $hoursIssue;
                    if (str_contains(mb_strtoupper($shiftLabel, 'UTF-8'), 'POTONGAN')) {
                        $issues[] = 'Shift bertanda POTONGAN; periksa jam dan ketentuan transport.';
                    }
                    $cached = $columns['total'] ? ($values[$columns['total']] ?? '') : '';
                    if ($cached !== '' && is_numeric($cached) && (int)$cached !== $bpjs + $umum) {
                        $issues[] = 'Total pasien berbeda dengan penjumlahan BPJS dan umum.';
                    }
                    if ($shift === 'poli2' && $bpjs > 0 && $umum > 0 && $bpjs + $umum > 20) {
                        $issues[] = 'Poli 2 campuran: isi pembagian pasien berfee setelah kuota.';
                    }
                    $id = count($all);
                    $record = [
                        'id' => $id, 'sheet' => $sheet['name'], 'source_row' => $n,
                        'period' => DoctorPayrollPeriod::fromWorkDate($date), 'date' => $date,
                        'doctor' => $doctor, 'shift_label' => $shiftLabel,
                        'shift_type' => $shift, 'start_time' => $start, 'end_time' => $end,
                        'bpjs' => $bpjs, 'umum' => $umum, 'issues' => $issues,
                    ];
                    $records[] = $record;
                    $all[] = $record;
                    if (count($all) > 500) throw new RuntimeException('Maksimum 500 jadwal per file.');
                    $seen[$record['period']] = true;
                }
                if ($records) $bySheet[] = ['name' => $sheet['name'], 'records' => count($records), 'date_from' => min(array_column($records, 'date')), 'date_to' => max(array_column($records, 'date'))];
            }
            if (!$all) throw new RuntimeException('Tidak ditemukan data dokter/tanggal/pasien. Periksa header Excel.');
            return ['rows' => $all, 'sheets' => $bySheet, 'periods' => array_keys($seen), 'total' => count($all)];
        } finally {
            $zip->close();
        }
    }

    private function firstColumn(array $values, array $labels): ?string
    {
        foreach ($labels as $label) {
            $col = array_search($label, $values, true);
            if ($col !== false) return (string)$col;
        }
        return null;
    }

    private function firstColumnContaining(array $values, string $text): ?string
    {
        foreach ($values as $col => $value) if (str_contains((string)$value, $text)) return (string)$col;
        return null;
    }

    private function columnNext(string $col, int $offset): string
    {
        $index = 0;
        foreach (str_split(strtoupper($col)) as $letter) $index = $index * 26 + ord($letter) - 64;
        $index += $offset;
        $str = '';
        while ($index > 0) {
            $index--;
            $str = chr($index % 26 + 65).$str;
            $index = intdiv($index, 26);
        }
        return $str;
    }

    private function excelDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (is_numeric($value) && (float)$value > 35000 && (float)$value < 80000) {
            return gmdate('Y-m-d', (int)round(((float)$value - 25569) * 86400));
        }
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date && $date->format(substr($format, 1)) === $value) return $date->format('Y-m-d');
        }
        return null;
    }

    private function shiftType(string $value): ?string
    {
        $v = mb_strtoupper(trim($value), 'UTF-8');
        if (str_contains($v, 'POLI 2')) return 'poli2';
        if (str_contains($v, 'MALAM')) return 'malam';
        if (str_contains($v, 'KHUSUS') || str_contains($v, 'LIBUR')) return 'khusus';
        if (str_contains($v, 'KIA')) return 'kia';
        if (str_contains($v, 'USG')) return 'usg';
        if (str_contains($v, 'BIASA')) return 'biasa';
        return null;
    }

    private function hours(string $text): array
    {
        $t = trim($text);
        if ($t === '') return [null, null, 'Jam praktik kosong; periksa apakah ini shift penuh.'];
        if (!preg_match('/^(\d{1,2})[.:](\d{1,2})\s*[-–—]\s*(\d{1,2})[.:](\d{1,2})$/u', $t, $m)) {
            return [null, null, 'Format jam tidak dikenali: '.$t];
        }
        [$h1, $m1, $h2, $m2] = [(int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4]];
        if ($h1 > 23 || $h2 > 23 || $m1 > 59 || $m2 > 59) return [null,null, 'Jam tidak valid: '.$t];
        $minutes = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
        if ($minutes <= 0) $minutes += 1440;
        $start = sprintf('%02d:%02d', $h1, $m1);
        $end = sprintf('%02d:%02d', $h2, $m2);
        if ($minutes > 16 * 60) return [$start,$end,'Durasi '.round($minutes/60,2).' jam tidak wajar; koreksi jam.'];
        return [$start, $end, null];
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $content = $zip->getFromName($name);
        if (!is_string($content)) throw new RuntimeException('Bagian XLSX tidak ditemukan: '.$name);
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (!$xml) throw new RuntimeException('XML Excel rusak: '.$name);
        return $xml;
    }

    private function strings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) return [];
        $xml = $this->xml($zip, 'xl/sharedStrings.xml');
        $res = [];
        foreach ($xml->si as $si) {
            $text = (string)$si->t;
            foreach ($si->r as $run) $text .= (string)$run->t;
            $res[] = $text;
        }
        return $res;
    }

    private function sheets(ZipArchive $zip): array
    {
        $workbook = $this->xml($zip, 'xl/workbook.xml');
        $rels = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $paths = [];
        foreach ($rels->Relationship as $rel) {
            $id = (string)$rel['Id'];
            $target = (string)$rel['Target'];
            if (str_contains((string)$rel['Type'], '/worksheet') && !str_contains($target, '..')) {
                $paths[$id] = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
        }
        $res = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string)$attrs['id'];
            if (isset($paths[$rid]) && str_starts_with($paths[$rid], 'xl/worksheets/') && str_ends_with($paths[$rid], '.xml')) {
                $res[] = ['name' => (string)$sheet['name'], 'path' => $paths[$rid]];
            }
        }
        return $res;
    }
}
