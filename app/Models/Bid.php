<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'bid_number',
        'rfq_id',
        'supplier_company_id',
        'submitted_by',
        'total_amount',
        'currency',
        'proposed_delivery_date',
        'technical_proposal',
        'commercial_terms',
        'terms_conditions',
        'attachments',
        'custom_fields',
        'is_compliant',
        'compliance_notes',
        'technical_score',
        'commercial_score',
        'delivery_score',
        'total_score',
        'evaluation_notes',
        'evaluated_by',
        'evaluated_at',
        'status',
        'submitted_at',
        'delivery_time',
        'is_active',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'delivery_time' => 'integer',
        'proposed_delivery_date' => 'date',
        'attachments' => 'array',
        'custom_fields' => 'array',
        'is_compliant' => 'boolean',
        'technical_score' => 'decimal:2',
        'commercial_score' => 'decimal:2',
        'delivery_score' => 'decimal:2',
        'total_score' => 'decimal:2',
        'evaluated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the RFQ that this bid belongs to.
     */
    public function rfq()
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * Get the supplier company that submitted this bid.
     */
    public function supplierCompany()
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    /**
     * Get the supplier user who submitted this bid.
     */
    public function supplier()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who submitted this bid.
     */
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the items in this bid.
     */
    public function items()
    {
        return $this->hasMany(BidItem::class);
    }

    /**
     * Get the purchase order generated from this bid.
     */
    public function purchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    /**
     * Scope to get active bids only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get bids by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get bids by supplier.
     */
    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Get formatted total amount with currency symbol.
     */
    public function getFormattedTotalAttribute()
    {
        $currencyService = app(\App\Services\CurrencyService::class);
        return $currencyService->formatAmount($this->total_amount, $this->currency);
    }

    /**
     * Convert bid amount to another currency.
     */
    public function convertAmountTo($targetCurrency)
    {
        $currencyService = app(\App\Services\CurrencyService::class);
        
        return [
            'currency' => $targetCurrency,
            'total_amount' => $currencyService->convert($this->total_amount, $this->currency, $targetCurrency),
            'formatted_amount' => $currencyService->formatAmount(
                $currencyService->convert($this->total_amount, $this->currency, $targetCurrency),
                $targetCurrency
            )
        ];
    }
}
