<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id',
        'uploaded_by',
        'filename',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'file_type',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_primary' => 'boolean',
    ];

    /**
     * Get the item that this attachment belongs to.
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
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
        return !$this->isImage();
    }

    /**
     * Get file type based on mime type.
     */
    public function getFileTypeAttribute()
    {
        if ($this->isImage()) {
            return 'image';
        }
        
        $documentTypes = [
            'application/pdf' => 'pdf',
            'application/msword' => 'word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'word',
            'application/vnd.ms-excel' => 'excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
        ];
        
        return $documentTypes[$this->mime_type] ?? 'document';
    }
}
