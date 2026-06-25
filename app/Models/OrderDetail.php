<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_PROCESSING = 'Processing';
    public const STATUS_DELIVERED = 'Delivered';
    public const STATUS_CANCELLED = 'Cancelled';

    protected $fillable = [
        'order_id',
        'guest_id',
        'dish_id',
        'dish_name',
        'dish_price',
        'dish_image',
        'quantity',
        'status',
        'note',
        'order_handler_id',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }

    public function orderHandler()
    {
        return $this->belongsTo(Account::class, 'order_handler_id');
    }

    public function state(): \App\Patterns\State\OrderDetail\OrderDetailState
    {
        return match($this->status) {
            self::STATUS_PENDING => new \App\Patterns\State\OrderDetail\PendingDetailState(),
            self::STATUS_PROCESSING => new \App\Patterns\State\OrderDetail\ProcessingDetailState(),
            self::STATUS_DELIVERED => new \App\Patterns\State\OrderDetail\DeliveredDetailState(),
            self::STATUS_CANCELLED => new \App\Patterns\State\OrderDetail\CancelledDetailState(),
            default => throw new \Exception('Invalid order detail status: ' . $this->status),
        };
    }
}
