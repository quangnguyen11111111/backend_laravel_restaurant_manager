<?php

namespace App\Patterns\Observer;

use App\Models\OrderDetail;
use App\Patterns\Observer\Contracts\Observer;
use App\Patterns\Observer\Contracts\Subject;
use App\Services\SocketService;

class OrderDetailObserver implements Observer
{
    protected SocketService $socketService;

    public function __construct(SocketService $socketService)
    {
        $this->socketService = $socketService;
    }

    public function update(Subject $subject, string $event = ''): void
    {
        if (!$subject instanceof OrderDetail) return;

        if ($event === 'created') {
            $subject->loadMissing(['dish', 'guest', 'orderHandler']);
            // Báo cho toàn bộ quản lý (manager-room) biết có món mới
            $this->socketService->emit('new-order', $subject->toArray(), 'manager-room');
        }

        if ($event === 'updated') {
            // Kiểm tra xem trường status hoặc quantity có bị thay đổi không
            if ($subject->wasChanged('status') || $subject->wasChanged('quantity')) {
                $subject->loadMissing(['dish', 'guest', 'orderHandler']);
                $data = $subject->toArray();

                // Cập nhật cho quản lý/bếp
                $this->socketService->emit('update-order', $data, 'manager-room');

                // Cập nhật cho đúng vị khách đó (Guest)
                if ($subject->guest_id) {
                    $socketId = $this->socketService->getSocketByGuestId((string) $subject->guest_id);
                    if ($socketId) {
                        $this->socketService->emit('update-order', $data, null, $socketId);
                    }
                }
            }
        }
    }
}
