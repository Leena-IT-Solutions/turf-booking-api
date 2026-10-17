<?php

namespace App\Support;

class NumberToWords
{
    private static array $ones = [
        0 => '',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
    ];

    private static array $tens = [
        2 => 'Twenty',
        3 => 'Thirty',
        4 => 'Forty',
        5 => 'Fifty',
        6 => 'Sixty',
        7 => 'Seventy',
        8 => 'Eighty',
        9 => 'Ninety',
    ];

    /**
     * Convert an amount into Indian Rupees in words (e.g. "One Lakh Fifty Thousand Rupees Only").
     */
    public static function indianRupees(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        if ($rupees === 0 && $paise === 0) {
            return 'Zero Rupees Only';
        }

        $parts = [];

        if ($rupees > 0) {
            $rupeesWords = self::convertNumber($rupees);
            $rupeeUnit = $rupees === 1 ? 'Rupee' : 'Rupees';
            $parts[] = "{$rupeesWords} {$rupeeUnit}";
        }

        if ($paise > 0) {
            $paiseWords = self::convertNumber($paise);
            $paiseUnit = $paise === 1 ? 'Paisa' : 'Paise';
            $parts[] = "{$paiseWords} {$paiseUnit}";
        }

        return implode(' and ', $parts) . ' Only';
    }

    /**
     * Convert an integer number into words using the Indian numbering system.
     */
    public static function convertNumber(int $num): string
    {
        if ($num === 0) {
            return 'Zero';
        }

        $crores = (int) floor($num / 10000000);
        $num %= 10000000;

        $lakhs = (int) floor($num / 100000);
        $num %= 100000;

        $thousands = (int) floor($num / 1000);
        $num %= 1000;

        $hundreds = (int) floor($num / 100);
        $remainder = $num % 100;

        $words = [];

        if ($crores > 0) {
            $words[] = self::convertTwoDigits($crores) . ' Crore';
        }
        if ($lakhs > 0) {
            $words[] = self::convertTwoDigits($lakhs) . ' Lakh';
        }
        if ($thousands > 0) {
            $words[] = self::convertTwoDigits($thousands) . ' Thousand';
        }
        if ($hundreds > 0) {
            $words[] = self::$ones[$hundreds] . ' Hundred';
        }
        if ($remainder > 0) {
            $words[] = self::convertTwoDigits($remainder);
        }

        return implode(' ', $words);
    }

    private static function convertTwoDigits(int $n): string
    {
        if ($n >= 100) {
            return self::convertNumber($n);
        }

        if ($n < 20) {
            return self::$ones[$n];
        }

        $ten = (int) floor($n / 10);
        $unit = $n % 10;

        return trim(self::$tens[$ten] . ' ' . self::$ones[$unit]);
    }
}
