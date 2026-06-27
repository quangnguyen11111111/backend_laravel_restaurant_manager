<?php

namespace App\Patterns\Factory\Order;

use App\Models\Order;
use App\Models\Guest;
use App\Repositories\Contracts\GuestRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\GuestService;

class ReservationOrderCreator extends OrderCreator
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly GuestRepositoryInterface $guestRepository,
        private readonly GuestService $guestService
    ) {}

    protected function createOrder(array $data): array
    {
        $order = $this->orderRepository->create([
            'guest_count' => $data['guest_count'],
            'reservation_time' => $data['reservation_time'],
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'session_pin' => $data['session_pin'],
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
                'role' => Guest::ROLE_GUEST,
                'orderId' => $guest->order_id,
            ],
            'accessToken' => $tokens['accessToken'],
            'refreshToken' => $tokens['refreshToken'],
        ];
    }
}
