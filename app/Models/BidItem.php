<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BidItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bid_id',
        'rfq_item_id',
        'item_name',
        'item_description',
        'quantity',
        'unit_of_measure',
        'unit_price',
        'total_price',
        'currency',
        'delivery_date',
        'technical_specifications',
        'brand_model',
        'warranty',
        'custom_fields',
        'is_available',
        'availability_notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'delivery_date' => 'date',
        'custom_fields' => 'array',
        'is_available' => 'boolean',
    ];

    /**
     * Get the bid that owns this item.
     */
    public function bid()
    {
        return $this->belongsTo(Bid::class);
    }

    /**
     * Get the RFQ item details.
     */
    public function rfqItem()
    {
        return $this->belongsTo(RfqItem::class);
    }
}
