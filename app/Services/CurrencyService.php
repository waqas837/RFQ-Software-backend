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
     * Get currency symbol (using English currency codes for consistency)
     */
    public function getSymbol($currency)
    {
        // Return currency code instead of localized symbols for consistency
        return $currency;
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
