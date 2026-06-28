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

    public function tables()
    {
        return $this->belongsToMany(Table::class, 'order_tables', 'order_id', 'table_number')->withTimestamps();
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function state(): \App\Patterns\State\Order\OrderState
    {
        return match($this->status) {
            self::STATUS_PENDING_ARRIVAL => new \App\Patterns\State\Order\PendingArrivalOrderState(),
            self::STATUS_ACTIVE => new \App\Patterns\State\Order\ActiveOrderState(),
            self::STATUS_PAID => new \App\Patterns\State\Order\PaidOrderState(),
            self::STATUS_CANCELLED => new \App\Patterns\State\Order\CancelledOrderState(),
            default => throw new \Exception('Invalid order status: ' . $this->status),
        };
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d\TH:i:s');
    }
}
