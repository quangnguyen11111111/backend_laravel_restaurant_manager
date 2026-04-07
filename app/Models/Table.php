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

    /**
     * Get the orders for the table
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'table_number', 'number');
    }

    /**
     * Get the guests at the table
     */
    public function guests()
    {
        return $this->hasMany(Guest::class, 'table_number', 'number');
    }
}
