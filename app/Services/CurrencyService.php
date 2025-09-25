<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Carbon\Carbon;

class CurrencyService
{
    /**
     * Convert amount from one currency to another
     */
    public function convert($amount, $fromCurrency, $toCurrency, $date = null)
    {
        return CurrencyRate::convert($amount, $fromCurrency, $toCurrency, $date);
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getRate($fromCurrency, $toCurrency, $date = null)
    {
        return CurrencyRate::getRate($fromCurrency, $toCurrency, $date);
    }

    /**
     * Get all supported currencies
     */
    public function getSupportedCurrencies()
    {
        return CurrencyRate::getSupportedCurrencies();
    }

    /**
     * Format currency amount with symbol (using English currency codes for consistency)
     */
    public function formatAmount($amount, $currency = 'USD')
    {
        // Use currency code instead of localized symbols for consistency
        return $currency . ' ' . number_format($amount, 2);
    }

    /**
     * Get currency symbol
     */
    public function getSymbol($currency)
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'AED',
            'SAR' => 'SAR',
            'QAR' => 'QAR',
            'KWD' => 'KWD',
            'BHD' => 'BHD',
            'OMR' => 'OMR',
            'JOD' => 'JOD',
            'EGP' => 'EGP',
            'LBP' => 'LBP',
            'INR' => '₹',
            'PKR' => 'PKR',
            'BDT' => 'BDT',
            'LKR' => 'LKR',
            'NPR' => 'NPR',
            'CNY' => '¥',
            'JPY' => '¥',
            'KRW' => '₩',
            'THB' => '฿',
            'SGD' => 'S$',
            'MYR' => 'RM',
            'IDR' => 'Rp',
            'PHP' => '₱',
            'VND' => '₫',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'PLN' => 'zł',
            'CZK' => 'Kč',
            'HUF' => 'Ft',
            'RUB' => '₽',
            'TRY' => '₺',
            'ZAR' => 'R',
            'BRL' => 'R$',
            'MXN' => '$',
            'ARS' => '$',
            'CLP' => '$',
            'COP' => '$',
            'PEN' => 'S/',
            'UYU' => '$U',
            'VEF' => 'Bs',
            'NGN' => '₦',
            'GHS' => '₵',
            'KES' => 'KSh',
            'UGX' => 'USh',
            'TZS' => 'TSh',
            'ETB' => 'Br',
            'MAD' => 'MAD',
            'TND' => 'TND',
            'DZD' => 'DZD',
            'LYD' => 'LYD',
            'SDG' => 'SDG',
            'SSP' => 'SSP',
            'SOS' => 'S',
            'DJF' => 'Fdj',
            'KMF' => 'CF',
            'MUR' => '₨',
            'SCR' => '₨',
            'MVR' => 'MVR',
            'LKR' => '₨',
            'NPR' => '₨',
            'BTN' => 'Nu',
            'AFN' => '؋',
            'IRR' => '﷼',
            'IQD' => 'ع.د',
            'SYP' => '£',
            'YER' => '﷼',
            'ILS' => '₪',
            'PST' => '₨',
            'TMT' => 'T',
            'UZS' => 'лв',
            'KZT' => '₸',
            'KGS' => 'лв',
            'TJS' => 'SM',
            'MNT' => '₮',
            'AMD' => '֏',
            'GEL' => '₾',
            'AZN' => '₼',
            'BYN' => 'Br',
            'MDL' => 'L',
            'UAH' => '₴',
            'BGN' => 'лв',
            'RON' => 'lei',
            'HRK' => 'kn',
            'RSD' => 'дин',
            'MKD' => 'ден',
            'ALL' => 'L',
            'BAM' => 'КМ',
            'ISK' => 'kr',
            'LVL' => 'Ls',
            'LTL' => 'Lt',
            'EEK' => 'kr',
            'MTL' => '₤',
            'CYP' => '£',
            'SIT' => 'SIT',
            'SKK' => 'Sk',
            'BEF' => 'fr',
            'NLG' => 'ƒ',
            'FRF' => '₣',
            'DEM' => 'DM',
            'ITL' => '₤',
            'ESP' => '₧',
            'PTE' => '$',
            'IEP' => '£',
            'FIM' => 'mk',
            'ATS' => 'S',
            'GRD' => '₯',
            'LUF' => 'fr',
            'BEF' => 'fr',
            'NLG' => 'ƒ',
            'FRF' => '₣',
            'DEM' => 'DM',
            'ITL' => '₤',
            'ESP' => '₧',
            'PTE' => '$',
            'IEP' => '£',
            'FIM' => 'mk',
            'ATS' => 'S',
            'GRD' => '₯',
            'LUF' => 'fr',
        ];

        return $symbols[$currency] ?? $currency;
    }

    /**
     * Update exchange rates (for admin use)
     */
    public function updateRates($rates, $date = null)
    {
        $date = $date ?: Carbon::today();
        
        foreach ($rates as $rate) {
            CurrencyRate::updateOrCreate(
                [
                    'from_currency' => $rate['from_currency'],
                    'to_currency' => $rate['to_currency'],
                    'date' => $date
                ],
                [
                    'rate' => $rate['rate'],
                    'is_active' => true
                ]
            );
        }
    }

    /**
     * Get currency conversion data for frontend
     */
    public function getConversionData($baseCurrency = 'USD')
    {
        $currencies = $this->getSupportedCurrencies();
        $rates = [];
        
        foreach ($currencies as $code => $name) {
            if ($code !== $baseCurrency) {
                $rate = $this->getRate($baseCurrency, $code);
                $rates[$code] = [
                    'name' => $name,
                    'symbol' => $this->getSymbol($code),
                    'rate' => $rate ? $rate->rate : 1,
                    'last_updated' => $rate ? $rate->date->format('Y-m-d') : null
                ];
            }
        }

        return [
            'base_currency' => $baseCurrency,
            'currencies' => $currencies,
            'rates' => $rates
        ];
    }
}
