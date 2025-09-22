<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CurrencyRate;
use Carbon\Carbon;

class CurrencyRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $today = Carbon::today();
        
        // Default exchange rates (these should be updated with real rates)
        $rates = [
            // USD to other currencies
            ['from_currency' => 'USD', 'to_currency' => 'EUR', 'rate' => 0.85],
            ['from_currency' => 'USD', 'to_currency' => 'GBP', 'rate' => 0.73],
            ['from_currency' => 'USD', 'to_currency' => 'JPY', 'rate' => 110.00],
            ['from_currency' => 'USD', 'to_currency' => 'CAD', 'rate' => 1.25],
            ['from_currency' => 'USD', 'to_currency' => 'AUD', 'rate' => 1.35],
            ['from_currency' => 'USD', 'to_currency' => 'CHF', 'rate' => 0.92],
            ['from_currency' => 'USD', 'to_currency' => 'CNY', 'rate' => 6.45],
            ['from_currency' => 'USD', 'to_currency' => 'INR', 'rate' => 74.50],
            ['from_currency' => 'USD', 'to_currency' => 'AED', 'rate' => 3.67],
            ['from_currency' => 'USD', 'to_currency' => 'SAR', 'rate' => 3.75],
            ['from_currency' => 'USD', 'to_currency' => 'QAR', 'rate' => 3.64],
            ['from_currency' => 'USD', 'to_currency' => 'KWD', 'rate' => 0.30],
            ['from_currency' => 'USD', 'to_currency' => 'BHD', 'rate' => 0.38],
            ['from_currency' => 'USD', 'to_currency' => 'OMR', 'rate' => 0.38],

            // EUR to other currencies
            ['from_currency' => 'EUR', 'to_currency' => 'USD', 'rate' => 1.18],
            ['from_currency' => 'EUR', 'to_currency' => 'GBP', 'rate' => 0.86],
            ['from_currency' => 'EUR', 'to_currency' => 'JPY', 'rate' => 129.50],
            ['from_currency' => 'EUR', 'to_currency' => 'CHF', 'rate' => 1.08],

            // GBP to other currencies
            ['from_currency' => 'GBP', 'to_currency' => 'USD', 'rate' => 1.37],
            ['from_currency' => 'GBP', 'to_currency' => 'EUR', 'rate' => 1.16],
            ['from_currency' => 'GBP', 'to_currency' => 'JPY', 'rate' => 150.75],

            // AED to other currencies (common in Middle East)
            ['from_currency' => 'AED', 'to_currency' => 'USD', 'rate' => 0.27],
            ['from_currency' => 'AED', 'to_currency' => 'EUR', 'rate' => 0.23],
            ['from_currency' => 'AED', 'to_currency' => 'SAR', 'rate' => 1.02],
            ['from_currency' => 'AED', 'to_currency' => 'QAR', 'rate' => 0.99],
            ['from_currency' => 'AED', 'to_currency' => 'KWD', 'rate' => 0.08],
            ['from_currency' => 'AED', 'to_currency' => 'BHD', 'rate' => 0.10],
            ['from_currency' => 'AED', 'to_currency' => 'OMR', 'rate' => 0.10],

            // SAR to other currencies
            ['from_currency' => 'SAR', 'to_currency' => 'USD', 'rate' => 0.27],
            ['from_currency' => 'SAR', 'to_currency' => 'EUR', 'rate' => 0.23],
            ['from_currency' => 'SAR', 'to_currency' => 'AED', 'rate' => 0.98],
            ['from_currency' => 'SAR', 'to_currency' => 'QAR', 'rate' => 0.97],
            ['from_currency' => 'SAR', 'to_currency' => 'KWD', 'rate' => 0.08],
            ['from_currency' => 'SAR', 'to_currency' => 'BHD', 'rate' => 0.10],
            ['from_currency' => 'SAR', 'to_currency' => 'OMR', 'rate' => 0.10],

            // QAR to other currencies
            ['from_currency' => 'QAR', 'to_currency' => 'USD', 'rate' => 0.27],
            ['from_currency' => 'QAR', 'to_currency' => 'EUR', 'rate' => 0.23],
            ['from_currency' => 'QAR', 'to_currency' => 'AED', 'rate' => 1.01],
            ['from_currency' => 'QAR', 'to_currency' => 'SAR', 'rate' => 1.03],
            ['from_currency' => 'QAR', 'to_currency' => 'KWD', 'rate' => 0.08],
            ['from_currency' => 'QAR', 'to_currency' => 'BHD', 'rate' => 0.10],
            ['from_currency' => 'QAR', 'to_currency' => 'OMR', 'rate' => 0.10],

            // KWD to other currencies
            ['from_currency' => 'KWD', 'to_currency' => 'USD', 'rate' => 3.33],
            ['from_currency' => 'KWD', 'to_currency' => 'EUR', 'rate' => 2.83],
            ['from_currency' => 'KWD', 'to_currency' => 'AED', 'rate' => 12.23],
            ['from_currency' => 'KWD', 'to_currency' => 'SAR', 'rate' => 12.50],
            ['from_currency' => 'KWD', 'to_currency' => 'QAR', 'rate' => 12.13],
            ['from_currency' => 'KWD', 'to_currency' => 'BHD', 'rate' => 1.27],
            ['from_currency' => 'KWD', 'to_currency' => 'OMR', 'rate' => 1.27],

            // BHD to other currencies
            ['from_currency' => 'BHD', 'to_currency' => 'USD', 'rate' => 2.63],
            ['from_currency' => 'BHD', 'to_currency' => 'EUR', 'rate' => 2.23],
            ['from_currency' => 'BHD', 'to_currency' => 'AED', 'rate' => 9.66],
            ['from_currency' => 'BHD', 'to_currency' => 'SAR', 'rate' => 9.87],
            ['from_currency' => 'BHD', 'to_currency' => 'QAR', 'rate' => 9.58],
            ['from_currency' => 'BHD', 'to_currency' => 'KWD', 'rate' => 0.79],
            ['from_currency' => 'BHD', 'to_currency' => 'OMR', 'rate' => 1.00],

            // OMR to other currencies
            ['from_currency' => 'OMR', 'to_currency' => 'USD', 'rate' => 2.63],
            ['from_currency' => 'OMR', 'to_currency' => 'EUR', 'rate' => 2.23],
            ['from_currency' => 'OMR', 'to_currency' => 'AED', 'rate' => 9.66],
            ['from_currency' => 'OMR', 'to_currency' => 'SAR', 'rate' => 9.87],
            ['from_currency' => 'OMR', 'to_currency' => 'QAR', 'rate' => 9.58],
            ['from_currency' => 'OMR', 'to_currency' => 'KWD', 'rate' => 0.79],
            ['from_currency' => 'OMR', 'to_currency' => 'BHD', 'rate' => 1.00],
        ];

        foreach ($rates as $rate) {
            CurrencyRate::updateOrCreate(
                [
                    'from_currency' => $rate['from_currency'],
                    'to_currency' => $rate['to_currency'],
                    'date' => $today
                ],
                [
                    'rate' => $rate['rate'],
                    'is_active' => true
                ]
            );
        }

        $this->command->info('Currency rates seeded successfully!');
    }
}
