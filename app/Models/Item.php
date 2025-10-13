<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'category_id',
        'specifications',
        'unit_of_measure',
        'is_active',
        'created_by',
        'company_id',
        'template_id',
    ];

    protected $casts = [
        'specifications' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the item.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the user who created the item.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the RFQ items that use this item.
     */
    public function rfqItems()
    {
        return $this->hasMany(RfqItem::class);
    }

    /**
     * Get the bid items that use this item.
     */
    public function bidItems()
    {
        return $this->hasMany(BidItem::class);
    }

    /**
     * Get the template that this item uses.
     */
    public function template()
    {
        return $this->belongsTo(ItemTemplate::class);
    }

    /**
     * Get the custom fields for this item.
     */
    public function customFields()
    {
        return $this->hasMany(ItemCustomField::class)->orderBy('sort_order');
    }

    /**
     * Get the attachments for this item.
     */
    public function attachments()
    {
        return $this->hasMany(ItemAttachment::class)->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * Get the primary image for this item.
     */
    public function primaryImage()
    {
        return $this->hasOne(ItemAttachment::class)->where('is_primary', true)->where('file_type', 'image');
    }

    /**
     * Get all images for this item.
     */
    public function images()
    {
        return $this->attachments()->where('file_type', 'image');
    }

    /**
     * Get all documents for this item.
     */
    public function documents()
    {
        return $this->attachments()->where('file_type', '!=', 'image');
    }

    /**
     * Get a custom field by name.
     */
    public function getCustomField($fieldName)
    {
        return $this->customFields()->where('field_name', $fieldName)->first();
    }

    /**
     * Set a custom field value.
     */
    public function setCustomField($fieldName, $value)
    {
        $field = $this->getCustomField($fieldName);
        if ($field) {
            $field->update(['field_value' => $value]);
        }
        return $field;
    }

    /**
     * Get all custom field values as an array.
     */
    public function getCustomFieldsArray()
    {
        return $this->customFields->pluck('field_value', 'field_name')->toArray();
    }

    /**
     * Scope to get active items only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to search items by name or description.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
