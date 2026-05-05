<?php

namespace App\Repositories\Contracts;

use App\Models\Dish;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DishRepositoryInterface
{
    /**
     * Lấy danh sách dishes cho admin (tất cả)
     */
    public function getPaginatedForAdmin(int $perPage, int $page): LengthAwarePaginator;

    /**
     * Lấy danh sách dishes cho user (theo category)
     */
    public function getPaginatedByCategoryId(int $categoryId, int $perPage, int $page): LengthAwarePaginator;

    /**
     * Lấy danh sách dishes cũ (deprecated - giữ để backward compatible)
     */
    public function getPaginatedOrderByCreatedAtDesc(int $perPage, int $page): LengthAwarePaginator;

    public function findById(int $id): ?Dish;

    public function create(array $attributes): Dish;

    public function update(Dish $dish, array $attributes): bool;

    public function delete(Dish $dish): bool;
}
