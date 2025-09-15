<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'registration_number',
        'tax_id',
        'website',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'logo',
        'type',
        'status',
        'business_hours',
        'payment_terms',
        'description',
        'certifications',
        'capabilities',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'payment_terms' => 'array',
        'certifications' => 'array',
        'capabilities' => 'array',
    ];

    /**
     * Get the users that belong to this company.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_company');
    }

    /**
     * Get the RFQs created by this company.
     */
    public function rfqs()
    {
        return $this->hasMany(Rfq::class);
    }

    /**
     * Get the bids submitted by this company.
     */
    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Get the suppliers associated with this company.
     */
    public function suppliers()
    {
        return $this->belongsToMany(Company::class, 'rfq_suppliers', 'buyer_company_id', 'supplier_company_id');
    }

    /**
     * Get the buyers associated with this company.
     */
    public function buyers()
    {
        return $this->belongsToMany(Company::class, 'rfq_suppliers', 'supplier_company_id', 'buyer_company_id');
    }
}
