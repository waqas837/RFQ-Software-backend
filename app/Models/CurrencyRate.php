<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CurrencyRate extends Model
{
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'date',
        'is_active'
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'date' => 'date',
        'is_active' => 'boolean'
    ];

    /**
     * Get the latest exchange rate between two currencies
     */
    public static function getRate($fromCurrency, $toCurrency, $date = null)
    {
        $date = $date ?: Carbon::today();
        
        return self::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('date', '<=', $date)
            ->where('is_active', true)
            ->orderBy('date', 'desc')
            ->first();
    }

    /**
     * Convert amount from one currency to another
     */
    public static function convert($amount, $fromCurrency, $toCurrency, $date = null)
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = self::getRate($fromCurrency, $toCurrency, $date);
        
        if (!$rate) {
            // Try reverse rate
            $reverseRate = self::getRate($toCurrency, $fromCurrency, $date);
            if ($reverseRate) {
                return $amount / $reverseRate->rate;
            }
            return $amount; // Return original if no rate found
        }

        return $amount * $rate->rate;
    }

    /**
     * Get all supported currencies
     */
    public static function getSupportedCurrencies()
    {
        return [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'JPY' => 'Japanese Yen',
            'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar',
            'CHF' => 'Swiss Franc',
            'CNY' => 'Chinese Yuan',
            'INR' => 'Indian Rupee',
            'AED' => 'UAE Dirham',
            'SAR' => 'Saudi Riyal',
            'QAR' => 'Qatari Riyal',
            'KWD' => 'Kuwaiti Dinar',
            'BHD' => 'Bahraini Dinar',
            'OMR' => 'Omani Rial'
        ];
    }
}
