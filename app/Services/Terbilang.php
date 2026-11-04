<?php
namespace App\Services;

class Terbilang
{
    private const WORDS = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    private static function say(int $n): string
    {
        if ($n < 12) return self::WORDS[$n];
        if ($n < 20) return trim(self::say($n - 10).' Belas');
        if ($n < 100) return trim(self::say(intdiv($n, 10)).' Puluh '.self::say($n % 10));
        if ($n < 200) return trim('Seratus '.self::say($n - 100));
        if ($n < 1000) return trim(self::say(intdiv($n, 100)).' Ratus '.self::say($n % 100));
        if ($n < 2000) return trim('Seribu '.self::say($n - 1000));
        if ($n < 1000000) return trim(self::say(intdiv($n, 1000)).' Ribu '.self::say($n % 1000));
        if ($n < 1000000000) return trim(self::say(intdiv($n, 1000000)).' Juta '.self::say($n % 1000000));
        if ($n < 1000000000000) return trim(self::say(intdiv($n, 1000000000)).' Miliar '.self::say($n % 1000000000));
        return trim(self::say(intdiv($n, 1000000000000)).' Triliun '.self::say($n % 1000000000000));
    }

    public static function rupiah(int $amount): string
    {
        if ($amount < 0) return 'Minus '.self::say(abs($amount)).' Rupiah';
        if ($amount === 0) return 'Nol Rupiah';
        return trim(self::say($amount)).' Rupiah';
    }
}
