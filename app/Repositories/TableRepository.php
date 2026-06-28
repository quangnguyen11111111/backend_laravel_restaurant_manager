<?php

namespace App\Repositories;

use App\Models\Table;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TableRepository implements TableRepositoryInterface
{
    public function getAllOrderByCreatedAtDesc(): Collection
    {
        return Table::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByNumber(int $number): ?Table
    {
        return Table::query()->where('number', $number)->first();
    }

    public function getByIds(array $numbers): Collection
    {
        return Table::query()->whereIn('number', $numbers)->get();
    }

    public function create(array $attributes): Table
    {
        return Table::query()->create($attributes);
    }

    public function update(Table $table, array $attributes): bool
    {
        return $table->update($attributes);
    }

    public function delete(Table $table): bool
    {
        return (bool) $table->delete();
    }
}
