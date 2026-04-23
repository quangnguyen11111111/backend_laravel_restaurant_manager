<?php

namespace App\Repositories\Contracts;

use App\Models\Dish;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DishRepositoryInterface
{
    public function getPaginatedOrderByCreatedAtDesc(int $perPage, int $page): LengthAwarePaginator;

    public function findById(int $id): ?Dish;

    public function create(array $attributes): Dish;

    public function update(Dish $dish, array $attributes): bool;

    public function delete(Dish $dish): bool;
}
