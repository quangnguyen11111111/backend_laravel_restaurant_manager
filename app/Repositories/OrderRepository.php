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
        $query = Order::query()
            ->orderByRaw('CASE WHEN reservation_time IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw('COALESCE(reservation_time, created_at) DESC');

        if ($relations !== []) {
            $query->with($relations);
        }

        if (!empty($fromDate) && !empty($toDate)) {
            $from = \Carbon\Carbon::parse($fromDate);
            $to = \Carbon\Carbon::parse($toDate);
            
            $query->where(function ($q) use ($from, $to) {
                $q->where(function ($subQ) use ($from, $to) {
                    $subQ->whereNotNull('reservation_time')
                         ->whereBetween('reservation_time', [$from, $to]);
                })->orWhere(function ($subQ) use ($from, $to) {
                    $subQ->whereNull('reservation_time')
                         ->whereBetween('created_at', [$from, $to]);
                });
            });
        } elseif (!empty($fromDate)) {
            $from = \Carbon\Carbon::parse($fromDate);
            $query->where(function ($q) use ($from) {
                $q->where(function ($subQ) use ($from) {
                    $subQ->whereNotNull('reservation_time')
                         ->where('reservation_time', '>=', $from);
                })->orWhere(function ($subQ) use ($from) {
                    $subQ->whereNull('reservation_time')
                         ->where('created_at', '>=', $from);
                });
            });
        } elseif (!empty($toDate)) {
            $to = \Carbon\Carbon::parse($toDate);
            $query->where(function ($q) use ($to) {
                $q->where(function ($subQ) use ($to) {
                    $subQ->whereNotNull('reservation_time')
                         ->where('reservation_time', '<=', $to);
                })->orWhere(function ($subQ) use ($to) {
                    $subQ->whereNull('reservation_time')
                         ->where('created_at', '<=', $to);
                });
            });
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
