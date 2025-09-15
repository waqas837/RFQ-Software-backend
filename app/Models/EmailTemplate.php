<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'subject',
        'content',
        'type',
        'placeholders',
        'is_active',
        'is_default',
        'version',
        'created_by',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the user who created this template.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the latest version of a template by slug.
     */
    public static function getLatestBySlug($slug)
    {
        return static::where('slug', $slug)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();
    }

    /**
     * Get default template by type.
     */
    public static function getDefaultByType($type)
    {
        return static::where('type', $type)
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();
    }

    /**
     * Create a new version of this template.
     */
    public function createNewVersion($data)
    {
        $newVersion = $this->replicate();
        $newVersion->version = $this->version + 1;
        $newVersion->fill($data);
        $newVersion->save();

        return $newVersion;
    }

    /**
     * Replace placeholders in content and subject.
     */
    public function replacePlaceholders($data)
    {
        $content = $this->content;
        $subject = $this->subject;

        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $content = str_replace($placeholder, $value, $content);
            $subject = str_replace($placeholder, $value, $subject);
        }

        return [
            'subject' => $subject,
            'content' => $content,
        ];
    }

    /**
     * Get available placeholders for this template type.
     */
    public static function getPlaceholdersByType($type)
    {
        $placeholders = [
            'rfq_invitation' => [
                'supplier_name' => 'Supplier company name',
                'rfq_title' => 'RFQ title',
                'rfq_description' => 'RFQ description',
                'deadline' => 'Bid submission deadline',
                'buyer_name' => 'Buyer company name',
                'rfq_link' => 'Link to view RFQ',
                'contact_email' => 'Contact email for questions',
            ],
            'rfq_published' => [
                'rfq_title' => 'RFQ title',
                'rfq_description' => 'RFQ description',
                'deadline' => 'Bid submission deadline',
                'buyer_name' => 'Buyer company name',
                'published_date' => 'Date RFQ was published',
                'rfq_link' => 'Link to view RFQ',
            ],
            'bid_submitted' => [
                'supplier_name' => 'Supplier company name',
                'rfq_title' => 'RFQ title',
                'bid_amount' => 'Total bid amount',
                'submission_date' => 'Bid submission date',
                'buyer_name' => 'Buyer company name',
                'bid_link' => 'Link to view bid details',
            ],
            'bid_confirmation' => [
                'supplier_name' => 'Supplier company name',
                'rfq_title' => 'RFQ title',
                'bid_amount' => 'Total bid amount',
                'submission_date' => 'Bid submission date',
                'confirmation_number' => 'Bid confirmation number',
                'rfq_deadline' => 'RFQ deadline',
            ],
            'deadline_reminder' => [
                'supplier_name' => 'Supplier company name',
                'rfq_title' => 'RFQ title',
                'deadline' => 'Bid submission deadline',
                'days_remaining' => 'Days remaining until deadline',
                'rfq_link' => 'Link to view RFQ',
                'buyer_name' => 'Buyer company name',
            ],
            'status_change' => [
                'user_name' => 'User name',
                'rfq_title' => 'RFQ title',
                'old_status' => 'Previous status',
                'new_status' => 'New status',
                'change_date' => 'Date of status change',
                'changed_by' => 'User who made the change',
                'rfq_link' => 'Link to view RFQ',
            ],
            'po_generated' => [
                'supplier_name' => 'Supplier company name',
                'po_number' => 'Purchase order number',
                'po_amount' => 'Purchase order amount',
                'rfq_title' => 'Original RFQ title',
                'delivery_date' => 'Expected delivery date',
                'buyer_name' => 'Buyer company name',
                'po_link' => 'Link to view purchase order',
            ],
            'general_notification' => [
                'user_name' => 'User name',
                'message' => 'Notification message',
                'action_required' => 'Action required (if any)',
                'link' => 'Link to relevant page',
                'company_name' => 'Company name',
            ],
        ];

        return $placeholders[$type] ?? [];
    }

    /**
     * Generate slug from name.
     */
    public static function generateSlug($name)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Scope to get active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get templates by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get default templates.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
