<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Table;
use App\Repositories\Contracts\DishRepositoryInterface;
use App\Repositories\Contracts\DishSnapshotRepositoryInterface;
use App\Repositories\Contracts\GuestRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly GuestRepositoryInterface $guestRepository,
        private readonly TableRepositoryInterface $tableRepository,
        private readonly DishRepositoryInterface $dishRepository,
        private readonly DishSnapshotRepositoryInterface $dishSnapshotRepository,
        private readonly OrderRepositoryInterface $orderRepository
    ) {}

    public function createOrders(int $orderHandlerId, array $body): array
    {
        $guestId = $body['guestId'];
        $orders = $body['orders'];

        $guest = $this->guestRepository->findById($guestId);
        if (!$guest) {
            throw new ServiceException('Guest không tồn tại', 400);
        }
        if ($guest->table_number === null) {
            throw new ServiceException('Bàn gắn liền với khách hàng này đã bị xóa, vui lòng chọn khách hàng khác!', 400);
        }

        $table = $this->tableRepository->findByNumber($guest->table_number);
        if (!$table) {
            throw new ServiceException('Bàn không tồn tại', 400);
        }
        if ($table->status === Table::STATUS_HIDDEN) {
            throw new ServiceException("Bàn {$table->number} gắn liền với khách hàng đã bị ẩn, vui lòng chọn khách hàng khác!", 400);
        }

        $created = DB::transaction(function () use ($orders, $guestId, $orderHandlerId, $guest) {
            $records = [];
            foreach ($orders as $order) {
                $dish = $this->dishRepository->findById($order['dishId']);
                if (!$dish) {
                    throw new ServiceException('Món không tồn tại', 400);
                }
                if ($dish->status === Dish::STATUS_UNAVAILABLE) {
                    throw new ServiceException("Món {$dish->name} đã hết", 400);
                }
                if ($dish->status === Dish::STATUS_HIDDEN) {
                    throw new ServiceException("Món {$dish->name} không thể đặt", 400);
                }
                $dishSnapshot = $this->dishSnapshotRepository->create([
                    'description' => $dish->description,
                    'image' => $dish->image,
                    'name' => $dish->name,
                    'price' => $dish->price,
                    'dish_id' => $dish->id,
                    'status' => $dish->status,
                ]);
                $orderRecord = $this->orderRepository->create([
                    'dish_snapshot_id' => $dishSnapshot->id,
                    'guest_id' => $guestId,
                    'quantity' => $order['quantity'],
                    'table_number' => $guest->table_number,
                    'order_handler_id' => $orderHandlerId,
                    'status' => Order::STATUS_PENDING,
                ]);
                $records[] = $this->orderRepository->loadRelations($orderRecord, ['dishSnapshot', 'guest']);
            }
            return $records;
        });

        // socket logic not implemented; return orders
        return $created;
    }

    public function guestCreateOrders(int $guestId, array $orders): array
    {
        $created = DB::transaction(function () use ($orders, $guestId) {
            $guest = $this->guestRepository->findById($guestId);
            if (!$guest) {
                throw new ServiceException("Guest không tồn tại ", 400);
            }
            if ($guest->table_number === null) {
                throw new ServiceException('Bàn của bạn đã bị xóa, vui lòng đăng xuất và đăng nhập lại một bàn mới', 400);
            }
            $table = $this->tableRepository->findByNumber($guest->table_number);
            if (!$table) {
                throw new ServiceException('Bàn không tồn tại', 400);
            }
            if ($table->status === Table::STATUS_HIDDEN) {
                throw new ServiceException("Bàn {$table->number} đã bị ẩn, vui lòng đăng xuất và chọn bàn khác", 400);
            }
            if ($table->status === Table::STATUS_RESERVED) {
                throw new ServiceException("Bàn {$table->number} đã được đặt trước, vui lòng đăng xuất và chọn bàn khác", 400);
            }
            $records = [];
            foreach ($orders as $order) {
                $dish = $this->dishRepository->findById($order['dishId']);
                if (!$dish) {
                    throw new ServiceException('Món không tồn tại', 400);
                }
                if ($dish->status === Dish::STATUS_UNAVAILABLE) {
                    throw new ServiceException("Món {$dish->name} đã hết", 400);
                }
                if ($dish->status === Dish::STATUS_HIDDEN) {
                    throw new ServiceException("Món {$dish->name} không thể đặt", 400);
                }
                $dishSnapshot = $this->dishSnapshotRepository->create([
                    'description' => $dish->description,
                    'image' => $dish->image,
                    'name' => $dish->name,
                    'price' => $dish->price,
                    'dish_id' => $dish->id,
                    'status' => $dish->status,
                ]);
                $orderRecord = $this->orderRepository->create([
                    'dish_snapshot_id' => $dishSnapshot->id,
                    'guest_id' => $guestId,
                    'quantity' => $order['quantity'],
                    'table_number' => $guest->table_number,
                    'order_handler_id' => null,
                    'status' => Order::STATUS_PENDING,
                ]);
                $records[] = $this->orderRepository->loadRelations($orderRecord, ['dishSnapshot', 'guest']);
            }
            return $records;
        });

        return $created;
    }

    public function guestGetOrders(int $guestId): array
    {
        return $this->orderRepository
            ->getByGuestIdWithRelations($guestId, ['dishSnapshot', 'orderHandler', 'guest'])
            ->toArray();
    }

    public function getOrders(?string $fromDate = null, ?string $toDate = null)
    {
        return $this->orderRepository
            ->getByFilters($fromDate, $toDate, ['dishSnapshot', 'orderHandler', 'guest'])
            ->toArray();
    }

    public function getOrderDetail(int $orderId)
    {
        return $this->orderRepository->findByIdOrFailWithRelations(
            $orderId,
            ['dishSnapshot', 'orderHandler', 'guest', 'table']
        );
    }

    public function updateOrder(int $orderId, array $body)
    {
        return DB::transaction(function () use ($orderId, $body) {
            $order = $this->orderRepository->findByIdOrFailWithRelations($orderId, ['dishSnapshot']);
            $dishSnapshotId = $order->dish_snapshot_id;
            if (array_key_exists('dishId', $body) && $order->dishSnapshot?->dish_id !== $body['dishId']) {
                $dish = $this->dishRepository->findByIdOrFail($body['dishId']);
                $dishSnapshot = $this->dishSnapshotRepository->create([
                    'description' => $dish->description,
                    'image' => $dish->image,
                    'name' => $dish->name,
                    'price' => $dish->price,
                    'dish_id' => $dish->id,
                    'status' => $dish->status,
                ]);
                $dishSnapshotId = $dishSnapshot->id;
            }
            $updated = $this->orderRepository->update($order, [
                'status' => $body['status'],
                'dish_snapshot_id' => $dishSnapshotId,
                'quantity' => $body['quantity'],
                'order_handler_id' => $body['orderHandlerId'],
            ]);

            if (!$updated) {
                throw new ServiceException('Không thể cập nhật đơn hàng.', 500);
            }

            return $this->orderRepository->loadRelations($order, ['dishSnapshot', 'orderHandler', 'guest']);
        });
    }

    public function payOrders(int $guestId, int $orderHandlerId)
    {
        $orders = $this->orderRepository->getByGuestIdAndStatuses(
            $guestId,
            [Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_DELIVERED]
        );
        if ($orders->isEmpty()) {
            throw new ServiceException('Không có hóa đơn nào cần thanh toán', 400);
        }
        $orderIds = $orders->pluck('id')->toArray();

        DB::transaction(function () use ($orderIds, $orderHandlerId) {
            $this->orderRepository->updateByIds($orderIds, [
                'status' => Order::STATUS_PAID,
                'order_handler_id' => $orderHandlerId,
            ]);
        });

        return $this->orderRepository
            ->getByIdsWithRelations($orderIds, ['dishSnapshot', 'orderHandler', 'guest'])
            ->toArray();
    }
}
