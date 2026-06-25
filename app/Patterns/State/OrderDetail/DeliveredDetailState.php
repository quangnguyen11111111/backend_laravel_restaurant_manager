<?php

namespace App\Patterns\State\OrderDetail;

use App\Exceptions\ServiceException;
use App\Models\OrderDetail;

class DeliveredDetailState implements OrderDetailState
{
    public function transitionTo(string $status, OrderDetail $detail): void
    {
        if ($status === OrderDetail::STATUS_DELIVERED) {
            throw new ServiceException('Món ăn đã được giao rồi', 400);
        } elseif ($status === OrderDetail::STATUS_CANCELLED) {
            throw new ServiceException('Không thể hủy món đã giao', 400);
        } elseif ($status === OrderDetail::STATUS_PROCESSING) {
            throw new ServiceException('Món ăn đã được giao, không thể chế biến lại', 400);
        } else {
            throw new ServiceException('Trạng thái không hợp lệ', 400);
        }
    }
}
