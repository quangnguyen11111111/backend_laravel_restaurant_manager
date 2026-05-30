<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'table_number',
        'refresh_token',
        'refresh_token_expires_at',
    ];

    protected $casts = [
        'refresh_token_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constants for role
    const ROLE_GUEST = 'Guest';

    public function getRoleAttribute(): string
    {
        return self::ROLE_GUEST;
    }

    /**
     * Get the table associated with the guest
     */
    public function table()
    {
        return $this->belongsTo(Table::class, 'table_number', 'number');
    }

    /**
     * Get the orders for the guest
     */
    public function orders()
    {
        // return $this->hasMany(Order::class);
    }
}
