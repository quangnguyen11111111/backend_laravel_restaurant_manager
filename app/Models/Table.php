<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    use HasFactory;

    protected $primaryKey = 'number';
    public $incrementing = false;

    protected $fillable = [
        'number',
        'capacity',
        'group_id',
        'group_order',
        'max_capacity',
        'status',
        'token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constants for status
    const STATUS_AVAILABLE = 'Available';
    const STATUS_HIDDEN = 'Hidden';
    const STATUS_RESERVED = 'Reserved';
    const STATUS_OCCUPIED = 'Occupied';
    const STATUS_VALUES = [
        self::STATUS_AVAILABLE,
        self::STATUS_HIDDEN,
        self::STATUS_RESERVED,
        self::STATUS_OCCUPIED,
    ];

    /**
     * Get the orders for the table
     */
    public function orders()
    {
        // Old relation: return $this->hasMany(Order::class, 'table_number', 'number');
        // New relation: BelongsToMany
        return $this->belongsToMany(Order::class, 'order_tables', 'table_number', 'order_id')->withTimestamps();
    }
}
