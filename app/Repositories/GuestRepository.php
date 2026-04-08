<?php

namespace App\Repositories;

use App\Models\Guest;
use App\Models\Table;
use App\Repositories\Contracts\GuestRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class GuestRepository implements GuestRepositoryInterface
{
    public function findTableByNumber(int $tableNumber): ?Table
    {
        return Table::query()->where('number', $tableNumber)->first();
    }

    public function create(array $attributes): Guest
    {
        return Guest::query()->create($attributes);
    }

    public function getByFilters(?string $fromDate, ?string $toDate): Collection
    {
        $query = Guest::query()->orderBy('created_at', 'desc');

        if (!empty($fromDate)) {
            $query->where('created_at', '>=', $fromDate);
        }

        if (!empty($toDate)) {
            $query->where('created_at', '<=', $toDate);
        }

        return $query->get();
    }
}
