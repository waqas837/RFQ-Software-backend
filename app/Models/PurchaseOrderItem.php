<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'rfq_item_id',
        'item_name',
        'item_description',
        'quantity',
        'unit_price',
        'total_price',
        'unit_of_measure',
        'specifications',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'specifications' => 'array',
    ];

    /**
     * Get the purchase order that owns this item.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the RFQ item this PO item is based on.
     */
    public function rfqItem()
    {
        return $this->belongsTo(RfqItem::class);
    }
}