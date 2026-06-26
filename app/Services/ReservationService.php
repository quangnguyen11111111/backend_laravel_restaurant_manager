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
        return DB::transaction(function () use ($data) {
            $pin = strtoupper(Str::random(4));
            $order = $this->orderRepository->create([
                'guest_count' => $data['guest_count'],
                'reservation_time' => $data['reservation_time'],
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'session_pin' => $pin,
                'status' => Order::STATUS_PENDING_ARRIVAL,
            ]);

            // Tạo guest
            $guest = $this->guestRepository->create([
                'name' => $data['customer_name'],
                'order_id' => $order->id,
            ]);

            $this->orderRepository->update($order, [
                'guest_id' => $guest->id,
            ]);

            // Sinh token
            $tokens = $this->guestService->generateTokens($guest);

            return [
                'order' => $order,
                'guest' => [
                    'id' => $guest->id,
                    'name' => $guest->name,
                    'role' => \App\Models\Guest::ROLE_GUEST,
                    'orderId' => $guest->order_id,
                ],
                'accessToken' => $tokens['accessToken'],
                'refreshToken' => $tokens['refreshToken'],
            ];
        });
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

        // Find tables that can accommodate guestCount
        $tables = Table::where('capacity', '>=', $guestCount)->get();
        
        $availableTables = [];
        foreach ($tables as $table) {
            if ($table->status === Table::STATUS_HIDDEN) {
                continue;
            }

            // check if there's any active session or overlapping reservation
            $hasConflict = Order::where('table_number', $table->number)
                ->where(function ($q) use ($time, $startWindow, $endWindow) {
                    $q->where(function ($q1) use ($time) {
                          $q1->where('status', Order::STATUS_ACTIVE)
                             ->where('updated_at', '>', $time->copy()->subHours(4));
                      })
                      ->orWhere(function ($q2) use ($startWindow, $endWindow) {
                          $q2->where('status', Order::STATUS_PENDING_ARRIVAL)
                             ->whereBetween('reservation_time', [$startWindow, $endWindow]);
                      });
                })->exists();

            if (!$hasConflict) {
                $availableTables[] = $table;
            }
        }

        return [
            'target_time' => $time->toDateTimeString(),
            'guest_count' => $guestCount,
            'available_tables' => $availableTables,
            'available_count' => count($availableTables),
        ];
    }
}
