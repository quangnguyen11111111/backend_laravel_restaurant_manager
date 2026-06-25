<?php

namespace App\Patterns\State\Order;

use App\Exceptions\ServiceException;
use App\Models\Order;

class PaidOrderState implements OrderState
{
    public function transitionTo(string $status, Order $order): void
    {
        throw new ServiceException('Đơn hàng đã thanh toán không thể thay đổi trạng thái', 400);
    }
}
