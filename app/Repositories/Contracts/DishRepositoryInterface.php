<?php

namespace App\Repositories\Contracts;

use App\Models\Dish;
use Illuminate\Database\Eloquent\Collection;

interface DishRepositoryInterface
{
    public function getAllOrderByCreatedAtDesc(): Collection;

    public function findById(int $id): ?Dish;

    public function create(array $attributes): Dish;

    public function update(Dish $dish, array $attributes): bool;

    public function delete(Dish $dish): bool;
}
