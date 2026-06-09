<?php

namespace App\Repositories;

use App\Models\OrderDetail;
use App\Repositories\Contracts\OrderDetailRepositoryInterface;

class OrderDetailRepository implements OrderDetailRepositoryInterface
{
    public function create(array $data): OrderDetail
    {
        return OrderDetail::create($data);
    }

    public function findById(int $id): ?OrderDetail
    {
        return OrderDetail::find($id);
    }

    public function update(OrderDetail $orderDetail, array $data): bool
    {
        return $orderDetail->update($data);
    }

    public function getByOrderId(int $orderId)
    {
        return OrderDetail::where('order_id', $orderId)->get();
    }
}
