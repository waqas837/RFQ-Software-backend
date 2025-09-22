<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rfq extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'reference_number',
        'description',
        'specifications',
        'terms_conditions',
        'category_id',
        'created_by',
        'company_id',
        'status',
        'budget_min',
        'budget_max',
        'currency',
        'delivery_date',
        'bid_deadline',
        'estimated_quantity',
        'delivery_location',
        'attachments',
        'custom_fields',
        'is_urgent',
        'requires_approval',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'delivery_date' => 'datetime',
        'bid_deadline' => 'datetime',
        'attachments' => 'array',
        'custom_fields' => 'array',
        'is_urgent' => 'boolean',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'delivery_deadline',
        'bidding_deadline',
    ];

    /**
     * Get the delivery deadline (alias for delivery_date).
     */
    public function getDeliveryDeadlineAttribute()
    {
        return $this->delivery_date;
    }

    /**
     * Get the bidding deadline (alias for bid_deadline).
     */
    public function getBiddingDeadlineAttribute()
    {
        return $this->bid_deadline;
    }

    /**
     * Get the category of the RFQ.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the company that owns the RFQ.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the RFQ.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the items in this RFQ.
     */
    public function items()
    {
        return $this->hasMany(RfqItem::class);
    }

    /**
     * Get the suppliers invited to this RFQ.
     */
    public function suppliers()
    {
        return $this->belongsToMany(Company::class, 'rfq_suppliers', 'rfq_id', 'supplier_company_id');
    }

    /**
     * Get the bids for this RFQ.
     */
    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Get the purchase orders generated from this RFQ.
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }


    /**
     * Scope to get RFQs by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get RFQs created by a specific user.
     */
    public function scopeByCreator($query, $userId)
    {
        return $query->where('created_by', $userId);
    }


    /**
     * Get the user who approved the RFQ.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if RFQ can transition to a specific status.
     */
    public function canTransitionTo(string $newStatus, User $user): bool
    {
        return \App\Services\WorkflowService::canTransitionTo($this, $newStatus, $user);
    }

    /**
     * Get available status transitions for the current user.
     */
    public function getAvailableTransitions(User $user): array
    {
        return \App\Services\WorkflowService::getAvailableTransitions($this, $user);
    }

    /**
     * Transition RFQ to a new status.
     */
    public function transitionTo(string $newStatus, User $user, array $metadata = []): bool
    {
        return \App\Services\WorkflowService::transitionTo($this, $newStatus, $user, $metadata);
    }

    /**
     * Get workflow statistics for this RFQ.
     */
    public function getWorkflowStats(): array
    {
        $stats = [
            'total_bids' => $this->bids()->count(),
            'total_suppliers' => $this->suppliers()->count(),
            'days_remaining' => $this->bid_deadline ? max(0, now()->startOfDay()->diffInDays($this->bid_deadline->startOfDay(), false)) : 0,
            'is_overdue' => $this->bid_deadline ? now()->isAfter($this->bid_deadline) : false,
        ];

        return $stats;
    }

    /**
     * Check if RFQ is in a specific status.
     */
    public function isStatus(string $status): bool
    {
        return $this->status === $status;
    }

    /**
     * Check if RFQ is in draft mode.
     */
    public function isDraft(): bool
    {
        return $this->isStatus('draft');
    }

    /**
     * Check if RFQ is published.
     */
    public function isPublished(): bool
    {
        return $this->isStatus('published');
    }

    /**
     * Check if RFQ is open for bidding.
     */
    public function isBiddingOpen(): bool
    {
        return $this->isStatus('bidding_open');
    }

    /**
     * Check if RFQ is closed for bidding.
     */
    public function isBiddingClosed(): bool
    {
        return $this->isStatus('bidding_closed');
    }

    /**
     * Check if RFQ is under evaluation.
     */
    public function isUnderEvaluation(): bool
    {
        return $this->isStatus('under_evaluation');
    }

    /**
     * Check if RFQ is awarded.
     */
    public function isAwarded(): bool
    {
        return $this->isStatus('awarded');
    }

    /**
     * Check if RFQ is completed.
     */
    public function isCompleted(): bool
    {
        return $this->isStatus('completed');
    }

    /**
     * Check if RFQ is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->isStatus('cancelled');
    }

    /**
     * Get formatted budget range with currency symbol.
     */
    public function getFormattedBudgetAttribute()
    {
        $currencyService = app(\App\Services\CurrencyService::class);
        $symbol = $currencyService->getSymbol($this->currency);
        
        if ($this->budget_min && $this->budget_max) {
            return $symbol . ' ' . number_format($this->budget_min, 2) . ' - ' . $symbol . ' ' . number_format($this->budget_max, 2);
        } elseif ($this->budget_max) {
            return 'Up to ' . $symbol . ' ' . number_format($this->budget_max, 2);
        } elseif ($this->budget_min) {
            return 'From ' . $symbol . ' ' . number_format($this->budget_min, 2);
        }
        
        return 'Budget not specified';
    }

    /**
     * Convert budget to another currency.
     */
    public function convertBudgetTo($targetCurrency)
    {
        $currencyService = app(\App\Services\CurrencyService::class);
        
        $converted = [
            'currency' => $targetCurrency,
            'budget_min' => $this->budget_min ? $currencyService->convert($this->budget_min, $this->currency, $targetCurrency) : null,
            'budget_max' => $this->budget_max ? $currencyService->convert($this->budget_max, $this->currency, $targetCurrency) : null,
        ];
        
        return $converted;
    }
}
