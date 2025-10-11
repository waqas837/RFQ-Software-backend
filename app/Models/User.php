<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'position',
        'department',
        'role',
        'status',
        'email_verified_at',
        'email_verification_token',
        'password_reset_token',
        'profile_image',
        'permissions',
        'last_login_at',
        'last_login_ip',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the companies that the user belongs to.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'user_company');
    }

    /**
     * Get the RFQs created by this user.
     */
    public function rfqs()
    {
        return $this->hasMany(Rfq::class, 'created_by');
    }

    /**
     * Get the bids submitted by this user.
     */
    public function bids()
    {
        return $this->hasMany(Bid::class, 'supplier_id');
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin()
    {
        return $this->hasRole('admin') || $this->role === 'admin';
    }

    /**
     * Check if user is buyer.
     */
    public function isBuyer()
    {
        return $this->hasRole('buyer') || $this->role === 'buyer';
    }

    /**
     * Check if user is supplier.
     */
    public function isSupplier()
    {
        return $this->hasRole('supplier') || $this->role === 'supplier';
    }

    /**
     * Check if user is active.
     */
    public function isActive()
    {
        return $this->status === 'active';
    }
}
