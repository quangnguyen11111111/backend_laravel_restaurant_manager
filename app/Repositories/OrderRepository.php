<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository implements OrderRepositoryInterface
{
    public function create(array $attributes): Order
    {
        return Order::query()->create($attributes);
    }

    public function findByIdWithRelations(int $id, array $relations = []): ?Order
    {
        $query = Order::query();

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    public function findByIdOrFailWithRelations(int $id, array $relations = []): Order
    {
        $query = Order::query();

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->findOrFail($id);
    }

    public function getByGuestIdWithRelations(int $guestId, array $relations = []): Collection
    {
        $query = Order::query()
            ->where('guest_id', $guestId)
            ->orderBy('created_at', 'desc');

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->get();
    }

    public function getByFilters(?string $fromDate, ?string $toDate, array $relations = []): Collection
    {
        $query = Order::query()->orderBy('created_at', 'desc');

        if ($relations !== []) {
            $query->with($relations);
        }

        if (!empty($fromDate)) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($fromDate));
        }

        if (!empty($toDate)) {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($toDate));
        }

        return $query->get();
    }

    public function getByGuestIdAndStatuses(int $guestId, array $statuses): Collection
    {
        return Order::query()
            ->where('guest_id', $guestId)
            ->whereIn('status', $statuses)
            ->get();
    }

    public function update(Order $order, array $attributes): bool
    {
        return $order->update($attributes);
    }

    public function updateByIds(array $ids, array $attributes): int
    {
        return Order::query()->whereIn('id', $ids)->update($attributes);
    }

    public function getByIdsWithRelations(array $ids, array $relations = []): Collection
    {
        $query = Order::query()
            ->whereIn('id', $ids)
            ->orderBy('created_at', 'desc');

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->get();
    }

    public function loadRelations(Order $order, array $relations = []): Order
    {
        if ($relations === []) {
            return $order;
        }

        return $order->load($relations);
    }
}
