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

    public function findById(int $id): ?Guest
    {
        return Guest::query()->find($id);
    }

    public function create(array $attributes): Guest
    {
        return Guest::query()->create($attributes);
    }

    public function update(Guest $guest, array $attributes): bool
    {
        return $guest->update($attributes);
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

    public function clearRefreshTokensByTableNumber(int $tableNumber): int
    {
        return Guest::query()
            ->whereHas('order', function ($query) use ($tableNumber) {
                $query->where('table_number', $tableNumber)
                      ->orWhereHas('tables', function ($q) use ($tableNumber) {
                          $q->where('tables.number', $tableNumber);
                      });
            })
            ->update([
                'refresh_token' => null,
                'refresh_token_expires_at' => null,
            ]);
    }
}
