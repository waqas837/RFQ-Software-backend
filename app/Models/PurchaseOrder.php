<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'rfq_id',
        'bid_id',
        'supplier_company_id',
        'buyer_company_id',
        'created_by',
        'status',
        'total_amount',
        'currency',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'delivery_address',
        'billing_address',
        'payment_terms',
        'terms_conditions',
        'attachments',
        'notes',
        'approved_by',
        'approved_at',
        'sent_at',
        'acknowledged_at',
        'delivery_notes',
        'delivery_photos',
        'delivery_documents',
        'approval_notes',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'modification_history',
        'last_modified_by',
        'last_modified_at',
        'internal_notes',
        'status_history',
        'requires_approval',
        'approved_amount',
        'approval_level',
        'approval_chain',
        'current_approval_step',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'attachments' => 'array',
        'delivery_photos' => 'array',
        'delivery_documents' => 'array',
        'rejected_at' => 'datetime',
        'last_modified_at' => 'datetime',
        'modification_history' => 'array',
        'status_history' => 'array',
        'requires_approval' => 'boolean',
        'approved_amount' => 'decimal:2',
        'approval_chain' => 'array',
    ];

    /**
     * Get the RFQ that this PO belongs to.
     */
    public function rfq()
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * Get the bid that this PO is based on.
     */
    public function bid()
    {
        return $this->belongsTo(Bid::class);
    }

    /**
     * Get the supplier company.
     */
    public function supplierCompany()
    {
        return $this->belongsTo(Company::class, 'supplier_company_id');
    }

    /**
     * Get the buyer company.
     */
    public function buyerCompany()
    {
        return $this->belongsTo(Company::class, 'buyer_company_id');
    }

    /**
     * Get the user who created the PO.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved the PO.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected the PO.
     */
    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the user who last modified the PO.
     */
    public function lastModifier()
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    /**
     * Get the PO items.
     */
    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the status history for this PO.
     */
    public function statusHistory()
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class);
    }

    /**
     * Get the modifications for this PO.
     */
    public function modifications()
    {
        return $this->hasMany(PurchaseOrderModification::class);
    }

    /**
     * Scope to get active POs only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get POs by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
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
     * Convert PO amount to another currency.
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


    /**
     * Check if PO can be modified.
     */
    public function canBeModified()
    {
        return in_array($this->status, ['sent_to_supplier', 'acknowledged', 'in_progress']);
    }

    /**
     * Check if PO can be sent to supplier.
     */
    public function canBeSent()
    {
        return in_array($this->status, ['approved']);
    }

    /**
     * Check if PO can be acknowledged by supplier.
     */
    public function canBeAcknowledged()
    {
        return $this->status === 'sent_to_supplier';
    }

    /**
     * Check if PO can be marked as in progress.
     */
    public function canBeInProgress()
    {
        return in_array($this->status, ['acknowledged', 'sent_to_supplier']);
    }

    /**
     * Check if PO can be marked as delivered.
     */
    public function canBeDelivered()
    {
        return $this->status === 'in_progress';
    }

    /**
     * Get the next possible status transitions.
     */
    public function getNextPossibleStatuses()
    {
        $transitions = [
            'draft' => ['pending_approval', 'cancelled'],
            'pending_approval' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['sent_to_supplier', 'cancelled'],
            'sent_to_supplier' => ['acknowledged', 'in_progress', 'cancelled'],
            'acknowledged' => ['in_progress', 'cancelled'],
            'in_progress' => ['delivered', 'cancelled'],
            'delivered' => ['completed'],
            'completed' => [],
            'cancelled' => [],
            'rejected' => ['draft', 'cancelled'],
        ];

        return $transitions[$this->status] ?? [];
    }

    /**
     * Record status change in history.
     */
    public function recordStatusChange($fromStatus, $toStatus, $userId, $notes = null, $metadata = [])
    {
        $this->statusHistory()->create([
            'status_from' => $fromStatus,
            'status_to' => $toStatus,
            'notes' => $notes,
            'changed_by' => $userId,
            'changed_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record modification.
     */
    public function recordModification($fieldName, $oldValue, $newValue, $userId, $reason = null)
    {
        return $this->modifications()->create([
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'modified_by' => $userId,
            'modified_at' => now(),
        ]);
    }
}
