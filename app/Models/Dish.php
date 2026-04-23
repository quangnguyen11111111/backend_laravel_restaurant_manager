<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dish extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'Available';
    public const STATUS_UNAVAILABLE = 'Unavailable';
    public const STATUS_HIDDEN = 'Hidden';

    public const STATUS_VALUES = [
        self::STATUS_AVAILABLE,
        self::STATUS_UNAVAILABLE,
        self::STATUS_HIDDEN,
    ];

    protected $fillable = [
        'name',
        'price',
        'description',
        'image',
        'image_s3_key',
        'status',
    ];

    protected $casts = [
        'price' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
