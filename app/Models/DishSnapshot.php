<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DishSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'image',
        'name',
        'price',
        'dish_id',
        'status',
    ];

    protected $casts = [
        'price' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }
}
