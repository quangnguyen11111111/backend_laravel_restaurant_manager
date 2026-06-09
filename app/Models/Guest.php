<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'order_id',
        'refresh_token',
        'refresh_token_expires_at',
    ];

    protected $casts = [
        'refresh_token_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['role'];

    // Constants for role
    const ROLE_GUEST = 'Guest';

    public function getRoleAttribute(): string
    {
        return self::ROLE_GUEST;
    }

    /**
     * Get the order associated with the guest
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the order details created by the guest
     */
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }
}
