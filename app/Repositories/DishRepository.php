<?php

namespace App\Repositories;

use App\Models\Dish;
use App\Repositories\Contracts\DishRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class DishRepository implements DishRepositoryInterface
{
    public function getAllOrderByCreatedAtDesc(): Collection
    {
        return Dish::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findById(int $id): ?Dish
    {
        return Dish::query()->find($id);
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
