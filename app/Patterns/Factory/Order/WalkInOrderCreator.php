<?php

namespace App\Patterns\Factory\Order;

use App\Models\Order;
use App\Models\Table;
use App\Repositories\Contracts\GuestRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\TableRepositoryInterface;
use App\Exceptions\ServiceException;

class WalkInOrderCreator extends OrderCreator
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TableRepositoryInterface $tableRepository,
        private readonly GuestRepositoryInterface $guestRepository
    ) {}

    protected function createOrder(array $data): Order
    {
        $tableNumber = $data['table_number'];
        $guestId = $data['guest_id'];
        $guestCount = $data['guest_count'] ?? 1;

        $table = $this->tableRepository->findByNumber($tableNumber);
        if (!$table || $table->status === Table::STATUS_HIDDEN) {
            throw new ServiceException('Bàn không hợp lệ hoặc đã bị ẩn', 400);
        }

        // Kiểm tra xem bàn có order active không
        $activeOrder = Order::where('table_number', $tableNumber)
            ->where('status', Order::STATUS_ACTIVE)
            ->first();

        if ($activeOrder) {
            throw new ServiceException('Bàn này đang được sử dụng', 400);
        }

        $order = $this->orderRepository->create([
            'table_number' => $tableNumber,
            'guest_id' => $guestId,
            'guest_count' => $guestCount,
            'session_pin' => $data['session_pin'],
            'status' => Order::STATUS_ACTIVE,
        ]);

        $this->tableRepository->update($table, ['status' => Table::STATUS_OCCUPIED]);

        $guest = $this->guestRepository->findById($guestId);
        if ($guest) {
            $this->guestRepository->update($guest, ['order_id' => $order->id]);
        }

        return $order;
    }
}
