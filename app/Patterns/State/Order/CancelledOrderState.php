<?php

namespace App\Patterns\State\Order;

use App\Exceptions\ServiceException;
use App\Models\Order;

class CancelledOrderState implements OrderState
{
    public function transitionTo(string $status, Order $order): void
    {
        throw new ServiceException('Đơn hàng đã hủy không thể thay đổi trạng thái', 400);
    }
}
