<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'field_definitions',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'field_definitions' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get items using this template.
     */
    public function items()
    {
        return $this->hasMany(Item::class, 'template_id');
    }

    /**
     * Scope to get active templates only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get templates by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get the default template.
     */
    public static function getDefault()
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Create custom fields for an item based on this template.
     */
    public function createCustomFieldsForItem($itemId)
    {
        $customFields = [];
        
        foreach ($this->field_definitions as $fieldDef) {
            $customFields[] = [
                'item_id' => $itemId,
                'field_name' => $fieldDef['name'],
                'field_type' => $fieldDef['type'],
                'field_options' => $fieldDef['options'] ?? null,
                'is_required' => $fieldDef['required'] ?? false,
                'sort_order' => $fieldDef['sort_order'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return ItemCustomField::insert($customFields);
    }

    /**
     * Get field definition by name.
     */
    public function getFieldDefinition($fieldName)
    {
        foreach ($this->field_definitions as $fieldDef) {
            if ($fieldDef['name'] === $fieldName) {
                return $fieldDef;
            }
        }
        return null;
    }
}
