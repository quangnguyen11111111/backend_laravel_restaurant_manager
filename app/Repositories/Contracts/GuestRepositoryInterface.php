<?php

namespace App\Repositories\Contracts;

use App\Models\Guest;
use App\Models\Table;
use Illuminate\Support\Collection;

interface GuestRepositoryInterface
{
    public function findTableByNumber(int $tableNumber): ?Table;

    public function create(array $attributes): Guest;

    public function getByFilters(?string $fromDate, ?string $toDate): Collection;
}
