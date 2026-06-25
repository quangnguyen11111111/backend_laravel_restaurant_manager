<?php

namespace App\Patterns\State\Order;

use App\Models\Order;

interface OrderState
{
    public function transitionTo(string $status, Order $order): void;
}
