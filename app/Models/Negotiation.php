<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Negotiation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rfq_id',
        'bid_id',
        'initiated_by',
        'supplier_id',
        'status',
        'initial_message',
        'counter_offer_data',
        'last_activity_at',
        'closed_at',
        'purchase_order_id',
    ];

    protected $casts = [
        'counter_offer_data' => 'array',
        'last_activity_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Get the RFQ that this negotiation belongs to.
     */
    public function rfq()
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * Get the bid that this negotiation is about.
     */
    public function bid()
    {
        return $this->belongsTo(Bid::class);
    }

    /**
     * Get the user who initiated this negotiation.
     */
    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Get the supplier involved in this negotiation.
     */
    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    /**
     * Get all messages in this negotiation.
     */
    public function messages()
    {
        return $this->hasMany(NegotiationMessage::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get all attachments in this negotiation.
     */
    public function attachments()
    {
        return $this->hasMany(NegotiationAttachment::class);
    }

    /**
     * Get the purchase order created from this negotiation.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(\App\Models\PurchaseOrder::class);
    }

    /**
     * Get the latest message in this negotiation.
     */
    public function latestMessage()
    {
        return $this->hasOne(NegotiationMessage::class)->latest();
    }

    /**
     * Check if negotiation is active.
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if negotiation is closed.
     */
    public function isClosed()
    {
        return $this->status === 'closed';
    }

    /**
     * Check if negotiation is cancelled.
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Close the negotiation.
     */
    public function close()
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Cancel the negotiation.
     */
    public function cancel()
    {
        $this->update([
            'status' => 'cancelled',
            'closed_at' => now(),
        ]);
    }

    /**
     * Update last activity timestamp.
     */
    public function updateLastActivity()
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Get unread messages count for a user.
     */
    public function getUnreadMessagesCount($userId)
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark all messages as read for a user.
     */
    public function markMessagesAsRead($userId)
    {
        $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Scope to get active negotiations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get negotiations for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('initiated_by', $userId)
              ->orWhere('supplier_id', $userId);
        });
    }

    /**
     * Scope to get negotiations for a specific RFQ.
     */
    public function scopeForRfq($query, $rfqId)
    {
        return $query->where('rfq_id', $rfqId);
    }
}