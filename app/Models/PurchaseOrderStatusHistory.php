<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_status_history';

    protected $fillable = [
        'purchase_order_id',
        'status_from',
        'status_to',
        'notes',
        'changed_by',
        'changed_at',
        'metadata',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the purchase order that owns this status history.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the user who changed the status.
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope to get recent status changes.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('changed_at', '>=', now()->subDays($days));
    }
}