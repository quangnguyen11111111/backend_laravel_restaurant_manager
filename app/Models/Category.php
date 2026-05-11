<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    public const STATUS_VALUES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'name',
        'parent_id',
        'status',
        'order',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Danh mục cha
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Danh mục con
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order')->orderBy('name');
    }

    /**
     * Tất cả danh mục con (recursive)
     */
    public function allChildren(): HasMany
    {
        return $this->children();
    }
    
    public function getAllChildrenIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {

            $ids[] = $child->id;

            $ids = array_merge(
                $ids,
                $child->getAllChildrenIds()
            );
        }

        return $ids;
    }
}
