<?php

namespace Tests\Unit;

use App\Support\NumberToWords;
use PHPUnit\Framework\TestCase;

class NumberToWordsTest extends TestCase
{
    public function test_converts_zero_rupees(): void
    {
        $this->assertEquals('Zero Rupees Only', NumberToWords::indianRupees(0));
        $this->assertEquals('Zero Rupees Only', NumberToWords::indianRupees(0.00));
    }

    public function test_converts_whole_rupees_with_no_paise(): void
    {
        $this->assertEquals('Five Hundred Rupees Only', NumberToWords::indianRupees(500));
        $this->assertEquals('One Rupee Only', NumberToWords::indianRupees(1));
    }

    public function test_converts_paise_only(): void
    {
        $this->assertEquals('Fifty Paise Only', NumberToWords::indianRupees(0.50));
        $this->assertEquals('One Paisa Only', NumberToWords::indianRupees(0.01));
    }

    public function test_converts_exact_thousand_lakh_and_crore(): void
    {
        $this->assertEquals('One Thousand Rupees Only', NumberToWords::indianRupees(1000));
        $this->assertEquals('One Lakh Rupees Only', NumberToWords::indianRupees(100000));
        $this->assertEquals('One Crore Rupees Only', NumberToWords::indianRupees(10000000));
    }

    public function test_converts_mixed_crore_lakh_thousand_hundred_values(): void
    {
        $this->assertEquals(
            'One Lakh Seventy Five Thousand Four Hundred Fifty Rupees and Fifty Paise Only',
            NumberToWords::indianRupees(175450.50)
        );

        $this->assertEquals(
            'Two Crore Thirty Four Lakh Fifty Six Thousand Seven Hundred Eighty Nine Rupees and Forty Five Paise Only',
            NumberToWords::indianRupees(23456789.45)
        );
    }

    public function test_rounds_fractional_paise_correctly(): void
    {
        // 10.005 rounds to 10.01
        $this->assertEquals(
            'Ten Rupees and One Paisa Only',
            NumberToWords::indianRupees(10.005)
        );

        // 10.004 rounds to 10.00
        $this->assertEquals(
            'Ten Rupees Only',
            NumberToWords::indianRupees(10.004)
        );
    }
}
