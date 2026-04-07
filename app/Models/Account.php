<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Account extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'owner_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constants for roles
    const ROLE_OWNER = 'Owner';
    const ROLE_EMPLOYEE = 'Employee';

    /**
     * Get the employees of this owner
     */
    public function employees()
    {
        return $this->hasMany(Account::class, 'owner_id');
    }

    /**
     * Get the owner of this employee
     */
    public function owner()
    {
        return $this->belongsTo(Account::class, 'owner_id');
    }

    /**
     * Get the refresh tokens for the account
     */
    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * Get the orders handled by this account
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'order_handler_id');
    }

    /**
     * Check if account is Owner
     */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Check if account is Employee
     */
    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }
}
