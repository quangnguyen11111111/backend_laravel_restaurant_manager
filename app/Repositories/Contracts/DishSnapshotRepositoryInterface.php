<?php

namespace App\Repositories\Contracts;

use App\Models\DishSnapshot;

interface DishSnapshotRepositoryInterface
{
    public function create(array $attributes): DishSnapshot;
}
