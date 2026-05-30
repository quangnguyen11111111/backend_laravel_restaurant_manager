<?php

namespace App\Repositories;

use App\Models\DishSnapshot;
use App\Repositories\Contracts\DishSnapshotRepositoryInterface;

class DishSnapshotRepository implements DishSnapshotRepositoryInterface
{
    public function create(array $attributes): DishSnapshot
    {
        return DishSnapshot::query()->create($attributes);
    }
}
