<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Order;
use App\Models\Table;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Repositories\Contracts\GuestRepositoryInterface;
use App\Services\GuestService;

class ReservationService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TableRepositoryInterface $tableRepository,
        private readonly GuestRepositoryInterface $guestRepository,
        private readonly GuestService $guestService
    ) {}

    public function createReservation(array $data)
    {
        $capacity = $this->getCapacity($data['guest_count'], $data['reservation_time']);
        if ($capacity['available_count'] === 0) {
            throw new ServiceException('Không có bàn trống phù hợp vào thời gian này', 400);
        }

        $creator = new \App\Patterns\Factory\Order\ReservationOrderCreator(
            $this->orderRepository,
            $this->guestRepository,
            $this->guestService
        );

        return $creator->processOrder($data);
    }

    public function checkInReservation(int $orderId, $tableNumbers)
    {
        if (!is_array($tableNumbers)) {
            $tableNumbers = [$tableNumbers];
        }

        return DB::transaction(function () use ($orderId, $tableNumbers) {
            $order = $this->orderRepository->findByIdOrFailWithRelations($orderId);
            if ($order->status !== Order::STATUS_PENDING_ARRIVAL && $order->status !== Order::STATUS_PENDING) {
                throw new ServiceException('Đơn hàng không hợp lệ để nhận bàn', 400);
            }

            $tables = $this->tableRepository->getByIds($tableNumbers);
            if ($tables->count() !== count($tableNumbers)) {
                throw new ServiceException('Một số bàn không tồn tại', 400);
            }
            foreach ($tables as $table) {
                if ($table->status !== Table::STATUS_AVAILABLE) {
                    throw new ServiceException("Bàn {$table->number} không sẵn sàng", 400);
                }
            }

            // Primary table is the first one
            $primaryTableNumber = $tableNumbers[0];

            $this->orderRepository->update($order, [
                'table_number' => $primaryTableNumber,
                'status' => Order::STATUS_ACTIVE,
            ]);

            // Sync order_tables pivot
            $order->tables()->sync($tableNumbers);

            // Update all tables to OCCUPIED
            foreach ($tables as $table) {
                $this->tableRepository->update($table, [
                    'status' => Table::STATUS_OCCUPIED,
                ]);
            }

            return $order->load('tables');
        });
    }

    private function findBestTableAllocation(int $guestCount, \Illuminate\Support\Collection $freeTables)
    {
        // 1. Standard capacity single table
        $bestSingle = null;
        foreach ($freeTables as $table) {
            if ($table->capacity >= $guestCount) {
                if (!$bestSingle || $table->capacity < $bestSingle->capacity) {
                    $bestSingle = $table;
                }
            }
        }
        if ($bestSingle) {
            return ['tables' => collect([$bestSingle]), 'is_tight_fit' => false, 'requires_merge' => false];
        }

        // 2. Max capacity single table
        $bestTight = null;
        foreach ($freeTables as $table) {
            if ($table->max_capacity >= $guestCount) {
                if (!$bestTight || $table->max_capacity < $bestTight->max_capacity) {
                    $bestTight = $table;
                }
            }
        }
        if ($bestTight) {
            return ['tables' => collect([$bestTight]), 'is_tight_fit' => true, 'requires_merge' => false];
        }

        // 3. Merging
        $bestMerge = null;
        $bestMergeIsTight = false;

        $grouped = $freeTables->filter(fn($t) => !empty($t->group_id))->groupBy('group_id');
        foreach ($grouped as $groupId => $tablesInGroup) {
            $sorted = $tablesInGroup->sortBy('group_order')->values();
            $n = $sorted->count();

            for ($i = 0; $i < $n; $i++) {
                $currentSegment = collect([$sorted[$i]]);
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($sorted[$j]->group_order == $sorted[$j - 1]->group_order + 1) {
                        $currentSegment->push($sorted[$j]);
                    } else {
                        break;
                    }
                }

                $segment = collect();
                for ($j = $i; $j < $i + $currentSegment->count(); $j++) {
                    $segment->push($sorted[$j]);
                    if ($segment->count() < 2) continue;

                    $sumCap = $segment->sum('capacity');
                    $sumMaxCap = $segment->sum('max_capacity');

                    if ($sumCap >= $guestCount) {
                        if (!$bestMerge || $bestMergeIsTight || $segment->count() < $bestMerge->count() || ($segment->count() == $bestMerge->count() && $sumCap < $bestMerge->sum('capacity'))) {
                            $bestMerge = collect($segment->all());
                            $bestMergeIsTight = false;
                        }
                    } elseif ($sumMaxCap >= $guestCount) {
                        if (!$bestMerge || ($bestMergeIsTight && ($segment->count() < $bestMerge->count() || ($segment->count() == $bestMerge->count() && $sumMaxCap < $bestMerge->sum('max_capacity'))))) {
                            $bestMerge = collect($segment->all());
                            $bestMergeIsTight = true;
                        }
                    }
                }
            }
        }

        if ($bestMerge) {
            return ['tables' => $bestMerge, 'is_tight_fit' => $bestMergeIsTight, 'requires_merge' => true];
        }

        return null;
    }

    public function getCapacity(int $guestCount, string $targetTime)
    {
        $time = Carbon::parse($targetTime);
        $startWindow = $time->copy()->subHours(2);
        $endWindow = $time->copy()->addHours(2);

        // 1. Get all visible tables
        $tables = Table::where('status', '!=', Table::STATUS_HIDDEN)->get();

        // 2. Find ACTIVE orders that conflict
        $activeOrders = Order::with('tables')->where('status', Order::STATUS_ACTIVE)
            ->where('updated_at', '>', $time->copy()->subHours(4))
            ->get();
        
        $occupiedTableNumbers = collect();
        foreach ($activeOrders as $order) {
            if ($order->table_number) $occupiedTableNumbers->push($order->table_number);
            foreach ($order->tables as $t) {
                $occupiedTableNumbers->push($t->number);
            }
        }
        $occupiedTableNumbers = $occupiedTableNumbers->unique()->toArray();

        // 3. Find PENDING reservations that conflict
        $pendingReservations = Order::where('status', Order::STATUS_PENDING_ARRIVAL)
            ->whereBetween('reservation_time', [$startWindow, $endWindow])
            ->orderBy('guest_count', 'desc')
            ->get();

        // 4. Simulate free tables
        $freeTables = $tables->filter(function($table) use ($occupiedTableNumbers) {
            return !in_array($table->number, $occupiedTableNumbers);
        })->values();

        // Assign pending reservations to free tables
        foreach ($pendingReservations as $reservation) {
            $allocation = $this->findBestTableAllocation($reservation->guest_count, $freeTables);
            if ($allocation) {
                $allocatedTableNumbers = $allocation['tables']->pluck('number')->toArray();
                $freeTables = $freeTables->filter(function($t) use ($allocatedTableNumbers) {
                    return !in_array($t->number, $allocatedTableNumbers);
                })->values();
            }
        }

        // 5. Check if any remaining free table fits the requested guestCount
        $allocation = $this->findBestTableAllocation($guestCount, $freeTables);

        if (!$allocation) {
            return [
                'target_time' => $time->toDateTimeString(),
                'guest_count' => $guestCount,
                'available_count' => 0,
            ];
        }

        return [
            'target_time' => $time->toDateTimeString(),
            'guest_count' => $guestCount,
            'available_count' => 1,
            'tables' => $allocation['tables']->toArray(),
            'is_tight_fit' => $allocation['is_tight_fit'],
            'requires_merge' => $allocation['requires_merge'],
        ];
    }
}
