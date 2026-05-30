<?php

namespace App\Repositories;

use App\Models\Dish;
use App\Repositories\Contracts\DishRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DishRepository implements DishRepositoryInterface
{
    /**
     * Lấy danh sách dishes cho admin (tất cả)
     */
    public function getPaginatedForAdmin(int $perPage, int $page): LengthAwarePaginator
    {
        return Dish::query()
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Lấy danh sách dishes cho user (tất cả)
     */
    public function getPaginatedForUser(int $perPage, int $page): LengthAwarePaginator
    {
        return Dish::query()
            ->where('status', '!=', Dish::STATUS_HIDDEN)
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Lấy danh sách dishes cho user (theo category)
     */
    public function getPaginatedByCategoryId(array $categoryIds, int $perPage, int $page): LengthAwarePaginator
    {
        return Dish::query()
            ->whereIn('category_id', $categoryIds)
            ->where('status', '!=', Dish::STATUS_HIDDEN)
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Lấy danh sách dishes cũ (deprecated - giữ để backward compatible)
     */
    public function getPaginatedOrderByCreatedAtDesc(int $perPage, int $page): LengthAwarePaginator
    {
        return Dish::query()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findById(int $id): ?Dish
    {
        return Dish::query()->find($id);
    }

    public function findByIdOrFail(int $id): Dish
    {
        return Dish::query()->findOrFail($id);
    }

    public function create(array $attributes): Dish
    {
        return Dish::query()->create($attributes);
    }

    public function update(Dish $dish, array $attributes): bool
    {
        return $dish->update($attributes);
    }

    public function delete(Dish $dish): bool
    {
        return (bool) $dish->delete();
    }
}
