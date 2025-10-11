<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderModification extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_modifications';

    protected $fillable = [
        'purchase_order_id',
        'field_name',
        'old_value',
        'new_value',
        'reason',
        'modified_by',
        'modified_at',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
    ];

    protected $casts = [
        'modified_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the purchase order that owns this modification.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the user who made the modification.
     */
    public function modifiedBy()
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    /**
     * Get the user who approved the modification.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope to get pending modifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved modifications.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected modifications.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}