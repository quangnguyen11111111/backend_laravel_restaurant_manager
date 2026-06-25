<?php

namespace App\Patterns\State\OrderDetail;

use App\Models\OrderDetail;

interface OrderDetailState
{
    public function transitionTo(string $status, OrderDetail $detail): void;
}
