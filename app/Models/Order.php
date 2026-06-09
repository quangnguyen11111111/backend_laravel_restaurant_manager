<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING_ARRIVAL = 'Pending_Arrival';
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_PAID = 'Paid';
    public const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'table_number',
        'guest_id',
        'guest_count',
        'session_pin',
        'customer_name',
        'customer_phone',
        'reservation_time',
        'status',
    ];

    protected $casts = [
        'reservation_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class, 'table_number', 'number');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }
}
