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
     * Hàm lấy session order dựa vào số bàn
     * @param int $tableNumber Số bàn muốn lấy order
     * @return \App\Models\Order|null Trả về Order nếu tìm thấy, ngược lại trả về null
     */
    public function getActiveOrderForTable(?int $tableNumber): ?Order
    {
        if ($tableNumber === null) return null;
        return Order::where(function($query) use ($tableNumber) {
                $query->where('table_number', $tableNumber)
                      ->orWhereHas('tables', function($q) use ($tableNumber) {
                          $q->where('tables.number', $tableNumber);
                      });
            })
            ->where('status', Order::STATUS_ACTIVE)
            ->first();
    }

    /**
     * hàm xử lý mở bàn ăn 
     */
    public function openTableSession(array $tableNumbers, int $guestId, int $guestCount = 1): Order
    {
        $primaryTableNumber = $tableNumbers[0];
        $tables = $this->tableRepository->getByIds($tableNumbers);

        if ($tables->count() !== count($tableNumbers)) {
            throw new ServiceException('Một số bàn không tồn tại', 400);
        }

        foreach ($tables as $table) {
            if ($table->status === Table::STATUS_HIDDEN) {
                throw new ServiceException("Bàn {$table->number} đã bị ẩn", 400);
            }

            $activeOrder = $this->getActiveOrderForTable($table->number);
            if ($activeOrder) {
                throw new ServiceException("Bàn {$table->number} đang được sử dụng", 400);
            }
        }

        $creator = new \App\Patterns\Factory\Order\WalkInOrderCreator(
            $this->orderRepository,
            $this->tableRepository,
            $this->guestRepository
        );

        $order = $creator->processOrder([
            'table_number' => $primaryTableNumber,
            'guest_id' => $guestId,
            'guest_count' => $guestCount
        ]);
        
        $order->tables()->sync($tableNumbers);
        
        return $order;
    }

    /**
     * Hàm xử lý khi nhân viên thêm món ăn vào order
     */
    public function createOrders(int $orderHandlerId, array $body): array
    {
        // Đây là POS thêm order trực tiếp.
        // Nên tìm order đang hoạt động cho order_id của khách.
        $guestId = $body['guestId'];
        $items = $body['orders'];
        $tableNumber = $body['tableNumber'] ?? null;

        $guest = $this->guestRepository->findById($guestId);
        if (!$guest) {
            throw new ServiceException('Guest không hợp lệ', 400);
        }

        if (!$guest->order_id) {
            if (!$tableNumber) {
                throw new ServiceException('Khách hàng này chưa được gán bàn, vui lòng cập nhật bàn hoặc tạo khách hàng mới', 400);
            }
            // Check if there is an active order for the guest's table_number
            $activeOrder = $this->getActiveOrderForTable((int) $tableNumber);
            if ($activeOrder) {
                // join
                $guest->order_id = $activeOrder->id;
                $guest->save();
                $order = $activeOrder;
            } else {
                // create new table session
                $order = $this->openTableSession([(int) $tableNumber], $guest->id, 1);
            }
        } else {
            $order = $this->orderRepository->findByIdOrFailWithRelations($guest->order_id);
            if (!in_array($order->status, [Order::STATUS_ACTIVE, Order::STATUS_PENDING_ARRIVAL])) {
                throw new ServiceException('Session đã đóng hoặc bị huỷ, không thể đặt thêm món', 400);
            }
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

    public function guestCancelOrders(int $guestId): array
    {
        $guest = $this->guestRepository->findById($guestId);
        if (!$guest || !$guest->order_id) {
            throw new ServiceException('Bạn chưa join vào bàn nào', 400);
        }

        return DB::transaction(function () use ($guest) {
            $orderDetails = $this->orderDetailRepository->getByOrderId($guest->order_id);
            
            foreach ($orderDetails as $detail) {
                if ($detail->status !== OrderDetail::STATUS_PENDING && $detail->status !== OrderDetail::STATUS_CANCELLED) {
                    throw new ServiceException('Không thể huỷ đơn vì có món ăn đã được xử lý', 400);
                }
            }
            
            $cancelledRecords = [];
            foreach ($orderDetails as $detail) {
                if ($detail->status === OrderDetail::STATUS_PENDING) {
                    $detail->state()->transitionTo(OrderDetail::STATUS_CANCELLED, $detail);
                    $this->orderDetailRepository->update($detail, [
                        'status' => OrderDetail::STATUS_CANCELLED
                    ]);
                    $cancelledRecords[] = $detail->load(['dish', 'guest', 'orderHandler']);
                }
            }
            
            return $cancelledRecords;
        });
    }

    public function guestCancelOrderDetail(int $guestId, int $orderDetailId): array
    {
        $guest = $this->guestRepository->findById($guestId);
        if (!$guest || !$guest->order_id) {
            throw new ServiceException('Bạn chưa join vào bàn nào', 400);
        }

        return DB::transaction(function () use ($guest, $orderDetailId) {
            $detail = $this->orderDetailRepository->findById($orderDetailId);
            if (!$detail || $detail->guest_id !== $guest->id) {
                throw new ServiceException('Không tìm thấy món ăn hoặc bạn không có quyền huỷ', 400);
            }

            if ($detail->status !== OrderDetail::STATUS_PENDING) {
                throw new ServiceException('Không thể huỷ món ăn đã được xử lý', 400);
            }

            $detail->state()->transitionTo(OrderDetail::STATUS_CANCELLED, $detail);
            $this->orderDetailRepository->update($detail, [
                'status' => OrderDetail::STATUS_CANCELLED
            ]);

            return $detail->load(['dish', 'guest', 'orderHandler'])->toArray();
        });
    }

    public function getOrders(?string $fromDate = null, ?string $toDate = null)
    {
        return $this->orderRepository
            ->getByFilters($fromDate, $toDate, ['orderDetails.dish', 'orderDetails.guest', 'guest', 'tables'])
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
        // Hàm này dùng để cập nhật một item trong order. Bây giờ nó nên cập nhật OrderDetail.
        // Chúng ta sẽ sử dụng lại cái này cho cập nhật OrderDetail vì route có khả năng nhắm vào một item cụ thể.
        // Thực tế, hãy giữ nguyên, nhưng nó mong đợi `orderId` là `orderDetailId`.
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

    //Hàm cập nhật trạng thái của order
    public function updateSession(int $orderId, string $status)
    {
        return DB::transaction(function () use ($orderId, $status) {
            $order = $this->orderRepository->findByIdWithRelations($orderId, ['tables', 'table', 'orderDetails']);
            if (!$order) throw new ServiceException('Đơn hàng không tồn tại', 404);

            if ($status !== $order->status) {
                $order->state()->transitionTo($status, $order);
            }
            
            $this->orderRepository->update($order, ['status' => $order->status]);

            // Nếu đơn hàng bị huỷ, giải phóng bàn và huỷ các món ăn
            if ($order->status === Order::STATUS_CANCELLED) {
                if ($order->table_number || $order->tables->isNotEmpty()) {
                    if ($order->table) {
                        $this->tableRepository->update($order->table, ['status' => Table::STATUS_AVAILABLE]);
                    }
                    foreach ($order->tables as $t) {
                        $this->tableRepository->update($t, ['status' => Table::STATUS_AVAILABLE]);
                    }
                }

                foreach ($order->orderDetails as $detail) {
                    if ($detail->status !== OrderDetail::STATUS_CANCELLED) {
                        $detail->state()->transitionTo(OrderDetail::STATUS_CANCELLED, $detail);
                        $this->orderDetailRepository->update($detail, [
                            'status' => OrderDetail::STATUS_CANCELLED
                        ]);
                    }
                }
            }

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

            if ($order->table_number || $order->tables->isNotEmpty()) {
                if ($order->table) {
                    $this->tableRepository->update($order->table, ['status' => Table::STATUS_AVAILABLE]);
                }
                foreach ($order->tables as $t) {
                    $this->tableRepository->update($t, ['status' => Table::STATUS_AVAILABLE]);
                }
            }
        });

        return $order->toArray();
    }
}
