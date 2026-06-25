<?php

namespace App\Patterns\State\OrderDetail;

use App\Exceptions\ServiceException;
use App\Models\OrderDetail;

class PendingDetailState implements OrderDetailState
{
    public function transitionTo(string $status, OrderDetail $detail): void
    {
        if ($status === OrderDetail::STATUS_PROCESSING) {
            $detail->status = OrderDetail::STATUS_PROCESSING;
        } elseif ($status === OrderDetail::STATUS_CANCELLED) {
            $detail->status = OrderDetail::STATUS_CANCELLED;
        } else {
            throw new ServiceException('Trạng thái không hợp lệ hoặc món ăn phải được chế biến trước khi giao', 400);
        }
    }
}
