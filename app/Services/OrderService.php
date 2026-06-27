<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Table;
use App\Models\OrderDetail;
use App\Repositories\Contracts\DishRepositoryInterface;
use App\Repositories\Contracts\OrderDetailRepositoryInterface;
use App\Repositories\Contracts\GuestRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly GuestRepositoryInterface $guestRepository,
        private readonly TableRepositoryInterface $tableRepository,
        private readonly DishRepositoryInterface $dishRepository,
        private readonly OrderDetailRepositoryInterface $orderDetailRepository,
        private readonly OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * Helper to get active order for a table
     */
    public function getActiveOrderForTable(int $tableNumber): ?Order
    {
        return Order::where('table_number', $tableNumber)
            ->where('status', Order::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Host opens a table
     */
    public function openTableSession(int $tableNumber, int $guestId, int $guestCount = 1): Order
    {
        $table = $this->tableRepository->findByNumber($tableNumber);
        if (!$table || $table->status === Table::STATUS_HIDDEN) {
            throw new ServiceException('Bàn không hợp lệ hoặc đã bị ẩn', 400);
        }

        $activeOrder = $this->getActiveOrderForTable($tableNumber);
        if ($activeOrder) {
            throw new ServiceException('Bàn này đang được sử dụng', 400);
        }

        $creator = new \App\Patterns\Factory\Order\WalkInOrderCreator(
            $this->orderRepository,
            $this->tableRepository,
            $this->guestRepository
        );

        return $creator->processOrder([
            'table_number' => $tableNumber,
            'guest_id' => $guestId,
            'guest_count' => $guestCount
        ]);
    }

    /**
     * POS or App adds dishes to an active order
     */
    public function createOrders(int $orderHandlerId, array $body): array
    {
        // This is POS adding orders directly.
        // It should find the active order for the guest's order_id.
        $guestId = $body['guestId'];
        $items = $body['orders'];

        $guest = $this->guestRepository->findById($guestId);
        if (!$guest || !$guest->order_id) {
            throw new ServiceException('Guest không hợp lệ hoặc không thuộc session nào', 400);
        }

        $order = $this->orderRepository->findByIdOrFailWithRelations($guest->order_id);
        if (!in_array($order->status, [Order::STATUS_ACTIVE, Order::STATUS_PENDING_ARRIVAL])) {
            throw new ServiceException('Session đã đóng hoặc bị huỷ, không thể đặt thêm món', 400);
        }

        return $this->addDishesToOrder($order, $guestId, $items, $orderHandlerId);
    }

    public function guestCreateOrders(int $guestId, array $items): array
    {
        $guest = $this->guestRepository->findById($guestId);
        if (!$guest || !$guest->order_id) {
            throw new ServiceException('Bạn chưa join vào bàn nào', 400);
        }

        $order = $this->orderRepository->findByIdOrFailWithRelations($guest->order_id);
        if (!in_array($order->status, [Order::STATUS_ACTIVE, Order::STATUS_PENDING_ARRIVAL])) {
            throw new ServiceException('Bàn đã đóng hoặc bị huỷ, không thể gọi món', 400);
        }

        return $this->addDishesToOrder($order, $guestId, $items);
    }

    private function addDishesToOrder(Order $order, int $guestId, array $items, ?int $handlerId = null): array
    {
        return DB::transaction(function () use ($order, $guestId, $items, $handlerId) {
            $records = [];
            foreach ($items as $item) {
                $dish = $this->dishRepository->findById($item['dishId']);
                if (!$dish) {
                    throw new ServiceException('Món không tồn tại', 400);
                }
                if ($dish->status === Dish::STATUS_UNAVAILABLE || $dish->status === Dish::STATUS_HIDDEN) {
                    throw new ServiceException("Món {$dish->name} hiện không thể đặt", 400);
                }

                $detail = $this->orderDetailRepository->create([
                    'order_id' => $order->id,
                    'guest_id' => $guestId,
                    'dish_id' => $dish->id,
                    'dish_name' => $dish->name,
                    'dish_price' => $dish->price,
                    'dish_image' => $dish->image,
                    'quantity' => $item['quantity'],
                    'status' => OrderDetail::STATUS_PENDING,
                    'order_handler_id' => $handlerId,
                ]);

                $records[] = $detail->load(['dish', 'guest', 'orderHandler']);
            }
            return $records;
        });
    }

    public function guestGetOrders(int $guestId): array
    {
        $guest = $this->guestRepository->findById($guestId);
        if (!$guest || !$guest->order_id) return [];

        return $this->orderDetailRepository->getByOrderId($guest->order_id)
            ->load(['dish', 'orderHandler', 'guest', 'order'])
            ->toArray();
    }

    public function getOrders(?string $fromDate = null, ?string $toDate = null)
    {
        return $this->orderRepository
            ->getByFilters($fromDate, $toDate, ['orderDetails.dish', 'orderDetails.guest', 'guest'])
            ->toArray();
    }

    public function getOrderDetail(int $orderId)
    {
        return $this->orderRepository->findByIdOrFailWithRelations(
            $orderId,
            ['orderDetails.dish', 'orderDetails.orderHandler', 'orderDetails.guest', 'table', 'guest']
        );
    }

    public function updateOrder(int $orderId, array $body)
    {
        // This used to update a single order item. Now it should update OrderDetail.
        // We will repurpose this for OrderDetail update since the route likely targets a specific item.
        // Actually, let's keep it as is, but it expects `orderId` to be `orderDetailId`.
        return DB::transaction(function () use ($orderId, $body) {
            $detail = $this->orderDetailRepository->findById($orderId);
            if (!$detail) throw new ServiceException('Chi tiết đơn hàng không tồn tại', 404);

            if (isset($body['status']) && $body['status'] !== $detail->status) {
                $status = $body['status'];
                $detail->state()->transitionTo($status, $detail);
            }

            $this->orderDetailRepository->update($detail, [
                'status' => $detail->status,
                'quantity' => $body['quantity'] ?? $detail->quantity,
                'order_handler_id' => $body['orderHandlerId'] ?? $detail->order_handler_id,
            ]);

            return $detail->load(['dish', 'guest', 'orderHandler']);
        });
    }

    public function updateSession(int $orderId, string $status)
    {
        return DB::transaction(function () use ($orderId, $status) {
            $order = $this->orderRepository->findById($orderId);
            if (!$order) throw new ServiceException('Đơn hàng không tồn tại', 404);

            if ($status !== $order->status) {
                $order->state()->transitionTo($status, $order);
            }

            $this->orderRepository->update($order, ['status' => $order->status]);
            return $order->load(['orderDetails.dish', 'orderDetails.guest', 'guest', 'table']);
        });
    }

    public function payOrders(int $guestId, int $orderHandlerId)
    {
        $guest = $this->guestRepository->findById($guestId);
        if (!$guest || !$guest->order_id) {
            throw new ServiceException('Guest không hợp lệ', 400);
        }

        $order = $this->orderRepository->findByIdOrFailWithRelations($guest->order_id, ['orderDetails', 'table']);

        DB::transaction(function () use ($order, $orderHandlerId) {
            $order->state()->transitionTo(Order::STATUS_PAID, $order);
            $this->orderRepository->update($order, [
                'status' => $order->status,
            ]);

            if ($order->table) {
                $this->tableRepository->update($order->table, ['status' => Table::STATUS_AVAILABLE]);
            }
        });

        return $order->toArray();
    }
}
