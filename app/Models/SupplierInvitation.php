<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SupplierInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'token',
        'rfq_id',
        'invited_by',
        'company_name',
        'contact_name',
        'status',
        'expires_at',
        'registered_at',
        'registered_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    /**
     * Generate a unique invitation token.
     */
    public static function generateToken()
    {
        do {
            $token = Str::random(64);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Create a new supplier invitation.
     */
    public static function createInvitation($email, $rfqId, $invitedBy, $companyName = null, $contactName = null)
    {
        return self::create([
            'email' => $email,
            'token' => self::generateToken(),
            'rfq_id' => $rfqId,
            'invited_by' => $invitedBy,
            'company_name' => $companyName,
            'contact_name' => $contactName,
            'status' => 'pending',
            'expires_at' => now()->addDays(7), // 7 days expiry
        ]);
    }

    /**
     * Check if invitation is valid and not expired.
     */
    public function isValid()
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    /**
     * Mark invitation as registered.
     */
    public function markAsRegistered($userId)
    {
        $this->update([
            'status' => 'registered',
            'registered_at' => now(),
            'registered_user_id' => $userId,
        ]);
    }

    /**
     * Mark invitation as expired.
     */
    public function markAsExpired()
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Get the RFQ this invitation is for.
     */
    public function rfq()
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * Get the user who sent the invitation.
     */
    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get the user who registered from this invitation.
     */
    public function registeredUser()
    {
        return $this->belongsTo(User::class, 'registered_user_id');
    }

    /**
     * Scope to get valid invitations.
     */
    public function scopeValid($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired invitations.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
                    ->where('status', 'pending');
    }
}