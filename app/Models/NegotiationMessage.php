<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NegotiationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'negotiation_id',
        'sender_id',
        'message',
        'message_type',
        'offer_data',
        'offer_status',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'offer_data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the negotiation that this message belongs to.
     */
    public function negotiation()
    {
        return $this->belongsTo(Negotiation::class);
    }

    /**
     * Get the user who sent this message.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get attachments for this message.
     */
    public function attachments()
    {
        return $this->hasMany(NegotiationAttachment::class, 'message_id');
    }

    /**
     * Mark message as read.
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Check if message is a counter offer.
     */
    public function isCounterOffer()
    {
        return $this->message_type === 'counter_offer';
    }

    /**
     * Check if message is an acceptance.
     */
    public function isAcceptance()
    {
        return $this->message_type === 'acceptance';
    }

    /**
     * Check if message is a rejection.
     */
    public function isRejection()
    {
        return $this->message_type === 'rejection';
    }

    /**
     * Check if message is a text message.
     */
    public function isText()
    {
        return $this->message_type === 'text';
    }

    /**
     * Get formatted offer data for display.
     */
    public function getFormattedOfferData()
    {
        if (!$this->offer_data) {
            return null;
        }

        $data = $this->offer_data;
        $formatted = [];

        if (isset($data['total_amount'])) {
            $formatted['Total Amount'] = '$' . number_format($data['total_amount'], 2);
        }

        if (isset($data['delivery_time'])) {
            $formatted['Delivery Time'] = $data['delivery_time'] . ' days';
        }

        if (isset($data['terms'])) {
            $formatted['Terms'] = $data['terms'];
        }

        if (isset($data['notes'])) {
            $formatted['Notes'] = $data['notes'];
        }

        return $formatted;
    }

    /**
     * Scope to get unread messages.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to get messages by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('message_type', $type);
    }

    /**
     * Scope to get counter offers.
     */
    public function scopeCounterOffers($query)
    {
        return $query->where('message_type', 'counter_offer');
    }
}