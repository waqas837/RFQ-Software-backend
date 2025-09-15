<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\FieldType;

class ItemCustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'field_name',
        'field_type',
        'field_value',
        'field_options',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'field_options' => 'array',
        'is_required' => 'boolean',
    ];

    /**
     * Get the item that owns the custom field.
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the field type enum.
     */
    public function getFieldTypeAttribute($value)
    {
        return FieldType::from($value);
    }

    /**
     * Set the field type enum.
     */
    public function setFieldTypeAttribute($value)
    {
        $this->attributes['field_type'] = $value instanceof FieldType ? $value->value : $value;
    }

    /**
     * Get formatted field value based on type.
     */
    public function getFormattedValueAttribute()
    {
        return match($this->field_type) {
            FieldType::BOOLEAN => $this->field_value ? 'Yes' : 'No',
            FieldType::DATE => $this->field_value ? \Carbon\Carbon::parse($this->field_value)->format('Y-m-d') : null,
            FieldType::NUMBER => $this->field_value ? number_format($this->field_value, 2) : null,
            default => $this->field_value,
        };
    }

    /**
     * Validate field value based on type and options.
     */
    public function validateValue($value)
    {
        if ($this->is_required && empty($value)) {
            return false;
        }

        return match($this->field_type) {
            FieldType::EMAIL => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            FieldType::URL => filter_var($value, FILTER_VALIDATE_URL) !== false,
            FieldType::NUMBER => is_numeric($value),
            FieldType::DATE => \Carbon\Carbon::createFromFormat('Y-m-d', $value) !== false,
            FieldType::DROPDOWN => in_array($value, $this->field_options['options'] ?? []),
            default => true,
        };
    }
}
