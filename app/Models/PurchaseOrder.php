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
     * Get the PO items.
     */
    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
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
}
