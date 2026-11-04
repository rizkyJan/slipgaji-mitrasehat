<?php
namespace App\Services;

use InvalidArgumentException;

/**
 * Pembuat matriks QR standar ISO/IEC 18004, versi 8 tingkat koreksi M, mask 0.
 * Pure PHP: tidak memerlukan GD, Imagick, layanan pihak ketiga, atau dependensi Composer.
 * Kapasitas maksimal 152 byte; khusus URL verifikasi singkat aplikasi payroll.
 */
class QrCodeMatrix
{
    public static function make(string $payload): array
    {
        if (strlen($payload) > 152 || !preg_match('//u', $payload)) {
            throw new InvalidArgumentException('URL QR terlalu panjang atau bukan UTF-8.');
        }
        $bits = '0100'.str_pad(decbin(strlen($payload)), 8, '0', STR_PAD_LEFT);
        for ($i=0; $i<strlen($payload); $i++) $bits .= str_pad(decbin(ord($payload[$i])), 8, '0', STR_PAD_LEFT);
        $bits .= str_repeat('0', min(4, 1232 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $data = [];
        foreach (str_split($bits, 8) as $chunk) $data[] = bindec($chunk);
        for ($i=count($data), $pad=0; $i<154; $i++, $pad++) $data[] = $pad % 2 === 0 ? 0xEC : 0x11;
        // QR versi 8-M: 2 blok berisi 38 data, dan 2 blok berisi 39 data; 22 ECC tiap blok.
        $blocks = [];
        $offset = 0;
        foreach ([38,38,39,39] as $count) {
            $chunk = array_slice($data, $offset, $count);
            $offset += $count;
            $blocks[] = ['data' => $chunk, 'ecc' => self::reedSolomon($chunk, 22)];
        }
        $bytes = [];
        for ($i=0; $i<39; $i++) foreach ($blocks as $block) if (isset($block['data'][$i])) $bytes[] = $block['data'][$i];
        for ($i=0; $i<22; $i++) foreach ($blocks as $block) $bytes[] = $block['ecc'][$i];
        $size = 49;
        $m = array_fill(0,$size,array_fill(0,$size,null));
        foreach ([[0,0],[0,$size-7],[$size-7,0]] as [$r,$c]) {
            for ($dr=-1; $dr<=7; $dr++) for ($dc=-1; $dc<=7; $dc++) {
                $rr=$r+$dr; $cc=$c+$dc;
                if ($rr<0 || $cc<0 || $rr>=$size || $cc>=$size) continue;
                $m[$rr][$cc] = ($dr>=0 && $dr<=6 && ($dc===0 || $dc===6))
                    || ($dc>=0 && $dc<=6 && ($dr===0 || $dr===6))
                    || ($dr>=2 && $dr<=4 && $dc>=2 && $dc<=4);
            }
        }
        foreach ([6,24,42] as $r) foreach ([6,24,42] as $c) {
            if ($m[$r][$c] !== null) continue;
            for ($dr=-2; $dr<=2; $dr++) for ($dc=-2; $dc<=2; $dc++) {
                $m[$r+$dr][$c+$dc] = abs($dr)===2 || abs($dc)===2 || ($dr===0 && $dc===0);
            }
        }
        for ($i=8; $i<$size-8; $i++) {
            if ($m[$i][6] === null) $m[$i][6] = $i%2===0;
            if ($m[6][$i] === null) $m[6][$i] = $i%2===0;
        }
        // Format information: ECC M + mask 0. BCH masked value 0x5412.
        $format=0x5412;
        for ($i=0; $i<15; $i++) {
            $dark = (($format >> $i) & 1) === 1;
            $m[$i<6?$i:($i<8?$i+1:$size-15+$i)][8] = $dark;
            $r=8;
            $c=$i<8?$size-$i-1:($i<9?15-$i:14-$i);
            $m[$r][$c] = $dark;
        }
        $m[$size-8][8] = true;
        // Version information: versi 8 dengan BCH (0x85BC).
        $version=0x85BC;
        for ($i=0; $i<18; $i++) {
            $dark = (($version >> $i) & 1) === 1;
            $m[intdiv($i,3)][$i%3+$size-11] = $dark;
            $m[$i%3+$size-11][intdiv($i,3)] = $dark;
        }
        $row=$size-1; $inc=-1; $byteIndex=0; $bitIndex=7;
        for ($rawCol=$size-1; $rawCol>0; $rawCol-=2) {
            $col = $rawCol <= 6 ? $rawCol - 1 : $rawCol;
            while (true) {
                foreach ([$col,$col-1] as $cc) {
                    if ($m[$row][$cc] !== null) continue;
                    $dark = $byteIndex<count($bytes) && (($bytes[$byteIndex] >> $bitIndex) & 1) === 1;
                    if (($row+$cc)%2===0) $dark=!$dark;
                    $m[$row][$cc]=$dark;
                    $bitIndex--;
                    if ($bitIndex<0) { $byteIndex++; $bitIndex=7; }
                }
                $row += $inc;
                if ($row<0 || $row>=$size) { $row -= $inc; $inc=-$inc; break; }
            }
        }
        return $m;
    }

    private static function reedSolomon(array $data, int $degree): array
    {
        $exp = [1];
        for ($i=1; $i<512; $i++) {
            $v = $exp[$i-1]<<1;
            if ($v & 0x100) $v ^= 0x11D;
            $exp[$i]=$v;
        }
        $multiply = static function (int $a, int $b) use ($exp): int {
            if ($a===0 || $b===0) return 0;
            $la=0; $lb=0;
            while ($exp[$la]!==$a) $la++;
            while ($exp[$lb]!==$b) $lb++;
            return $exp[$la+$lb];
        };
        $gen=[1];
        for ($i=0; $i<$degree; $i++) {
            $next=array_fill(0,count($gen)+1,0);
            for ($j=0; $j<count($gen); $j++) {
                $next[$j] ^= $gen[$j];
                $next[$j+1] ^= $multiply($gen[$j],$exp[$i]);
            }
            $gen=$next;
        }
        $remainder=array_fill(0,$degree,0);
        foreach ($data as $value) {
            $factor=$value^$remainder[0];
            array_shift($remainder);
            $remainder[]=0;
            for ($j=0; $j<$degree; $j++) $remainder[$j]^=$multiply($gen[$j+1],$factor);
        }
        return $remainder;
    }
}
