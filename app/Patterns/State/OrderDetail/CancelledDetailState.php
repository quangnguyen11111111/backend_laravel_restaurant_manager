<?php

namespace App\Patterns\State\OrderDetail;

use App\Exceptions\ServiceException;
use App\Models\OrderDetail;

class CancelledDetailState implements OrderDetailState
{
    public function transitionTo(string $status, OrderDetail $detail): void
    {
        throw new ServiceException('Trạng thái đã hủy không thể thay đổi', 400);
    }
}
