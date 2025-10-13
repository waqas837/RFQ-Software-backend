<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'key',
        'secret',
        'is_active',
        'last_used_at',
        'expires_at',
        'permissions',
        'rate_limit'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'permissions' => 'array'
    ];

    protected $hidden = [
        'secret'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    public static function generateKey(): string
    {
        return 'sp_' . Str::random(32);
    }

    public static function generateSecret(): string
    {
        return Str::random(64);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function getMaskedKey(): string
    {
        return substr($this->key, 0, 8) . '...' . substr($this->key, -4);
    }
}
