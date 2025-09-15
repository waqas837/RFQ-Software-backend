<?php

namespace App\Enums;

enum FieldType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case DATE = 'date';
    case DROPDOWN = 'dropdown';
    case FILE = 'file';
    case BOOLEAN = 'boolean';
    case TEXTAREA = 'textarea';
    case EMAIL = 'email';
    case URL = 'url';
    case PHONE = 'phone';

    /**
     * Get all field types as array.
     */
    public static function toArray(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get field type label.
     */
    public function getLabel(): string
    {
        return match($this) {
            self::TEXT => 'Text',
            self::NUMBER => 'Number',
            self::DATE => 'Date',
            self::DROPDOWN => 'Dropdown',
            self::FILE => 'File',
            self::BOOLEAN => 'Yes/No',
            self::TEXTAREA => 'Long Text',
            self::EMAIL => 'Email',
            self::URL => 'URL',
            self::PHONE => 'Phone',
        };
    }

    /**
     * Check if field type requires options.
     */
    public function requiresOptions(): bool
    {
        return $this === self::DROPDOWN;
    }

    /**
     * Check if field type supports validation.
     */
    public function supportsValidation(): bool
    {
        return in_array($this, [self::TEXT, self::NUMBER, self::EMAIL, self::URL, self::PHONE]);
    }
}
