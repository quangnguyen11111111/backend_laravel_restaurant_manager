<?php

namespace App\Patterns\Observer\Contracts;

interface Subject
{
    /**
     * Gắn một Observer vào Subject
     */
    public function attach(Observer $observer): void;

    /**
     * Gỡ bỏ một Observer khỏi Subject
     */
    public function detach(Observer $observer): void;

    /**
     * Thông báo cho tất cả các Observers
     * 
     * @param string $event Tên sự kiện (tuỳ chọn)
     */
    public function notify(string $event = ''): void;
}
