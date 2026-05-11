<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_PROCESSING = 'Processing';
    public const STATUS_DELIVERED = 'Delivered';
    public const STATUS_PAID = 'Paid';

    protected $fillable = [
        'dish_snapshot_id',
        'guest_id',
        'quantity',
        'table_number',
        'order_handler_id',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dishSnapshot()
    {
        return $this->belongsTo(DishSnapshot::class, 'dish_snapshot_id');
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function orderHandler()
    {
        return $this->belongsTo(Account::class, 'order_handler_id');
    }

    public function table()
    {
        return $this->belongsTo(Table::class, 'table_number', 'number');
    }
}
