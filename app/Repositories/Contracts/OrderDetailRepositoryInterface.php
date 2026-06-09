<?php

namespace App\Repositories\Contracts;

use App\Models\OrderDetail;

interface OrderDetailRepositoryInterface
{
    public function create(array $data): OrderDetail;
    
    public function findById(int $id): ?OrderDetail;

    public function update(OrderDetail $orderDetail, array $data): bool;

    public function getByOrderId(int $orderId);
}
