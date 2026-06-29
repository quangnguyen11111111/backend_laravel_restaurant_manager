<?php

namespace App\Patterns\Observer;

use App\Models\Table;
use App\Patterns\Observer\Contracts\Observer;
use App\Patterns\Observer\Contracts\Subject;
use App\Services\SocketService;

class TableObserver implements Observer
{
    protected SocketService $socketService;

    public function __construct(SocketService $socketService)
    {
        $this->socketService = $socketService;
    }

    /**
     * @param Subject $subject
     * @param string $event
     */
    public function update(Subject $subject, string $event = ''): void
    {
        if (!$subject instanceof Table) return;

        if ($event === 'updated') {
            if ($subject->wasChanged('status') || $subject->wasChanged('capacity')) {
                $this->socketService->emit('update-table', $subject->toArray(), 'manager-room');
            }
        }
    }
}
