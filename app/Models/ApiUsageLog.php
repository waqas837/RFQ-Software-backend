<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiUsageLog extends Model
{
    protected $fillable = [
        'user_id',
        'api_key_id',
        'endpoint',
        'method',
        'response_code',
        'response_time',
        'ip_address',
        'user_agent',
        'request_data',
        'response_data',
        'requested_at'
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'requested_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
