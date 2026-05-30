<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderRepositoryInterface
{
    public function create(array $attributes): Order;

    public function findByIdWithRelations(int $id, array $relations = []): ?Order;

    public function findByIdOrFailWithRelations(int $id, array $relations = []): Order;

    public function getByGuestIdWithRelations(int $guestId, array $relations = []): Collection;

    public function getByFilters(?string $fromDate, ?string $toDate, array $relations = []): Collection;

    public function getByGuestIdAndStatuses(int $guestId, array $statuses): Collection;

    public function update(Order $order, array $attributes): bool;

    public function updateByIds(array $ids, array $attributes): int;

    public function getByIdsWithRelations(array $ids, array $relations = []): Collection;

    public function loadRelations(Order $order, array $relations = []): Order;
}
