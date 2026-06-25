<?php

namespace App\Patterns\State\Order;

use App\Exceptions\ServiceException;
use App\Models\Order;

class ActiveOrderState implements OrderState
{
    public function transitionTo(string $status, Order $order): void
    {
        if ($status === Order::STATUS_PAID) {
            $order->status = Order::STATUS_PAID;
        } elseif ($status === Order::STATUS_CANCELLED) {
            $order->status = Order::STATUS_CANCELLED;
        } else {
            throw new ServiceException('Trạng thái chuyển đổi không hợp lệ', 400);
        }
    }
}
