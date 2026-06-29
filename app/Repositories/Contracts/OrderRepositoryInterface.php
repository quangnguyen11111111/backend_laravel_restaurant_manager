<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderRepositoryInterface
{
    public function create(array $attributes): Order;
    //hàm dùng để tìm hóa đơn theo id và các quan hệ của nó
    public function findByIdWithRelations(int $id, array $relations = []): ?Order;
    //hàm dùng để tìm hóa đơn theo id và các quan hệ của nó
    public function findByIdOrFailWithRelations(int $id, array $relations = []): Order;
    //hàm dùng để tìm hóa đơn theo id khách hàng và các quan hệ của nó
    public function getByGuestIdWithRelations(int $guestId, array $relations = []): Collection;

    public function getByFilters(?string $fromDate, ?string $toDate, array $relations = []): Collection;

    public function getByGuestIdAndStatuses(int $guestId, array $statuses): Collection;

    public function update(Order $order, array $attributes): bool;

    public function updateByIds(array $ids, array $attributes): int;

    public function getByIdsWithRelations(array $ids, array $relations = []): Collection;

    public function loadRelations(Order $order, array $relations = []): Order;
}
