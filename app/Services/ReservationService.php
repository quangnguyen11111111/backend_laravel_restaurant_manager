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

    public function checkInReservation(int $orderId, int $tableNumber)
    {
        return DB::transaction(function () use ($orderId, $tableNumber) {
            $order = $this->orderRepository->findByIdOrFailWithRelations($orderId);
            if ($order->status !== Order::STATUS_PENDING_ARRIVAL) {
                throw new ServiceException('Reservation không hợp lệ để check-in', 400);
            }

            $table = $this->tableRepository->findByNumber($tableNumber);
            if (!$table || $table->status !== Table::STATUS_AVAILABLE) {
                throw new ServiceException('Bàn không sẵn sàng', 400);
            }

            $this->orderRepository->update($order, [
                'table_number' => $tableNumber,
                'status' => Order::STATUS_ACTIVE,
            ]);

            $this->tableRepository->update($table, [
                'status' => Table::STATUS_OCCUPIED,
            ]);

            return $order;
        });
    }

    public function getCapacity(int $guestCount, string $targetTime)
    {
        $time = Carbon::parse($targetTime);
        $startWindow = $time->copy()->subHours(2);
        $endWindow = $time->copy()->addHours(2);

        // 1. Get all visible tables sorted by capacity (ascending)
        $tables = Table::where('status', '!=', Table::STATUS_HIDDEN)
            ->orderBy('capacity', 'asc')
            ->get();

        // 2. Find ACTIVE orders that conflict
        $activeOrders = Order::where('status', Order::STATUS_ACTIVE)
            ->where('updated_at', '>', $time->copy()->subHours(4))
            ->get();
        $occupiedTableNumbers = $activeOrders->pluck('table_number')->filter()->toArray();

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
            $assignedIndex = -1;
            foreach ($freeTables as $index => $table) {
                if ($table->capacity >= $reservation->guest_count) {
                    $assignedIndex = $index;
                    break;
                }
            }
            if ($assignedIndex !== -1) {
                $freeTables->forget($assignedIndex);
                $freeTables = $freeTables->values();
            }
        }

        // 5. Check if any remaining free table fits the requested guestCount
        $suitableTables = [];
        foreach ($freeTables as $table) {
            if ($table->capacity >= $guestCount) {
                $suitableTables[] = $table;
            }
        }

        return [
            'target_time' => $time->toDateTimeString(),
            'guest_count' => $guestCount,
            'available_tables' => array_values($suitableTables),
            'available_count' => count($suitableTables),
        ];
    }
}
