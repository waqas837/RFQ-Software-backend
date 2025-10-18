<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    protected $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Get all supported currencies
     */
    public function getSupportedCurrencies(): JsonResponse
    {
        try {
            $currencies = $this->currencyService->getSupportedCurrencies();
            
            return response()->json([
                'success' => true,
                'data' => $currencies
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch currencies',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get currency conversion data
     */
    public function getConversionData(Request $request): JsonResponse
    {
        try {
            $baseCurrency = $request->get('base_currency', 'USD');
            $conversionData = $this->currencyService->getConversionData($baseCurrency);
            
            return response()->json([
                'success' => true,
                'data' => $conversionData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch conversion data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert amount between currencies
     */
    public function convertAmount(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0',
                'from_currency' => 'required|string|size:3',
                'to_currency' => 'required|string|size:3',
                'date' => 'nullable|date'
            ]);

            $amount = $request->amount;
            $fromCurrency = strtoupper($request->from_currency);
            $toCurrency = strtoupper($request->to_currency);
            $date = $request->date;

            $convertedAmount = $this->currencyService->convert($amount, $fromCurrency, $toCurrency, $date);
            $formattedAmount = $this->currencyService->formatAmount($convertedAmount, $toCurrency);

            return response()->json([
                'success' => true,
                'data' => [
                    'original_amount' => $amount,
                    'original_currency' => $fromCurrency,
                    'converted_amount' => $convertedAmount,
                    'converted_currency' => $toCurrency,
                    'formatted_amount' => $formattedAmount,
                    'conversion_date' => $date ?: now()->format('Y-m-d')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert currency',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get exchange rates (Admin only)
     */
    public function getExchangeRates(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', CurrencyRate::class);

            $fromCurrency = $request->get('from_currency');
            $toCurrency = $request->get('to_currency');
            $date = $request->get('date');

            $query = CurrencyRate::query();

            if ($fromCurrency) {
                $query->where('from_currency', $fromCurrency);
            }

            if ($toCurrency) {
                $query->where('to_currency', $toCurrency);
            }

            if ($date) {
                $query->where('date', $date);
            }

            $rates = $query->orderBy('date', 'desc')
                ->orderBy('from_currency')
                ->orderBy('to_currency')
                ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $rates
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch exchange rates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update exchange rates (Admin only)
     */
    public function updateExchangeRates(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', CurrencyRate::class);

            $request->validate([
                'rates' => 'required|array',
                'rates.*.from_currency' => 'required|string|size:3',
                'rates.*.to_currency' => 'required|string|size:3',
                'rates.*.rate' => 'required|numeric|min:0',
                'date' => 'nullable|date'
            ]);

            $rates = $request->rates;
            $date = $request->date;

            $this->currencyService->updateRates($rates, $date);

            return response()->json([
                'success' => true,
                'message' => 'Exchange rates updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update exchange rates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get currency symbols
     */
    public function getCurrencySymbols(): JsonResponse
    {
        try {
            $currencies = $this->currencyService->getSupportedCurrencies();
            $symbols = [];

            foreach ($currencies as $code => $name) {
                $symbols[$code] = [
                    'name' => $name,
                    'symbol' => $this->currencyService->getSymbol($code)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $symbols
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch currency symbols',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert amount for negotiation counter offers
     */
    public function convertNegotiationAmount(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0',
                'from_currency' => 'required|string|size:3',
                'to_currency' => 'required|string|size:3',
                'rfq_currency' => 'required|string|size:3'
            ]);

            $amount = $request->amount;
            $fromCurrency = strtoupper($request->from_currency);
            $toCurrency = strtoupper($request->to_currency);
            $rfqCurrency = strtoupper($request->rfq_currency);

            // Convert from counter offer currency to RFQ currency
            $convertedToRfq = $this->currencyService->convert($amount, $fromCurrency, $rfqCurrency);
            
            // Convert from RFQ currency to target currency
            $convertedAmount = $this->currencyService->convert($convertedToRfq, $rfqCurrency, $toCurrency);
            
            $formattedAmount = $this->currencyService->formatAmount($convertedAmount, $toCurrency);

            return response()->json([
                'success' => true,
                'data' => [
                    'original_amount' => $amount,
                    'original_currency' => $fromCurrency,
                    'converted_amount' => $convertedAmount,
                    'converted_currency' => $toCurrency,
                    'formatted_amount' => $formattedAmount,
                    'rfq_currency' => $rfqCurrency,
                    'conversion_rate' => $this->currencyService->getRate($fromCurrency, $toCurrency)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert negotiation amount',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
