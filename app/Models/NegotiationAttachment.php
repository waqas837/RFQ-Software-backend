<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NegotiationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'negotiation_id',
        'message_id',
        'uploaded_by',
        'filename',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    /**
     * Get the negotiation that this attachment belongs to.
     */
    public function negotiation()
    {
        return $this->belongsTo(Negotiation::class);
    }

    /**
     * Get the message that this attachment belongs to.
     */
    public function message()
    {
        return $this->belongsTo(NegotiationMessage::class);
    }

    /**
     * Get the user who uploaded this attachment.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the full file path.
     */
    public function getFullPathAttribute()
    {
        return storage_path('app/' . $this->file_path);
    }

    /**
     * Get the public URL for the attachment.
     */
    public function getUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if file is an image.
     */
    public function isImage()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if file is a document.
     */
    public function isDocument()
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        
        return in_array($this->mime_type, $documentTypes);
    }

    /**
     * Scope to get attachments for a specific negotiation.
     */
    public function scopeForNegotiation($query, $negotiationId)
    {
        return $query->where('negotiation_id', $negotiationId);
    }

    /**
     * Scope to get attachments for a specific message.
     */
    public function scopeForMessage($query, $messageId)
    {
        return $query->where('message_id', $messageId);
    }

    /**
     * Scope to get images only.
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    /**
     * Scope to get documents only.
     */
    public function scopeDocuments($query)
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        
        return $query->whereIn('mime_type', $documentTypes);
    }
}