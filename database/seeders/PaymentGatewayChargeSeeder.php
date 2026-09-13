<?php

namespace Database\Seeders;

use App\Models\PaymentGatewayCharge;
use Illuminate\Database\Seeder;

class PaymentGatewayChargeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $charges = [
            [
                'name' => 'UPI',
                'code' => 'upi',
                'charge_percentage' => 2.00,
                'tax_percentage' => 18.00,
                'total_percentage' => 2.3600,
                'flat_fee' => 0.00,
                'description' => 'Google Pay, PhonePe, Paytm, BHIM & all UPI apps',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Debit Cards (RuPay)',
                'code' => 'debit_card_rupay',
                'charge_percentage' => 2.00,
                'tax_percentage' => 18.00,
                'total_percentage' => 2.3600,
                'flat_fee' => 0.00,
                'description' => 'All domestic RuPay debit cards',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Debit Cards (Visa / Mastercard)',
                'code' => 'debit_card_visa_mc',
                'charge_percentage' => 2.00,
                'tax_percentage' => 18.00,
                'total_percentage' => 2.3600,
                'flat_fee' => 0.00,
                'description' => 'Domestic Visa and Mastercard debit cards',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Credit Cards (Domestic)',
                'code' => 'credit_card_domestic',
                'charge_percentage' => 3.00,
                'tax_percentage' => 18.00,
                'total_percentage' => 3.5400,
                'flat_fee' => 0.00,
                'description' => 'Domestic Visa, Mastercard & RuPay credit cards',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Credit Cards (International / Amex)',
                'code' => 'credit_card_intl_amex',
                'charge_percentage' => 3.00,
                'tax_percentage' => 18.00,
                'total_percentage' => 3.5400,
                'flat_fee' => 0.00,
                'description' => 'American Express, Diners Club & International cards',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Net Banking',
                'code' => 'net_banking',
                'charge_percentage' => 2.00,
                'tax_percentage' => 18.00,
                'total_percentage' => 2.3600,
                'flat_fee' => 0.00,
                'description' => 'All major Indian retail and corporate banks',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Wallets (Paytm, PhonePe, Mobikwik)',
                'code' => 'wallets',
                'charge_percentage' => 2.00,
                'tax_percentage' => 18.00,
                'total_percentage' => 2.3600,
                'flat_fee' => 0.00,
                'description' => 'Digital wallets and prepaid instruments',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'PayLater / Cardless EMI',
                'code' => 'paylater_emi',
                'charge_percentage' => 3.00,
                'tax_percentage' => 18.00,
                'total_percentage' => 3.5400,
                'flat_fee' => 0.00,
                'description' => 'Instant checkout credit and cardless EMI options',
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($charges as $charge) {
            PaymentGatewayCharge::updateOrCreate(
                ['code' => $charge['code']],
                $charge
            );
        }
    }
}
