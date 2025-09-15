<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'data',
        'user_id',
        'related_user_id',
        'related_entity_id',
        'related_entity_type',
        'is_read',
        'read_at',
        'is_email_sent',
        'email_sent_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_email_sent' => 'boolean',
        'read_at' => 'datetime',
        'email_sent_at' => 'datetime',
    ];

    // Notification types
    const TYPE_RFQ_CREATED = 'rfq_created';
    const TYPE_RFQ_PUBLISHED = 'rfq_published';
    const TYPE_RFQ_CLOSED = 'rfq_closed';
    const TYPE_BID_SUBMITTED = 'bid_submitted';
    const TYPE_BID_AWARDED = 'bid_awarded';
    const TYPE_BID_REJECTED = 'bid_rejected';
    const TYPE_PO_CREATED = 'po_created';
    const TYPE_PO_APPROVED = 'po_approved';
    const TYPE_PO_SENT = 'po_sent';
    const TYPE_PO_DELIVERED = 'po_delivered';
    const TYPE_USER_REGISTERED = 'user_registered';
    const TYPE_SUPPLIER_APPROVED = 'supplier_approved';

    /**
     * Get the user who receives this notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who triggered this notification.
     */
    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    /**
     * Get the related entity (RFQ, Bid, PurchaseOrder, etc.).
     */
    public function relatedEntity(): MorphTo
    {
        return $this->morphTo('related_entity', 'related_entity_type', 'related_entity_id');
    }

    /**
     * Get the related entity with proper type mapping.
     */
    public function getRelatedEntityAttribute()
    {
        if (!$this->related_entity_type || !$this->related_entity_id) {
            return null;
        }

        // Map lowercase types to proper class names
        $typeMap = [
            'bid' => 'App\\Models\\Bid',
            'rfq' => 'App\\Models\\Rfq',
            'purchaseorder' => 'App\\Models\\PurchaseOrder',
            'user' => 'App\\Models\\User',
        ];

        $type = $this->related_entity_type;
        $className = $typeMap[strtolower($type)] ?? $type;

        if (class_exists($className)) {
            return $className::find($this->related_entity_id);
        }

        return null;
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Mark email as sent.
     */
    public function markEmailAsSent(): void
    {
        $this->update([
            'is_email_sent' => true,
            'email_sent_at' => now(),
        ]);
    }

    /**
     * Scope for unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications.
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for notifications by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for recent notifications.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
