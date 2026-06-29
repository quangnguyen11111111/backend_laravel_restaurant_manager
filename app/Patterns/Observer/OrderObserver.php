<?php

namespace App\Patterns\Observer;

use App\Models\Order;
use App\Patterns\Observer\Contracts\Observer;
use App\Patterns\Observer\Contracts\Subject;
use App\Services\SocketService;

class OrderObserver implements Observer
{
    protected SocketService $socketService;

    public function __construct(SocketService $socketService)
    {
        $this->socketService = $socketService;
    }

    public function update(Subject $subject, string $event = ''): void
    {
        if (!$subject instanceof Order) return;

        if ($event === 'updated') {
            if ($subject->wasChanged('status')) {
                $subject->loadMissing(['orderDetails.dish', 'orderDetails.guest', 'guest', 'table']);
                
                // Broadcast to managers when order status changes
                $this->socketService->emit('update-order', $subject->toArray(), 'manager-room');

                if ($subject->status === Order::STATUS_PAID && $subject->guest_id) {
                    $socketId = $this->socketService->getSocketByGuestId((string) $subject->guest_id);
                    if ($socketId) {
                        $this->socketService->emit('payment', $subject->toArray(), null, $socketId);
                    }
                }
            }
        }
    }
}
