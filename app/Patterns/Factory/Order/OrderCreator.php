<?php

namespace App\Patterns\Factory\Order;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class OrderCreator
{
    /**
     * Template method cho quá trình tạo đơn hàng.
     * Xử lý các logic chung như transaction và sinh mã PIN.
     *
     * @param array $data
     * @return mixed
     */
    public function processOrder(array $data)
    {
        return DB::transaction(function () use ($data) {
            if (!isset($data['session_pin'])) {
                $data['session_pin'] = strtoupper(Str::random(4));
            }

            $result = $this->createOrder($data);

            return $result;
        });
    }

    /**
     * Phương thức abstract để các concrete class tự định nghĩa logic tạo.
     *
     * @param array $data
     * @return mixed
     */
    abstract protected function createOrder(array $data);
}
