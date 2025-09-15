<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RfqItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfq_id',
        'item_id',
        'item_name',
        'item_description',
        'quantity',
        'unit_of_measure',
        'specifications',
        'custom_fields',
        'estimated_price',
        'currency',
        'delivery_date',
        'sort_order',
    ];

    protected $casts = [
        'specifications' => 'array',
        'custom_fields' => 'array',
        'quantity' => 'integer',
        'estimated_price' => 'decimal:2',
        'delivery_date' => 'date',
        'sort_order' => 'integer',
    ];

    /**
     * Get the RFQ that owns this item.
     */
    public function rfq()
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * Get the item details.
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
