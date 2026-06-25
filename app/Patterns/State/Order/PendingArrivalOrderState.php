<?php

namespace App\Patterns\State\Order;

use App\Exceptions\ServiceException;
use App\Models\Order;

class PendingArrivalOrderState implements OrderState
{
    public function transitionTo(string $status, Order $order): void
    {
        if ($status === Order::STATUS_ACTIVE) {
            $order->status = Order::STATUS_ACTIVE;
        } elseif ($status === Order::STATUS_CANCELLED) {
            $order->status = Order::STATUS_CANCELLED;
        } else {
            throw new ServiceException('Không thể chuyển sang trạng thái này', 400);
        }
    }
}
