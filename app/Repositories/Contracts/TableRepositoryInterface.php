<?php

namespace App\Repositories\Contracts;

use App\Models\Table;
use Illuminate\Database\Eloquent\Collection;

interface TableRepositoryInterface
{
    public function getAllOrderByCreatedAtDesc(): Collection;

    public function findByNumber(int $number): ?Table;

    public function create(array $attributes): Table;

    public function update(Table $table, array $attributes): bool;

    public function delete(Table $table): bool;
}
