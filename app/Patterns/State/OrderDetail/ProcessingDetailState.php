<?php

namespace App\Patterns\State\OrderDetail;

use App\Exceptions\ServiceException;
use App\Models\OrderDetail;

class ProcessingDetailState implements OrderDetailState
{
    public function transitionTo(string $status, OrderDetail $detail): void
    {
        if ($status === OrderDetail::STATUS_DELIVERED) {
            $detail->status = OrderDetail::STATUS_DELIVERED;
        } elseif ($status === OrderDetail::STATUS_PROCESSING) {
            throw new ServiceException('Món ăn đang được chế biến rồi', 400);
        } elseif ($status === OrderDetail::STATUS_CANCELLED) {
            throw new ServiceException('Không thể hủy món đang chế biến. Vui lòng liên hệ bếp', 400);
        } else {
            throw new ServiceException('Trạng thái không hợp lệ', 400);
        }
    }
}
